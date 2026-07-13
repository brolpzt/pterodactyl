<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Support\WebRcon;
use Pterodactyl\Services\WebRcon\WebRconService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class WebRconController extends ClientApiController
{
    public function __construct(private WebRconService $webRconService)
    {
        parent::__construct();
    }

    public function overview(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanReadWebRcon($request, $server);

        return [
            'object' => 'webrcon_overview',
            'attributes' => $this->webRconService->overview($server),
        ];
    }

    public function listPlayers(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanReadWebRcon($request, $server);

        return [
            'object' => 'webrcon_players',
            'attributes' => $this->webRconService->listPlayers($server),
        ];
    }

    public function kickPlayer(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanKickWebRcon($request, $server);

        $data = $request->validate([
            'clientnum' => ['required', 'integer', 'min:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $result = $this->webRconService->kickPlayer(
            $server,
            (int) $data['clientnum'],
            $data['reason'] ?? null,
        );

        Activity::event('server:webrcon.player.kick')
            ->property([
                'clientnum' => $data['clientnum'],
                'reason' => $data['reason'] ?? null,
            ])
            ->log();

        return [
            'object' => 'webrcon_kick',
            'attributes' => $result,
        ];
    }

    public function banPlayer(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanBanWebRcon($request, $server);

        $data = $request->validate([
            'clientnum' => ['required', 'integer', 'min:0'],
            'minutes' => ['sometimes', 'integer', 'min:0', 'max:525600'],
            'guid' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $result = $this->webRconService->banPlayer(
            $server,
            (int) $data['clientnum'],
            (int) ($data['minutes'] ?? 0),
            $data['guid'] ?? null,
        );

        Activity::event('server:webrcon.player.ban')
            ->property([
                'clientnum' => $data['clientnum'],
                'minutes' => $data['minutes'] ?? 0,
            ])
            ->log();

        return [
            'object' => 'webrcon_ban',
            'attributes' => $result,
        ];
    }

    public function listMaps(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanReadWebRcon($request, $server);

        return [
            'object' => 'list',
            'data' => $this->webRconService->listMaps($server),
        ];
    }

    public function changeMap(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanChangeMapWebRcon($request, $server);

        $data = $request->validate([
            'map' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->webRconService->changeMap($server, $data['map']);

        Activity::event('server:webrcon.map.change')
            ->property(['map' => $result['map']])
            ->log();

        return [
            'object' => 'webrcon_map',
            'attributes' => $result,
        ];
    }

    public function sendSay(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanChatWebRcon($request, $server);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->webRconService->sendSay($server, $data['message']);

        Activity::event('server:webrcon.chat.say')
            ->property(['message' => $result['message']])
            ->log();

        return [
            'object' => 'webrcon_say',
            'attributes' => $result,
        ];
    }

    public function sendTell(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanChatWebRcon($request, $server);

        $data = $request->validate([
            'clientnum' => ['required', 'integer', 'min:0'],
            'message' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->webRconService->sendTell(
            $server,
            (int) $data['clientnum'],
            $data['message'],
        );

        Activity::event('server:webrcon.chat.tell')
            ->property([
                'clientnum' => $data['clientnum'],
                'message' => $result['message'],
            ])
            ->log();

        return [
            'object' => 'webrcon_tell',
            'attributes' => $result,
        ];
    }

    public function listDvars(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanDvarWebRcon($request, $server);

        return [
            'object' => 'list',
            'data' => $this->webRconService->listDvars($server),
        ];
    }

    public function queryDvar(Request $request, Server $server, string $name): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanDvarWebRcon($request, $server);

        return [
            'object' => 'webrcon_dvar',
            'attributes' => $this->webRconService->queryDvar($server, $name),
        ];
    }

    public function setDvar(Request $request, Server $server): array
    {
        $this->assertWebRconEnabled($server);
        $this->assertCanDvarWebRcon($request, $server);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'value' => ['required', 'string', 'max:128'],
        ]);

        $result = $this->webRconService->setDvar($server, $data['name'], $data['value']);

        Activity::event('server:webrcon.dvar.set')
            ->property([
                'name' => $result['name'],
                'value' => $result['value'],
            ])
            ->log();

        return [
            'object' => 'webrcon_dvar',
            'attributes' => $result,
        ];
    }

    private function assertWebRconEnabled(Server $server): void
    {
        if (!WebRcon::isEnabled($server)) {
            throw new AccessDeniedHttpException('WebRCON não está habilitado para este servidor.');
        }
    }

    private function assertCanReadWebRcon(Request $request, Server $server): void
    {
        if (!$this->hasWebRconOr($request, $server, Permission::ACTION_WEBRCON_READ, Permission::ACTION_CONTROL_CONSOLE)) {
            throw new AccessDeniedHttpException('Não tem permissão para visualizar o WebRCON.');
        }
    }

    private function assertCanKickWebRcon(Request $request, Server $server): void
    {
        if (!$this->hasWebRconOr($request, $server, Permission::ACTION_WEBRCON_KICK, Permission::ACTION_CONTROL_CONSOLE)) {
            throw new AccessDeniedHttpException('Não tem permissão para expulsar jogadores via WebRCON.');
        }
    }

    private function assertCanBanWebRcon(Request $request, Server $server): void
    {
        if (!$this->hasWebRconOr($request, $server, Permission::ACTION_WEBRCON_BAN, Permission::ACTION_FIREWALL_CREATE)) {
            throw new AccessDeniedHttpException('Não tem permissão para banir jogadores via WebRCON.');
        }
    }

    private function assertCanChangeMapWebRcon(Request $request, Server $server): void
    {
        if (!$this->hasWebRconOr($request, $server, Permission::ACTION_WEBRCON_MAP, Permission::ACTION_CONTROL_CONSOLE)) {
            throw new AccessDeniedHttpException('Não tem permissão para trocar o mapa via WebRCON.');
        }
    }

    private function assertCanChatWebRcon(Request $request, Server $server): void
    {
        if (!$this->hasWebRconOr($request, $server, Permission::ACTION_WEBRCON_CHAT, Permission::ACTION_CONTROL_CONSOLE)) {
            throw new AccessDeniedHttpException('Não tem permissão para enviar mensagens via WebRCON.');
        }
    }

    private function assertCanDvarWebRcon(Request $request, Server $server): void
    {
        if (!$this->hasWebRconOr($request, $server, Permission::ACTION_WEBRCON_DVAR, Permission::ACTION_CONTROL_CONSOLE)) {
            throw new AccessDeniedHttpException('Não tem permissão para gerir dvars via WebRCON.');
        }
    }

    private function hasWebRconOr(Request $request, Server $server, string $webRconPermission, string $fallbackPermission): bool
    {
        return $request->user()->can($webRconPermission, $server)
            || $request->user()->can($fallbackPermission, $server);
    }
}
