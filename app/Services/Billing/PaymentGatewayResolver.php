<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Contracts\Billing\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    public function __construct(
        private StripePaymentGateway $stripeGateway,
    ) {
    }

    /**
     * Resolve gateway by method identifier.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $method): PaymentGatewayInterface
    {
        return match (strtolower($method)) {
            'stripe' => $this->stripeGateway,
            'pix' => throw new InvalidArgumentException('Pix is not yet integrated.'),
            default => throw new InvalidArgumentException("Unknown payment method: {$method}"),
        };
    }

    /**
     * Get list of available payment methods.
     */
    public function getAvailableMethods(): array
    {
        $methods = [];

        if (config('billing.stripe_enabled', false)) {
            $methods[] = 'stripe';
        }

        // Future: pix

        return $methods;
    }
}
