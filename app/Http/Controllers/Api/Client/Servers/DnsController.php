<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\CloudflareDnsRecord;
use Pterodactyl\Services\Cloudflare\CloudflareDnsService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Transformers\Api\Client\CloudflareDnsRecordTransformer;
use Pterodactyl\Http\Requests\Api\Client\Servers\Dns\GetDnsRecordsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Dns\StoreDnsRecordRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Dns\DeleteDnsRecordRequest;

class DnsController extends ClientApiController
{
    public function __construct(
        private CloudflareDnsService $dnsService,
    ) {
        parent::__construct();
    }

    /**
     * @throws DisplayException
     */
    public function index(GetDnsRecordsRequest $request, Server $server): array
    {
        $result = $this->dnsService->listForServer($server);

        return $this->fractal->collection($result['records'])
            ->transformWith($this->getTransformer(CloudflareDnsRecordTransformer::class))
            ->addMeta([
                'dns' => $result['meta'],
            ])
            ->toArray();
    }

    /**
     * @throws DisplayException
     */
    public function store(StoreDnsRecordRequest $request, Server $server): array
    {
        $record = $this->dnsService->createForServer($server, $request->user(), $request->validated());

        return $this->fractal->item($record->load('zone'))
            ->transformWith($this->getTransformer(CloudflareDnsRecordTransformer::class))
            ->toArray();
    }

    /**
     * @throws DisplayException
     */
    public function delete(DeleteDnsRecordRequest $request, Server $server, CloudflareDnsRecord $dnsRecord): JsonResponse
    {
        $this->dnsService->deleteForServer($server, $dnsRecord);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
