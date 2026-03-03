<?php

namespace Pterodactyl\Services\FastDl;

use Pterodactyl\Models\FastDlNode;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\FastDlNodeRepositoryInterface;

class FastDlNodeUpdateService
{
    /**
     * FastDlNodeUpdateService constructor.
     */
    public function __construct(
        protected FastDlNodeRepositoryInterface $repository,
        protected Encrypter $encrypter
    ) {
    }

    /**
     * Update a FastDL node on the panel.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function handle(FastDlNode $node, array $data): FastDlNode
    {
        if (!empty($data['password'])) {
            $data['password'] = $this->encrypter->encrypt($data['password']);
        } else {
            unset($data['password']);
        }

        if (!empty($data['private_key'])) {
            $data['private_key'] = $this->encrypter->encrypt($data['private_key']);
        } else {
            unset($data['private_key']);
        }

        return $this->repository->update($node->id, $data, true, true);
    }
}
