<?php

namespace Pterodactyl\Services\FastDl;

use Aws\S3\S3Client;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\FastDlNode;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Maps the folders in a FastDL bucket to panel servers (each server syncs to
 * <remote_path>/<first 8 chars of its UUID>/) and removes the ones nobody uses.
 */
class FastDlBucketAuditService
{
    /** In use: server exists, FastDL on, server in this FastDL node's location. */
    public const STATUS_ACTIVE = 'active';
    /** Server exists but FastDL is now off. */
    public const STATUS_DISABLED = 'disabled';
    /** Server moved to a location served by another FastDL node. */
    public const STATUS_OTHER_LOCATION = 'other_location';
    /** No server with this ID any more (deleted). */
    public const STATUS_ORPHAN = 'orphan';
    /** Not a server folder (loose files or another name): never removed automatically. */
    public const STATUS_UNKNOWN = 'unknown';

    public const REMOVABLE = [self::STATUS_ORPHAN, self::STATUS_DISABLED, self::STATUS_OTHER_LOCATION];

    public function __construct(private Encrypter $encrypter)
    {
    }

    /**
     * @return array{root: string, folders: array<int, array{folder: string, prefix: string, objects: int, bytes: int, last_modified: ?string, status: string, server: ?Server}>}
     */
    public function audit(FastDlNode $node): array
    {
        $client = $this->client($node);
        $root = $this->rootPrefix($node);

        $folders = [];
        $params = ['Bucket' => $node->bucket, 'Prefix' => $root];
        do {
            $result = $client->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $object) {
                $rest = substr((string) $object['Key'], strlen($root));
                $slash = strpos($rest, '/');
                $folder = $slash === false ? '' : substr($rest, 0, $slash);

                $entry = &$folders[$folder];
                $entry ??= ['folder' => $folder, 'objects' => 0, 'bytes' => 0, 'last_modified' => null];
                ++$entry['objects'];
                $entry['bytes'] += (int) ($object['Size'] ?? 0);
                $modified = isset($object['LastModified']) ? $object['LastModified']->format(DATE_ATOM) : null;
                if ($modified && ($entry['last_modified'] === null || $modified > $entry['last_modified'])) {
                    $entry['last_modified'] = $modified;
                }
                unset($entry);
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while ($result['IsTruncated'] ?? false);

        $ids = array_filter(array_keys($folders), fn ($folder) => $this->isServerFolder((string) $folder));
        $servers = Server::query()->with('node')->whereIn('uuidShort', $ids)->get()->keyBy('uuidShort');

        $rows = [];
        foreach ($folders as $folder => $entry) {
            $folder = (string) $folder;
            $server = $servers->get($folder);
            $entry['prefix'] = $folder === '' ? $root : $root . $folder . '/';
            $entry['server'] = $server;
            $entry['status'] = match (true) {
                !$this->isServerFolder($folder) => self::STATUS_UNKNOWN,
                $server === null => self::STATUS_ORPHAN,
                !$server->fastdl_enabled => self::STATUS_DISABLED,
                (int) $server->node?->location_id !== (int) $node->location_id => self::STATUS_OTHER_LOCATION,
                default => self::STATUS_ACTIVE,
            };
            $rows[] = $entry;
        }

        usort($rows, fn ($a, $b) => [array_search($a['status'], [self::STATUS_ORPHAN, self::STATUS_DISABLED, self::STATUS_OTHER_LOCATION, self::STATUS_UNKNOWN, self::STATUS_ACTIVE]), $a['folder']]
            <=> [array_search($b['status'], [self::STATUS_ORPHAN, self::STATUS_DISABLED, self::STATUS_OTHER_LOCATION, self::STATUS_UNKNOWN, self::STATUS_ACTIVE]), $b['folder']]);

        return ['root' => $root, 'folders' => $rows];
    }

    /**
     * Deletes the given server folders when the audit still says they are unused.
     *
     * @param string[] $folders
     *
     * @return array<string, int> folder => objects deleted
     */
    public function clean(FastDlNode $node, array $folders): array
    {
        $audit = $this->audit($node);
        $client = $this->client($node);

        $deleted = [];
        foreach ($audit['folders'] as $row) {
            if (!in_array($row['folder'], $folders, true) || !in_array($row['status'], self::REMOVABLE, true)) {
                continue;
            }
            // Re-checked above: never touch active, unknown or the bucket root.
            if (!$this->isServerFolder($row['folder'])) {
                continue;
            }
            $client->deleteMatchingObjects($node->bucket, $row['prefix']);
            $deleted[$row['folder']] = $row['objects'];
        }

        return $deleted;
    }

    private function isServerFolder(string $folder): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}$/', $folder);
    }

    private function rootPrefix(FastDlNode $node): string
    {
        $root = trim((string) $node->remote_path, '/');

        return $root === '' ? '' : $root . '/';
    }

    private function client(FastDlNode $node): S3Client
    {
        if (!$node->isS3() || !$node->bucket || !$node->endpoint || !$node->access_key || !$node->secret_key) {
            throw new DisplayException('A auditoria do bucket só funciona em FastDL nodes S3 com bucket, endpoint e credenciais configurados.');
        }

        return new S3Client([
            'version' => 'latest',
            'region' => $node->region ?: 'auto',
            'endpoint' => rtrim($node->endpoint, '/'),
            'use_path_style_endpoint' => $node->use_path_style_endpoint || str_contains(strtolower($node->endpoint), 'r2.cloudflarestorage.com'),
            'credentials' => [
                'key' => $this->encrypter->decrypt($node->access_key),
                'secret' => $this->encrypter->decrypt($node->secret_key),
            ],
        ]);
    }
}
