<?php

namespace Pterodactyl\Services\FastDl;

use Pterodactyl\Models\FastDlNode;
use Pterodactyl\Contracts\Repository\FastDlNodeRepositoryInterface;

class FastDlNodeDeletionService
{
    /**
     * FastDlNodeDeletionService constructor.
     */
    public function __construct(protected FastDlNodeRepositoryInterface $repository)
    {
    }

    /**
     * Delete a FastDL node from the panel.
     */
    public function handle(int|FastDlNode $node): void
    {
        $this->repository->delete($node instanceof FastDlNode ? $node->id : $node);
    }
}
