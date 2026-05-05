<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\User;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $currency = 'USD',
        public string $gateway = 'stripe',
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
        $formattedAmount = number_format($this->amount, 2, '.', ',');
        $currency = strtoupper($this->currency);
        $gateway = strtoupper($this->gateway);

        return (new MailMessage())
            ->subject(sprintf('Payment received: %s %s', $currency, $formattedAmount))
            ->greeting('Hello ' . $notifiable->username . ',')
            ->line('We have successfully received your payment.')
            ->line(sprintf('Amount: %s %s', $currency, $formattedAmount))
            ->line(sprintf('Method: %s', $gateway))
            ->action('View Billing', route('index') . '/account/billing')
            ->line('Thank you for your payment.');
    }
}
