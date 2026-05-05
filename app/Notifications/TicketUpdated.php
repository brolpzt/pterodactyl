<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketMessage;
use Pterodactyl\Models\User;

class TicketUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $eventType,
        public ?TicketMessage $message = null,
        public ?User $actor = null,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $isAdmin = (bool) $notifiable->root_admin;
        $eventText = $this->eventLabel();
        $panelBase = rtrim(route('index'), '/');
        $subjectPrefix = $isAdmin ? 'ADMIN: ' : '';
        $destination = $isAdmin
            ? route('admin.tickets.view', $this->ticket->id)
            : $panelBase . '/account/support/' . $this->ticket->id;

        $mail = (new MailMessage())
            ->subject(sprintf('%s[Ticket #%d] %s', $subjectPrefix, $this->ticket->id, $eventText))
            ->greeting('Hello ' . $notifiable->username . ',')
            ->line('There is an update on ticket #' . $this->ticket->id . '.')
            ->line('Subject: ' . $this->ticket->subject)
            ->line('Status: ' . $this->ticket->status)
            ->line('Update type: ' . $eventText);

        if ($this->actor instanceof User) {
            $mail->line('Updated by: ' . $this->actor->username . ' (' . $this->actor->email . ')');
        }

        if ($this->message instanceof TicketMessage) {
            $mail->line('Message: ' . mb_strimwidth($this->message->message, 0, 240, '...'));
        }

        return $mail
            ->action('View Ticket', $destination)
            ->line('This is an automated notification from your panel.');
    }

    private function eventLabel(): string
    {
        return match ($this->eventType) {
            'created' => 'New Ticket Created',
            'reply' => 'New Reply Added',
            'status_changed' => 'Ticket Status Changed',
            default => 'Ticket Updated',
        };
    }
}
