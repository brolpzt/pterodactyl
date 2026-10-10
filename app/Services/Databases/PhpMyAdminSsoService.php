<?php

namespace Pterodactyl\Services\Databases;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\DatabaseHost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\DisplayException;

class PhpMyAdminSsoService
{
    public function __construct(private Encrypter $encrypter)
    {
    }

    /**
     * Build a one-time SSO URL that opens phpMyAdmin as the given database user.
     *
     * @throws DisplayException
     */
    public function urlForDatabase(Database $database): string
    {
        $database->loadMissing(['host.node']);
        if (!$database->host) {
            throw new DisplayException('Database host is not configured.');
        }

        return $this->buildRedirectUrl([
            'host' => $database->host->host,
            'port' => (int) $database->host->port,
            'username' => $database->username,
            'password' => $this->encrypter->decrypt($database->password),
            'only_db' => [$database->database],
            'actor' => 'client',
            'server_id' => (int) $database->server_id,
            'database_id' => (int) $database->id,
            'phpmyadmin_base' => $this->baseUrlForHost($database->host),
        ]);
    }

    /**
     * Build a one-time SSO URL that opens phpMyAdmin as the host admin user,
     * restricted to all databases belonging to the server.
     *
     * @throws DisplayException
     */
    public function urlForServerAdmin(Server $server): string
    {
        $server->loadMissing(['databases.host.node']);
        $databases = $server->databases;
        if ($databases->isEmpty()) {
            throw new DisplayException('This server has no databases.');
        }

        $host = $databases->first()->host;
        if (!$host) {
            throw new DisplayException('Database host is not configured.');
        }

        // Prefer a database that lives on the same host as the majority / first.
        $onlyDb = $databases
            ->filter(fn (Database $db) => (int) $db->database_host_id === (int) $host->id)
            ->pluck('database')
            ->values()
            ->all();

        if ($onlyDb === []) {
            throw new DisplayException('No databases found on the primary database host.');
        }

        return $this->buildRedirectUrl([
            'host' => $host->host,
            'port' => (int) $host->port,
            'username' => $host->username,
            'password' => $this->encrypter->decrypt($host->password),
            'only_db' => $onlyDb,
            'actor' => 'admin',
            'server_id' => (int) $server->id,
            'database_id' => null,
            'phpmyadmin_base' => $this->baseUrlForHost($host),
        ]);
    }

    /**
     * Redeem a one-time token previously stored by buildRedirectUrl.
     *
     * @return array{
     *   host: string,
     *   port: int,
     *   username: string,
     *   password: string,
     *   only_db: list<string>
     * }
     *
     * @throws DisplayException
     */
    public function redeem(string $token): array
    {
        $this->assertEnabled();

        $token = trim($token);
        if ($token === '' || strlen($token) > 128 || !ctype_xdigit($token)) {
            throw new DisplayException('Invalid phpMyAdmin SSO token.');
        }

        $cacheKey = $this->cacheKey($token);
        $payload = Cache::pull($cacheKey);
        if (!is_array($payload)) {
            throw new DisplayException('phpMyAdmin SSO token is invalid or has expired.');
        }

        return [
            'host' => (string) $payload['host'],
            'port' => (int) $payload['port'],
            'username' => (string) $payload['username'],
            'password' => (string) $payload['password'],
            'only_db' => array_values(array_map('strval', $payload['only_db'] ?? [])),
        ];
    }

    /**
     * Resolve the phpMyAdmin base URL for a database host (one instance per node).
     *
     * @throws DisplayException
     */
    public function baseUrlForHost(DatabaseHost $host): string
    {
        $host->loadMissing('node');

        $template = trim((string) config('phpmyadmin.url_template', 'https://phpmyadmin.{fqdn}'));
        $node = $host->node;

        if ($template !== '' && $node) {
            $fqdn = trim((string) $node->fqdn);
            $name = trim((string) $node->name);
            $short = $fqdn !== '' ? explode('.', $fqdn)[0] : explode('.', $name)[0];

            $base = strtr($template, [
                '{fqdn}' => $fqdn !== '' ? $fqdn : $name,
                '{name}' => $name !== '' ? $name : $fqdn,
                '{node}' => $short,
            ]);

            $base = rtrim($base, '/');
            if ($base !== '' && !str_contains($base, '{')) {
                return $base;
            }
        }

        // Fallback: derive from database host hostname when it looks like a node FQDN.
        $hostName = trim((string) $host->host);
        if ($hostName !== '' && str_contains($hostName, '.')) {
            return 'https://phpmyadmin.' . $hostName;
        }

        $fallback = rtrim((string) config('phpmyadmin.url', ''), '/');
        if ($fallback !== '') {
            return $fallback;
        }

        throw new DisplayException('phpMyAdmin URL is not configured for this database host.');
    }

    /**
     * @param  array{
     *   host: string,
     *   port: int,
     *   username: string,
     *   password: string,
     *   only_db: list<string>,
     *   actor: string,
     *   server_id: int,
     *   database_id: int|null,
     *   phpmyadmin_base: string
     * }  $payload
     *
     * @throws DisplayException
     */
    private function buildRedirectUrl(array $payload): string
    {
        $this->assertEnabled();

        $base = rtrim((string) $payload['phpmyadmin_base'], '/');
        if ($base === '') {
            throw new DisplayException('phpMyAdmin URL is not configured.');
        }

        $ttl = max(15, (int) config('phpmyadmin.token_ttl', 60));
        $token = bin2hex(random_bytes(16));

        Cache::put($this->cacheKey($token), [
            'host' => $payload['host'],
            'port' => $payload['port'],
            'username' => $payload['username'],
            'password' => $payload['password'],
            'only_db' => array_values($payload['only_db']),
            'actor' => $payload['actor'],
            'server_id' => $payload['server_id'],
            'database_id' => $payload['database_id'],
            'created_at' => time(),
        ], $ttl);

        $secret = (string) config('phpmyadmin.sso_secret');
        $expires = time() + $ttl;
        $signature = hash_hmac('sha256', $token . '|' . $expires, $secret);

        return $base . '/sso.php?' . http_build_query([
            'token' => $token,
            'expires' => $expires,
            'signature' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function cacheKey(string $token): string
    {
        return 'phpmyadmin_sso:' . $token;
    }

    /**
     * @throws DisplayException
     */
    private function assertEnabled(): void
    {
        if (!config('phpmyadmin.enabled', true)) {
            throw new DisplayException('phpMyAdmin SSO is disabled.');
        }

        $secret = (string) config('phpmyadmin.sso_secret');
        if (strlen($secret) < 32) {
            throw new DisplayException('PHPMYADMIN_SSO_SECRET is not configured.');
        }
    }
}
