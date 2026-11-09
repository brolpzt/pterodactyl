<?php

namespace Pterodactyl\Services\HgHlds;

use Pterodactyl\Models\Server;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Tells whether a server is running the hgHLDS engine by looking at the engine file
 * itself, as the addon record says nothing once a reinstall or a Steam validate has
 * restored the stock engine.
 */
class EngineDetector
{
    private const ENGINE_FILE = 'engine_i486.so';

    private const RESULT_TTL = 300;

    /** Short, so a node that is down is not asked again on every page. */
    private const UNKNOWN_TTL = 60;

    public function __construct(
        private CacheRepository $cache,
        private DaemonFileRepository $files,
    ) {
    }

    /**
     * Returns null when it cannot be determined: the node did not answer or the
     * server has no HLDS engine at all.
     */
    public function isInstalled(Server $server): ?bool
    {
        $key = "hghlds:engine:{$server->uuid}";

        // Wrapped in an array so that an unknown result can be cached as well.
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached['installed'];
        }

        $installed = $this->inspect($server);
        $this->cache->put($key, ['installed' => $installed], $installed === null ? self::UNKNOWN_TTL : self::RESULT_TTL);

        return $installed;
    }

    private function inspect(Server $server): ?bool
    {
        try {
            $entries = $this->files->setServer($server)->getDirectory('/');
        } catch (DaemonConnectionException) {
            return null;
        }

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === self::ENGINE_FILE && ($entry['file'] ?? false)) {
                return in_array((int) $entry['size'], config('hghlds.engine_sizes', []), true);
            }
        }

        return null;
    }
}
