<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\View\View;
use Pterodactyl\Models\Server;
use Illuminate\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Services\GameQuery\GameQueryService;

/**
 * HLSW-like monitor: lists every game server and queries each one (through Wings on its node)
 * from the browser, one request per server so slow or offline servers don't hold the page.
 */
class GameMonitorController extends Controller
{
    private const CACHE_SECONDS = 8;

    public function __construct(private Repository $cache, private GameQueryService $gameQuery)
    {
    }

    public function index(): View
    {
        $servers = Server::query()
            ->with(['egg:id,name,gamedig', 'allocation', 'node:id,name,fqdn', 'user:id,username'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Server $s) => self::gameType($s) !== null)
            ->map(fn (Server $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'egg' => $s->egg?->name,
                'type' => self::gameType($s),
                'node' => $s->node?->name,
                'owner' => $s->user?->username,
                'address' => $s->allocation ? ServerBoostService::address($s) : null,
                'status' => $s->status,
                'manage_url' => route('admin.servers.view', $s->id),
                'client_url' => '/server/' . $s->uuidShort,
                'query_url' => route('admin.monitor.query', $s->id),
            ])
            ->values();

        return view('admin.monitor.index', ['servers' => $servers]);
    }

    public function query(Server $server): JsonResponse
    {
        if (self::gameType($server) === null) {
            return new JsonResponse(['online' => false, 'error' => 'O egg deste servidor não tem tipo de query.'], 422);
        }
        if ($server->isSuspended()) {
            return new JsonResponse(['online' => false, 'error' => 'Servidor suspenso.']);
        }

        $key = "admin-monitor:{$server->uuid}";
        $result = $this->cache->get($key);
        if (!is_array($result)) {
            $started = microtime(true);
            try {
                $result = $this->gameQuery->query($server);
            } catch (\Throwable $e) {
                $result = ['online' => false, 'error' => $e->getMessage() ?: 'Falha ao consultar o servidor.'];
            }
            $result['took_ms'] = (int) round((microtime(true) - $started) * 1000);
            $this->cache->put($key, $result, Carbon::now()->addSeconds(self::CACHE_SECONDS));
        }

        return new JsonResponse($result);
    }

    /** Query type for the server's egg; TeamSpeak has its own credentials-based query and stays out. */
    private static function gameType(Server $server): ?string
    {
        if (ServerType::isTs3($server)) {
            return null;
        }

        $type = GameDigTypeResolver::resolve($server->egg?->gamedig, $server->egg?->name);

        return $type === 'teamspeak3' ? null : $type;
    }
}
