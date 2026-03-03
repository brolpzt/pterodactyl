<?php

namespace Pterodactyl\Services\FastDl;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\FastDlNode;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\FastDlNodeRepositoryInterface;

class FastDlNodeCreationService
{
    /**
     * FastDlNodeCreationService constructor.
     */
    public function __construct(
        protected FastDlNodeRepositoryInterface $repository,
        protected Encrypter $encrypter
    ) {
    }

    /**
     * Create a new FastDL node on the panel.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function handle(array $data): FastDlNode
    {
        $data['uuid'] = Uuid::uuid4()->toString();

        if (!empty($data['password'])) {
            $data['password'] = $this->encrypter->encrypt($data['password']);
        }

        if (!empty($data['private_key'])) {
            $data['private_key'] = $this->encrypter->encrypt($data['private_key']);
        }

        return $this->repository->create($data, true, true);
    }
}
