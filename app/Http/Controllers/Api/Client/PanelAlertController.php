<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Pterodactyl\Services\PanelAlerts\PanelAlertResolver;
use Pterodactyl\Transformers\Api\Client\PanelAlertTransformer;

class PanelAlertController extends ClientApiController
{
    public function __construct(private PanelAlertResolver $resolver)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        return $this->transform($this->resolver->forAccount($request->user()));
    }

    public function server(Request $request, Server $server): array
    {
        return $this->transform($this->resolver->forServer($request->user(), $server));
    }

    private function transform(Collection $alerts): array
    {
        return $this->fractal->collection($alerts)
            ->transformWith($this->getTransformer(PanelAlertTransformer::class))
            ->toArray();
    }
}
