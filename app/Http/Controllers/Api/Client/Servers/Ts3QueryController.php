<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Str;
use Pterodactyl\Models\Ts3Snapshot;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Services\Ts3\Ts3QueryService;
use Pterodactyl\Services\Servers\ReinstallServerService;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class Ts3QueryController extends ClientApiController
{
    public function __construct(
        private Ts3QueryService $ts3QueryService,
        private DaemonPowerRepository $powerRepository,
        private ReinstallServerService $reinstallServerService,
    ) {
        parent::__construct();
    }

    public function overview(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanRead($request, $server);

        return [
            'object' => 'ts3_overview',
            'attributes' => $this->ts3QueryService->overview($server),
        ];
    }

    public function listBans(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        if (!$request->user()->can(Permission::ACTION_FIREWALL_READ, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to view bans.');
        }

        return [
            'object' => 'list',
            'data' => $this->ts3QueryService->listBans($server),
        ];
    }

    public function createBan(Request $request, Server $server): JsonResponse
    {
        $this->assertTs3($server);
        if (!$request->user()->can(Permission::ACTION_FIREWALL_CREATE, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to create bans.');
        }

        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'time' => ['sometimes', 'integer', 'min:0', 'max:31536000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $this->ts3QueryService->createBan(
            $server,
            $data['ip'],
            (int) ($data['time'] ?? 0),
            $data['reason'] ?? null
        );

        Activity::event('server:ts3.ban.create')
            ->property(['ip' => $data['ip'], 'time' => $data['time'] ?? 0, 'reason' => $data['reason'] ?? null])
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function deleteBan(Request $request, Server $server, int $banId): JsonResponse
    {
        $this->assertTs3($server);
        if (!$request->user()->can(Permission::ACTION_FIREWALL_DELETE, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to delete bans.');
        }

        $this->ts3QueryService->deleteBan($server, $banId);

        Activity::event('server:ts3.ban.delete')->property(['ban_id' => $banId])->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function listTokens(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanRead($request, $server);

        return [
            'object' => 'list',
            'data' => $this->ts3QueryService->listTokens($server),
        ];
    }

    public function createToken(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanManage($request, $server);

        $data = $request->validate([
            'group_id' => ['sometimes', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $result = $this->ts3QueryService->createToken(
            $server,
            (int) ($data['group_id'] ?? 6),
            (string) ($data['description'] ?? '')
        );

        Activity::event('server:ts3.token.create')
            ->property(['group_id' => $data['group_id'] ?? 6, 'description' => $data['description'] ?? ''])
            ->log();

        return [
            'object' => 'ts3_token',
            'attributes' => $result,
        ];
    }

    public function deleteToken(Request $request, Server $server): JsonResponse
    {
        $this->assertTs3($server);
        $this->assertCanManage($request, $server);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $this->ts3QueryService->deleteToken($server, $data['token']);

        Activity::event('server:ts3.token.delete')->property(['token' => $data['token']])->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function logs(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanRead($request, $server);

        $data = $request->validate([
            'lines' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        return [
            'object' => 'list',
            'data' => $this->ts3QueryService->logs($server, (int) ($data['lines'] ?? 100)),
        ];
    }

    public function htmlViewer(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanRead($request, $server);

        return [
            'object' => 'ts3_html_viewer',
            'attributes' => $this->ts3QueryService->htmlViewer($server),
        ];
    }

    public function listSnapshots(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanBackupRead($request, $server);

        $snapshots = Ts3Snapshot::query()
            ->where('server_id', $server->id)
            ->orderByDesc('created_at')
            ->get(['uuid', 'name', 'created_by', 'created_at', 'updated_at']);

        return [
            'object' => 'list',
            'data' => $snapshots->toArray(),
        ];
    }

    public function createSnapshot(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertCanBackupCreate($request, $server);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $snapshotPayload = $this->ts3QueryService->createSnapshot($server);

        $snapshot = Ts3Snapshot::query()->create([
            'server_id' => $server->id,
            'uuid' => Str::uuid()->toString(),
            'name' => (string) ($data['name'] ?? ('Snapshot ' . now()->format('Y-m-d H:i:s'))),
            'snapshot' => $snapshotPayload,
            'created_by' => $request->user()->id,
        ]);

        Activity::event('server:ts3.snapshot.create')
            ->property(['snapshot_uuid' => $snapshot->uuid, 'name' => $snapshot->name])
            ->log();

        return [
            'object' => 'ts3_snapshot',
            'attributes' => $snapshot->only(['uuid', 'name', 'created_by', 'created_at', 'updated_at']),
        ];
    }

    public function restoreSnapshot(Request $request, Server $server, string $snapshotUuid): JsonResponse
    {
        $this->assertTs3($server);
        $this->assertCanBackupRestore($request, $server);

        $snapshot = $this->findSnapshotOrFail($server, $snapshotUuid);
        $this->ts3QueryService->restoreSnapshot($server, $snapshot->snapshot);

        Activity::event('server:ts3.snapshot.restore')
            ->property(['snapshot_uuid' => $snapshot->uuid, 'name' => $snapshot->name])
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function deleteSnapshot(Request $request, Server $server, string $snapshotUuid): JsonResponse
    {
        $this->assertTs3($server);
        $this->assertCanBackupDelete($request, $server);

        $snapshot = $this->findSnapshotOrFail($server, $snapshotUuid);
        $snapshot->delete();

        Activity::event('server:ts3.snapshot.delete')
            ->property(['snapshot_uuid' => $snapshot->uuid, 'name' => $snapshot->name])
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function action(Request $request, Server $server, string $action): JsonResponse
    {
        $this->assertTs3($server);

        if (in_array($action, ['start', 'stop', 'restart'], true)) {
            $permissionMap = [
                'start' => Permission::ACTION_CONTROL_START,
                'stop' => Permission::ACTION_CONTROL_STOP,
                'restart' => Permission::ACTION_CONTROL_RESTART,
            ];

            if (!$request->user()->can($permissionMap[$action], $server)) {
                throw new AccessDeniedHttpException('You do not have permission to perform this TS3 power action.');
            }

            $this->powerRepository->setServer($server)->send($action);
            Activity::event("server:ts3.power.{$action}")->log();

            return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
        }

        if ($action === 'reinstall') {
            if (!$request->user()->can(Permission::ACTION_SETTINGS_REINSTALL, $server)) {
                throw new AccessDeniedHttpException('You do not have permission to reinstall this TS3 server.');
            }

            $this->reinstallServerService->handle($server);
            Activity::event('server:ts3.reinstall')->log();

            return new JsonResponse([], JsonResponse::HTTP_ACCEPTED);
        }

        throw new NotFoundHttpException('Unknown TS3 action.');
    }

    public function executeQuery(Request $request, Server $server): array
    {
        $this->assertTs3($server);
        $this->assertRootAdmin($request);

        $data = $request->validate([
            'command' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->ts3QueryService->executeCustomCommand($server, $data['command']);

        Activity::event('server:ts3.query.execute')
            ->property(['command' => $data['command']])
            ->log();

        return [
            'object' => 'list',
            'data' => $result,
        ];
    }

    private function assertTs3(Server $server): void
    {
        if (!ServerType::isTs3($server)) {
            throw new AccessDeniedHttpException('This endpoint is only available for TeamSpeak 3 servers.');
        }
    }

    private function assertCanRead(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_ACTIVITY_READ, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to access TS3 data.');
        }
    }

    private function assertCanManage(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_CONTROL_CONSOLE, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to manage TS3.');
        }
    }

    private function assertCanBackupRead(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_READ, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to read TS3 snapshots.');
        }
    }

    private function assertCanBackupCreate(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_CREATE, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to create TS3 snapshots.');
        }
    }

    private function assertCanBackupRestore(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_RESTORE, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to restore TS3 snapshots.');
        }
    }

    private function assertCanBackupDelete(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_BACKUP_DELETE, $server)) {
            throw new AccessDeniedHttpException('You do not have permission to delete TS3 snapshots.');
        }
    }

    private function findSnapshotOrFail(Server $server, string $snapshotUuid): Ts3Snapshot
    {
        $snapshot = Ts3Snapshot::query()
            ->where('server_id', $server->id)
            ->where('uuid', $snapshotUuid)
            ->first();

        if (!$snapshot) {
            throw new NotFoundHttpException('TS3 snapshot not found for this server.');
        }

        return $snapshot;
    }

    private function assertRootAdmin(Request $request): void
    {
        if (!$request->user()->root_admin) {
            throw new AccessDeniedHttpException('Only root administrators can execute TS3 query commands.');
        }
    }
}
