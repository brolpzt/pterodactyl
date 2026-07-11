<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Services\Amxx\AmxxService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class AmxxController extends ClientApiController
{
    public function __construct(private AmxxService $amxxService)
    {
        parent::__construct();
    }

    public function overview(Request $request, Server $server): array
    {
        $this->assertCs16($server);
        $this->assertCanReadFiles($request, $server);

        return [
            'object' => 'amxx_overview',
            'attributes' => $this->amxxService->overview($server),
        ];
    }

    public function listAdmins(Request $request, Server $server): array
    {
        $this->assertCs16($server);
        $this->assertCanReadFiles($request, $server);

        $page = $this->amxxService->adminsPage($server);

        return [
            'object' => 'amxx_admins_page',
            'attributes' => [
                'overview' => $page['overview'],
            ],
            'data' => $page['admins'],
        ];
    }

    public function createAdmin(Request $request, Server $server): array
    {
        $this->assertCs16($server);
        $this->assertCanWriteFiles($request, $server);

        $data = $request->validate([
            'auth_type' => ['required', 'string', 'in:steamid,ip,nickname'],
            'auth' => ['required', 'string', 'max:64'],
            'password' => ['sometimes', 'nullable', 'string', 'max:64'],
            'access_flags' => ['required', 'string', 'max:32'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:64'],
            'preset' => ['sometimes', 'nullable', 'string', 'in:owner,admin,mod'],
            'reload' => ['sometimes', 'boolean'],
        ]);

        $accessFlags = $data['access_flags'];
        if (!empty($data['preset']) && isset(AmxxService::PRESET_FLAGS[$data['preset']])) {
            $accessFlags = AmxxService::PRESET_FLAGS[$data['preset']];
        }

        $result = $this->amxxService->createAdmin(
            $server,
            $data['auth_type'],
            $data['auth'],
            $data['password'] ?? '',
            $accessFlags,
            $data['nickname'] ?? null,
            $data['reload'] ?? true,
        );

        Activity::event('server:amxx.admin.create')
            ->property([
                'auth_type' => $data['auth_type'],
                'auth' => $data['auth'],
                'access_flags' => $accessFlags,
            ])
            ->log();

        return [
            'object' => 'amxx_admin',
            'attributes' => $result,
        ];
    }

    public function updateAdmin(Request $request, Server $server, int $adminId): array
    {
        $this->assertCs16($server);
        $this->assertCanWriteFiles($request, $server);

        $data = $request->validate([
            'auth_type' => ['required', 'string', 'in:steamid,ip,nickname'],
            'auth' => ['required', 'string', 'max:64'],
            'password' => ['sometimes', 'nullable', 'string', 'max:64'],
            'access_flags' => ['required', 'string', 'max:32'],
            'nickname' => ['sometimes', 'nullable', 'string', 'max:64'],
            'enabled' => ['sometimes', 'boolean'],
            'preset' => ['sometimes', 'nullable', 'string', 'in:owner,admin,mod'],
            'reload' => ['sometimes', 'boolean'],
        ]);

        $accessFlags = $data['access_flags'];
        if (!empty($data['preset']) && isset(AmxxService::PRESET_FLAGS[$data['preset']])) {
            $accessFlags = AmxxService::PRESET_FLAGS[$data['preset']];
        }

        $result = $this->amxxService->updateAdmin(
            $server,
            $adminId,
            $data['auth_type'],
            $data['auth'],
            $data['password'] ?? '',
            $accessFlags,
            $data['nickname'] ?? null,
            $data['enabled'] ?? true,
            $data['reload'] ?? true,
        );

        Activity::event('server:amxx.admin.update')
            ->property([
                'admin_id' => $adminId,
                'auth' => $data['auth'],
                'enabled' => $data['enabled'] ?? true,
            ])
            ->log();

        return [
            'object' => 'amxx_admin',
            'attributes' => $result,
        ];
    }

    public function deleteAdmin(Request $request, Server $server, int $adminId): JsonResponse
    {
        $this->assertCs16($server);
        $this->assertCanWriteFiles($request, $server);

        $this->amxxService->deleteAdmin($server, $adminId, $request->boolean('reload', true));

        Activity::event('server:amxx.admin.delete')
            ->property(['admin_id' => $adminId])
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function listBans(Request $request, Server $server): array
    {
        $this->assertCs16($server);
        $this->assertCanReadBans($request, $server);

        return [
            'object' => 'list',
            'data' => $this->amxxService->listBans($server),
        ];
    }

    public function createBan(Request $request, Server $server): array
    {
        $this->assertCs16($server);
        $this->assertCanCreateBan($request, $server);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:steamid,ip'],
            'identifier' => ['required', 'string', 'max:64'],
            'minutes' => ['sometimes', 'integer', 'min:0', 'max:525600'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'apply_live' => ['sometimes', 'boolean'],
        ]);

        $result = $this->amxxService->createBan(
            $server,
            $data['type'],
            $data['identifier'],
            (int) ($data['minutes'] ?? 0),
            $data['reason'] ?? null,
            $data['apply_live'] ?? true,
        );

        Activity::event('server:amxx.ban.create')
            ->property([
                'type' => $data['type'],
                'identifier' => $data['identifier'],
                'minutes' => $data['minutes'] ?? 0,
                'reason' => $data['reason'] ?? null,
            ])
            ->log();

        return [
            'object' => 'amxx_ban',
            'attributes' => $result,
        ];
    }

    public function deleteBan(Request $request, Server $server, string $banId): JsonResponse
    {
        $this->assertCs16($server);
        $this->assertCanDeleteBan($request, $server);

        $this->amxxService->deleteBan($server, $banId, $request->boolean('apply_live', true));

        Activity::event('server:amxx.ban.delete')
            ->property(['ban_id' => $banId])
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    private function assertCs16(Server $server): void
    {
        if (!ServerType::isCs16($server)) {
            throw new AccessDeniedHttpException('Este endpoint está disponível apenas para servidores Counter-Strike 1.6.');
        }
    }

    private function assertCanReadFiles(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_FILE_READ_CONTENT, $server)) {
            throw new AccessDeniedHttpException('Não tem permissão para ler ficheiros do servidor.');
        }
    }

    private function assertCanWriteFiles(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_FILE_UPDATE, $server)) {
            throw new AccessDeniedHttpException('Não tem permissão para editar ficheiros do servidor.');
        }
    }

    private function assertCanReadBans(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_FIREWALL_READ, $server)) {
            throw new AccessDeniedHttpException('Não tem permissão para visualizar bans.');
        }
    }

    private function assertCanCreateBan(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_FIREWALL_CREATE, $server)) {
            throw new AccessDeniedHttpException('Não tem permissão para criar bans.');
        }
    }

    private function assertCanDeleteBan(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_FIREWALL_DELETE, $server)) {
            throw new AccessDeniedHttpException('Não tem permissão para remover bans.');
        }
    }
}
