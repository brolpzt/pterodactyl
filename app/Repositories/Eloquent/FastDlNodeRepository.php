<?php

namespace Pterodactyl\Repositories\Eloquent;

use Pterodactyl\Models\FastDlNode;
use Pterodactyl\Contracts\Repository\FastDlNodeRepositoryInterface;

class FastDlNodeRepository extends EloquentRepository implements FastDlNodeRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return FastDlNode::class;
    }
}
