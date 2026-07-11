<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\FirewallRule;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Transformers\Api\Client\FirewallRuleTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Repositories\Wings\DaemonFirewallRepository;
use Pterodactyl\Http\Requests\Api\Client\Servers\Firewall\GetFirewallRulesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Firewall\StoreFirewallRuleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Firewall\DeleteFirewallRuleRequest;

class FirewallController extends ClientApiController
{
    public function __construct(
        private DaemonFirewallRepository $daemonFirewallRepository,
    ) {
        parent::__construct();
    }

    /**
     * Returns all firewall rules (banned IPs) for the server.
     */
    public function index(GetFirewallRulesRequest $request, Server $server): array
    {
        $rules = FirewallRule::where('server_id', $server->id)->orderByDesc('created_at')->get();

        return $this->fractal->collection($rules)
            ->transformWith($this->getTransformer(FirewallRuleTransformer::class))
            ->toArray();
    }

    /**
     * Stores a new firewall rule and instructs Wings to apply iptables.
     *
     * @throws DisplayException
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function store(StoreFirewallRuleRequest $request, Server $server): array
    {
        $ip = $request->input('ip');
        $reason = $request->input('reason') ?? '';

        // Check for duplicate ban
        if (FirewallRule::where('server_id', $server->id)->where('ip', $ip)->exists()) {
            throw new DisplayException('Este IP já está banido neste servidor.');
        }

        // Apply the rule on Wings first — if this fails, we don't save to DB
        $this->daemonFirewallRepository->setServer($server)->addRule($ip, $reason);

        $rule = FirewallRule::create([
            'server_id' => $server->id,
            'ip'        => $ip,
            'reason'    => $reason ?: null,
        ]);

        return $this->fractal->item($rule)
            ->transformWith($this->getTransformer(FirewallRuleTransformer::class))
            ->toArray();
    }

    /**
     * Deletes a firewall rule and instructs Wings to remove the iptables entry.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function delete(DeleteFirewallRuleRequest $request, Server $server, int $ruleId): JsonResponse
    {
        $firewallRule = FirewallRule::query()
            ->where('server_id', $server->id)
            ->where('id', $ruleId)
            ->firstOrFail();

        // Remove from Wings iptables first
        $this->daemonFirewallRepository->setServer($server)->removeRule($firewallRule->ip);

        $firewallRule->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
