<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Pterodactyl\Models\Ticket;

class AdminSidebarComposer
{
    /**
     * Share open tickets count with admin sidebar layout.
     */
    public function compose(View $view): void
    {
        $view->with('openTicketsCount', Ticket::query()->where('status', 'open')->count());
    }
}
