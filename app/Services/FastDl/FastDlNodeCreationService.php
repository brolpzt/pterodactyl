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
        $data = $this->normalizeStorageFields($data);
        $data = $this->encryptSecrets($data);

        return $this->repository->create($data, true, true);
    }

    private function normalizeStorageFields(array $data): array
    {
        $data['storage_type'] = $data['storage_type'] ?? FastDlNode::STORAGE_SSH;
        $data['use_path_style_endpoint'] = (bool) ($data['use_path_style_endpoint'] ?? false);

        if (($data['storage_type'] ?? FastDlNode::STORAGE_SSH) === FastDlNode::STORAGE_S3) {
            $data['port'] = $data['port'] ?? 443;
            $data['region'] = $data['region'] ?? 'auto';
            $data['username'] = null;
            $data['password'] = null;
            $data['private_key'] = null;

            if (!array_key_exists('use_path_style_endpoint', $data)) {
                $data['use_path_style_endpoint'] = true;
            }
        }

        return $data;
    }

    private function encryptSecrets(array $data): array
    {
        foreach (['password', 'private_key', 'access_key', 'secret_key'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->encrypter->encrypt($data[$field]);
            }
        }

        return $data;
    }
}
