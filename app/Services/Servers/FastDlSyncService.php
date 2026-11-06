<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\FastDlNode;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class FastDlSyncService
{
    /**
     * FastDlSyncService constructor.
     */
    public function __construct(
        protected DaemonServerRepository $daemonServerRepository,
        protected Encrypter $encrypter
    ) {
    }

    /**
     * Trigger a FastDL synchronization for a server.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function handle(Server $server): void
    {
        if (!$server->fastdl_enabled) {
            throw new DisplayException('FastDL is not enabled for this server.');
        }

        if (!in_array('fastdl', $server->egg->features ?? [])) {
            throw new DisplayException('The Egg assigned to this server does not support FastDL (missing "fastdl" feature).');
        }

        $node = FastDlNode::where('location_id', $server->node->location_id)
            ->where('is_active', true)
            ->first();

        if (!$node) {
            throw new DisplayException('No active FastDL node is configured for this server\'s location.');
        }

        // Never ship stock game files: without the install manifest Wings cannot tell
        // them apart from the user's own files, so nothing is synchronized.
        try {
            $manifest = $this->daemonServerRepository->setServer($server)->getFastDlManifest();
        } catch (\Throwable $exception) {
            throw new DisplayException('Não foi possível verificar o manifesto de arquivos originais do FastDL no node; a sincronização foi cancelada para não enviar arquivos do jogo. Tente novamente mais tarde.');
        }
        if (!($manifest['exists'] ?? false)) {
            throw new DisplayException('Este servidor ainda não tem o manifesto de arquivos originais do FastDL, então a sincronização foi bloqueada para não enviar arquivos do jogo. Reinstale o servidor ou peça ao suporte para gerar o manifesto.');
        }

        $payload = [
            'storage_type' => $node->storage_type,
            'remote_path' => $node->remote_path,
            'sync_patterns' => $this->patternsFor($server, $node),
            // Wings watches the files and asks for a sync when new ones appear.
            'auto_sync' => (bool) $server->egg->fastdl_auto_sync,
        ];

        if ($node->isS3()) {
            $payload = array_merge($payload, [
                'bucket' => $node->bucket,
                'endpoint' => $node->endpoint,
                'region' => $node->region ?? 'auto',
                'access_key' => $this->encrypter->decrypt($node->access_key),
                'secret_key' => $this->encrypter->decrypt($node->secret_key),
                'use_path_style_endpoint' => $node->use_path_style_endpoint,
            ]);
        } else {
            $password = $node->password ? $this->encrypter->decrypt($node->password) : null;
            $privateKey = $node->private_key ? $this->encrypter->decrypt($node->private_key) : null;

            if (!$password && !$privateKey) {
                throw new DisplayException('The FastDL node has no SSH credentials configured.');
            }

            $payload = array_merge($payload, [
                'host' => $node->fqdn,
                'port' => $node->port,
                'user' => $node->username,
                'password' => $password,
                'private_key' => $privateKey,
            ]);
        }

        $this->daemonServerRepository->setServer($server)->syncFastDl($payload);
    }

    /**
     * File types the user sees in the panel: include rules of the effective
     * patterns, without the "+" marker. Null when no FastDL node serves the server.
     *
     * @return string[]|null
     */
    public function fileTypesFor(Server $server): ?array
    {
        $node = FastDlNode::query()
            ->where('location_id', $server->node->location_id)
            ->where('is_active', true)
            ->first();
        if (!$node) {
            return null;
        }

        $types = [];
        foreach (preg_split('/[,;\r\n]+/', (string) $this->patternsFor($server, $node)) as $rule) {
            $rule = ltrim(trim($rule), '+');
            if ($rule !== '' && !str_starts_with($rule, '!')) {
                $types[] = trim($rule);
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Each game downloads different file types, so they come from the server's
     * egg; the FastDL node's patterns are the fallback.
     */
    public function patternsFor(Server $server, FastDlNode $node): ?string
    {
        $patterns = trim((string) $server->egg?->fastdl_patterns);

        return $patterns !== '' ? $patterns : $node->sync_patterns;
    }
}
