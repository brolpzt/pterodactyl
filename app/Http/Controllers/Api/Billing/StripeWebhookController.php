<?php

namespace Pterodactyl\Http\Controllers\Api\Billing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\PaymentIntent;
use Pterodactyl\Services\Billing\BillingChargeService;
use Pterodactyl\Services\Billing\WalletService;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private BillingChargeService $billingChargeService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $webhookSecret = config('billing.stripe_webhook_secret');
        if (empty($webhookSecret)) {
            return new JsonResponse(['error' => 'Stripe webhook secret is not configured.'], 500);
        }

        $signature = (string) $request->header('Stripe-Signature');
        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationException $exception) {
            return new JsonResponse(['error' => 'Invalid Stripe signature.'], 400);
        } catch (\UnexpectedValueException $exception) {
            return new JsonResponse(['error' => 'Invalid Stripe payload.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $localIntentId = $session->metadata->local_intent_id ?? null;

            if (!$localIntentId) {
                return new JsonResponse(['ok' => true]);
            }

            DB::transaction(function () use ($localIntentId, $session): void {
                $intent = PaymentIntent::query()
                    ->where('id', (int) $localIntentId)
                    ->where('gateway', 'stripe')
                    ->lockForUpdate()
                    ->first();

                if (!$intent || $intent->status === 'completed') {
                    return;
                }

                $amount = isset($session->amount_total)
                    ? ((float) $session->amount_total / 100)
                    : (float) $intent->amount;

                $user = $intent->user;
                if (!$user) {
                    $intent->update(['status' => 'failed']);
                    return;
                }

                $this->walletService->deposit(
                    $user,
                    $amount,
                    'Stripe deposit',
                    [
                        'payment_intent_id' => $intent->id,
                        'stripe_checkout_session_id' => $session->id,
                    ]
                );

                $intent->update([
                    'status' => 'completed',
                    'external_id' => $session->id,
                    'completed_at' => now(),
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'stripe_checkout_session_id' => $session->id,
                    ]),
                ]);

                $this->billingChargeService->restoreBillingSuspendedServers($user);
            });
        }

        return new JsonResponse(['ok' => true]);
    }
}
