<?php

namespace Pterodactyl\Services\Eggs;

use Pterodactyl\Models\Egg;
use Pterodactyl\Contracts\Repository\EggRepositoryInterface;
use Pterodactyl\Exceptions\Service\Egg\NoParentConfigurationFoundException;

class EggUpdateService
{
    /**
     * EggUpdateService constructor.
     */
    public function __construct(
        protected EggRepositoryInterface $repository,
        protected PortSlotSyncService $portSlotSync,
    ) {
    }

    /**
     * Update a service option.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws NoParentConfigurationFoundException
     */
    public function handle(Egg $egg, array $data): void
    {
        if (!is_null(array_get($data, 'config_from'))) {
            $results = $this->repository->findCountWhere([
                ['nest_id', '=', $egg->nest_id],
                ['id', '=', array_get($data, 'config_from')],
            ]);

            if ($results !== 1) {
                throw new NoParentConfigurationFoundException(trans('exceptions.nest.egg.must_be_child'));
            }
        }

        // TODO(dane): Once the admin UI is done being reworked and this is exposed
        //  in said UI, remove this so that you can actually update the denylist.
        unset($data['file_denylist']);

        $previousSlots = $egg->port_slots;

        $this->repository->withoutFreshModel()->update($egg->id, $data);

        if (array_key_exists('port_slots', $data)) {
            $egg->refresh();
            $this->portSlotSync->syncEggVariables($egg, is_array($previousSlots) ? $previousSlots : []);
        }
    }
}
