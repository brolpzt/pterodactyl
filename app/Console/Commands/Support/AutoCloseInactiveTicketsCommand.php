<?php

namespace Pterodactyl\Console\Commands\Support;

use Illuminate\Console\Command;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\User;
use Pterodactyl\Notifications\TicketUpdated;

class AutoCloseInactiveTicketsCommand extends Command
{
    protected $signature = 'p:support:auto-close-inactive-tickets';

    protected $description = 'Automatically close open tickets when the last admin reply is older than 24 hours and the client has not replied.';

    public function handle(): int
    {
        $threshold = now()->subDay();

        $tickets = Ticket::query()
            ->where('status', 'open')
            ->whereRelation('user', 'root_admin', false)
            ->whereHas('latestMessage', fn ($query) => $query->where('created_at', '<=', $threshold))
            ->whereHas('latestMessage.user', fn ($query) => $query->where('root_admin', true))
            ->with(['latestMessage', 'user'])
            ->get();

        if ($tickets->isEmpty()) {
            $this->line('No inactive support tickets found for auto-close.');

            return 0;
        }

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'closed']);

            User::query()
                ->where('root_admin', true)
                ->orWhere('id', $ticket->user_id)
                ->get()
                ->unique('id')
                ->each(fn (User $recipient) => $recipient->notify(new TicketUpdated($ticket, 'status_changed')));

            $this->line("Auto-closed ticket #{$ticket->id}.");
        }

        $this->info(sprintf('Auto-closed %d inactive ticket(s).', $tickets->count()));

        return 0;
    }
}
