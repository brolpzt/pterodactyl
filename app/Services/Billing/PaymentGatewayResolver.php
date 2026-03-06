<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Contracts\Billing\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayResolver
{
    public function __construct(
        private ManualPaymentGateway $manualGateway
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
            'manual' => $this->manualGateway,
            'stripe' => throw new InvalidArgumentException('Stripe is not yet integrated.'),
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

        if (config('billing.manual_enabled', false)) {
            $methods[] = 'manual';
        }

        // Future: stripe, pix

        return $methods;
    }
}
