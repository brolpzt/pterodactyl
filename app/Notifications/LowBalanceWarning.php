<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\User;

class LowBalanceWarning extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{name: string, billing_type: string, amount: float}> $services
     */
    public function __construct(
        public float $balance,
        public float $requiredMinimum,
        public float $deficit,
        public array $services = [],
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
        $mail = (new MailMessage())
            ->subject('Low balance warning: action required')
            ->greeting('Hello ' . $notifiable->username . ',')
            ->line('Your wallet balance is below the recommended minimum for your active services.')
            ->line(sprintf('Current balance: USD %.2f', $this->balance))
            ->line(sprintf('Required minimum: USD %.2f', $this->requiredMinimum))
            ->line(sprintf('Amount needed now: USD %.2f', $this->deficit));

        if (!empty($this->services)) {
            $mail->line('Services at risk:');
            foreach (array_slice($this->services, 0, 5) as $service) {
                $mail->line(sprintf(
                    '- %s (%s): USD %.2f',
                    $service['name'],
                    $service['billing_type'],
                    $service['amount']
                ));
            }
        }

        return $mail
            ->action('Add Funds', route('index') . '/account/billing')
            ->line('Please add funds to keep your servers online and avoid suspension.');
    }

    /**
     * Get the array representation for database storage.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_balance_warning',
            'balance' => round($this->balance, 2),
            'required_minimum' => round($this->requiredMinimum, 2),
            'deficit' => round($this->deficit, 2),
            'services' => array_slice($this->services, 0, 10),
        ];
    }
}
