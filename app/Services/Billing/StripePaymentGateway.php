<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Contracts\Billing\PaymentGatewayInterface;
use Pterodactyl\Models\PaymentIntent;
use Pterodactyl\Models\User;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGatewayInterface
{
    /**
     * @throws \Throwable
     */
    public function createPayment(User $user, float $amount, array $metadata = []): array
    {
        if (!config('billing.stripe_enabled', false)) {
            throw new \RuntimeException('Stripe is not enabled.');
        }

        $secretKey = config('billing.stripe_secret_key');
        if (empty($secretKey)) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        $intent = PaymentIntent::create([
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'external_id' => null,
            'amount' => $amount,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        $stripe = new StripeClient($secretKey);
        $currency = strtolower((string) config('billing.currency', 'USD'));
        $unitAmount = (int) round($amount * 100);
        $baseUrl = rtrim((string) config('app.url'), '/');

        $successUrl = config('billing.stripe_success_url') ?: $baseUrl . '/account/billing?payment=success';
        $cancelUrl = config('billing.stripe_cancel_url') ?: $baseUrl . '/account/billing?payment=cancelled';

        try {
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'customer_email' => $user->email,
                'client_reference_id' => (string) $user->id,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'local_intent_id' => (string) $intent->id,
                    'user_id' => (string) $user->id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'local_intent_id' => (string) $intent->id,
                        'user_id' => (string) $user->id,
                    ],
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $unitAmount,
                        'product_data' => [
                            'name' => sprintf('Wallet Top-up (%s)', strtoupper($currency)),
                            'description' => sprintf('Account credit for %s', $user->username),
                        ],
                    ],
                ]],
            ]);
        } catch (ApiErrorException $exception) {
            $intent->update(['status' => 'failed']);
            throw new \RuntimeException('Failed to create Stripe checkout session: ' . $exception->getMessage(), 0, $exception);
        }

        $intent->update([
            'external_id' => $session->id,
            'metadata' => array_merge($metadata, [
                'stripe_checkout_session_id' => $session->id,
            ]),
        ]);

        return [
            'intent_id' => $intent->id,
            'status' => 'pending',
            'checkout_url' => $session->url,
            'gateway' => 'stripe',
        ];
    }

    public function getIdentifier(): string
    {
        return 'stripe';
    }
}
