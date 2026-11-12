<?php

namespace Pterodactyl\Http\Controllers\Api\Boost;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Services\Boost\BoostBlacklistService;

/**
 * Endpoints for the external boost system, authenticated by the token in the URL
 * (Admin → Server Boost).
 */
class BoostController extends Controller
{
    public function __construct(private ServerBoostService $service, private BoostBlacklistService $blacklist)
    {
    }

    /**
     * GET /api/boost/{token}/blacklist — active entries (expired ones are left out).
     * JSON with ips / steam_ids / names lists and the full entries; ?format=txt&type=ip|steam_id|name
     * returns one value per line. Send If-None-Match with the last ETag to get a 304 when unchanged.
     */
    public function blacklist(Request $request, string $token): JsonResponse|Response
    {
        $this->authorizeToken($token);

        $payload = $this->blacklist->payload();
        $txt = $request->query('format') === 'txt';
        $type = (string) $request->query('type', '');

        if ($txt) {
            $lists = ['ip' => 'ips', 'steam_id' => 'steam_ids', 'name' => 'names'];
            if (!isset($lists[$type])) {
                return new JsonResponse(['ok' => false, 'error' => 'com format=txt informe type=ip, steam_id ou name'], 422);
            }
            $values = $payload[$lists[$type]];
            $response = new Response($values === [] ? '' : implode("\n", $values) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } else {
            $response = new JsonResponse($payload);
        }

        $response->setEtag(md5(($txt ? "txt:{$type}:" : 'json:') . json_encode($payload['entries'])));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * GET /api/boost/{token}/servers — servers of the boost eggs that are not hidden,
     * suspended or installing. ?format=txt returns one ip:port per line.
     */
    public function servers(Request $request, string $token): JsonResponse|Response
    {
        $this->authorizeToken($token);

        $servers = $this->service->listed();

        if ($request->query('format') === 'txt') {
            $body = $servers->map(fn (Server $s) => ServerBoostService::address($s))->implode("\n");

            return new Response($body === '' ? '' : $body . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new JsonResponse([
            'count' => $servers->count(),
            'servers' => $servers->map(fn (Server $s) => ServerBoostService::entry($s))->all(),
        ]);
    }

    /**
     * POST (or GET) /api/boost/{token}/connect — a player was sent to a server.
     * Server: server_id (from the list) or server=ip:port. Optional: player, player_ip, steam_id.
     */
    public function connect(Request $request, string $token): JsonResponse
    {
        $this->authorizeToken($token);

        $validator = Validator::make($request->all(), [
            'server_id' => 'nullable|integer|min:1',
            'server' => 'nullable|string|max:191',
            'player' => 'nullable|string|max:255',
            'player_ip' => 'nullable|ip',
            'steam_id' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return new JsonResponse(['ok' => false, 'error' => $validator->errors()->first()], 422);
        }
        $data = $validator->validated();

        if (empty($data['server_id']) && empty($data['server'])) {
            return new JsonResponse(['ok' => false, 'error' => 'informe server_id ou server (ip:porta)'], 422);
        }

        $server = $this->service->resolve(
            isset($data['server_id']) ? (string) $data['server_id'] : null,
            $data['server'] ?? null,
        );
        if ($server === null) {
            return new JsonResponse(['ok' => false, 'error' => 'servidor não encontrado na lista do boost'], 404);
        }

        $connection = $this->service->record($server, [
            'player_name' => $data['player'] ?? null,
            'player_ip' => $data['player_ip'] ?? null,
            'steam_id' => $data['steam_id'] ?? null,
        ], $request->ip());

        $blocked = BoostBlacklistService::match(
            $this->blacklist->active(),
            $connection->player_ip,
            $connection->steam_id,
            $connection->player_name,
        );

        return new JsonResponse([
            'ok' => true,
            'id' => $connection->id,
            'server_id' => $server->id,
            'address' => $connection->address,
            'blacklisted' => $blocked !== null,
        ], 201);
    }

    private function authorizeToken(string $token): void
    {
        // 404 rather than 401/403: a wrong token reveals nothing about the endpoint.
        abort_unless($this->service->validToken($token), 404);
    }
}
