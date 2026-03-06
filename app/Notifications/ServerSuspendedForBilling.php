<?php

namespace Pterodactyl\Notifications;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ServerSuspendedForBilling extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Server $server,
        public string $reason = 'Insufficient wallet balance'
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Server suspended: ' . $this->server->name)
            ->greeting('Hello ' . $notifiable->username . ',')
            ->line('Your server **' . $this->server->name . '** has been suspended due to insufficient wallet balance.')
            ->line('Please add funds to your wallet to restore your server.')
            ->action('Add Funds', route('index') . '/account/billing')
            ->line('Thank you for using our service.');
    }

    /**
     * Get the array representation for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'server_suspended_billing',
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'reason' => $this->reason,
        ];
    }
}
