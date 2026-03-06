<?php

namespace Pterodactyl\Contracts\Billing;

use Pterodactyl\Models\User;

interface PaymentGatewayInterface
{
    /**
     * Create a payment intent. For manual gateway, this may complete immediately.
     * For Stripe/Pix, this returns data needed for the frontend to complete payment.
     *
     * @return array{intent_id: int, status: string, client_secret?: string, ...}
     */
    public function createPayment(User $user, float $amount, array $metadata = []): array;

    /**
     * Get the gateway identifier (manual, stripe, pix).
     */
    public function getIdentifier(): string;
}
