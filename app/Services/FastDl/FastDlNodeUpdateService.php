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
        $data = $this->normalizeStorageFields($data);
        $data = $this->encryptSecrets($data);

        return $this->repository->update($node->id, $data, true, true);
    }

    private function normalizeStorageFields(array $data): array
    {
        if (array_key_exists('use_path_style_endpoint', $data)) {
            $data['use_path_style_endpoint'] = (bool) $data['use_path_style_endpoint'];
        }

        if (($data['storage_type'] ?? null) === FastDlNode::STORAGE_S3) {
            $data['username'] = null;
            $data['password'] = null;
            $data['private_key'] = null;
        }

        return $data;
    }

    private function encryptSecrets(array $data): array
    {
        foreach (['password', 'private_key', 'access_key', 'secret_key'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->encrypter->encrypt($data[$field]);
            } else {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
