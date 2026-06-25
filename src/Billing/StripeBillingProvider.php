<?php

declare(strict_types=1);

namespace G8Scope\Core\Billing;

use G8Scope\Core\Actions\GrantCapability;
use G8Scope\Core\Contracts\BillingProvider;
use G8Scope\Core\Models\BillingEvent;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe billing driver (spec §12–13, #82). One-time, permanent capability unlock
 * via Stripe Checkout in `mode=payment` — no subscriptions. International-first:
 * currency, locale and tax follow the Stripe Price + Checkout session, so nothing
 * here is region-specific. A successful, verified webhook grants the capability.
 */
class StripeBillingProvider implements BillingProvider
{
    /**
     * @param  array<string, string|null>  $prices  capability => Stripe one-time Price ID
     */
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly array $prices,
        private readonly ?string $webhookSecret,
        private readonly ?string $successUrl,
        private readonly ?string $cancelUrl,
        private readonly GrantCapability $grant,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config, GrantCapability $grant): self
    {
        /** @var array<string, mixed> $stripe */
        $stripe = (array) ($config['stripe'] ?? []);

        return new self(
            new StripeClient((string) ($stripe['secret'] ?? '')),
            (array) ($stripe['prices'] ?? []),
            isset($stripe['webhook_secret']) ? (string) $stripe['webhook_secret'] : null,
            isset($config['success_url']) ? (string) $config['success_url'] : null,
            isset($config['cancel_url']) ? (string) $config['cancel_url'] : null,
            $grant,
        );
    }

    public function checkoutUrl(int $accountId, string $capability): ?string
    {
        $price = $this->prices[$capability] ?? null;

        // No price configured for this capability → manual unlock (no online checkout).
        if (! is_string($price) || $price === '') {
            return null;
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'success_url' => (string) ($this->successUrl ?: ''),
            'cancel_url' => (string) ($this->cancelUrl ?: ''),
            'client_reference_id' => (string) $accountId,
            'metadata' => [
                'account_id' => (string) $accountId,
                'capability' => $capability,
            ],
            // Mirror onto the PaymentIntent so the metadata survives async events too.
            'payment_intent_data' => [
                'metadata' => [
                    'account_id' => (string) $accountId,
                    'capability' => $capability,
                ],
            ],
        ]);

        return $session->url;
    }

    /**
     * Verify a Stripe webhook signature and, for a paid checkout, grant the
     * capability exactly once. Returns true when a grant was applied.
     *
     * @throws SignatureVerificationException on a bad signature
     * @throws \UnexpectedValueException on a malformed payload
     */
    public function handleWebhook(string $payload, string $signature): bool
    {
        $event = Webhook::constructEvent($payload, $signature, (string) $this->webhookSecret);

        if ($event->type !== 'checkout.session.completed') {
            return false;
        }

        /** @var Session $session */
        $session = $event->data->object;

        if (($session->payment_status ?? null) !== 'paid') {
            return false;
        }

        $accountId = (int) ($session->metadata->account_id ?? 0);
        $capability = (string) ($session->metadata->capability ?? '');

        if ($accountId <= 0 || ! in_array($capability, ['custom_api', 'custom_mcp', 'seats'], true)) {
            return false;
        }

        // Idempotency: the (provider, event_id) pair is unique. If this event was
        // already recorded (Stripe redelivery), skip the grant.
        $ledger = BillingEvent::firstOrCreate(
            ['provider' => 'stripe', 'event_id' => $event->id],
            [
                'account_id' => $accountId,
                'capability' => $capability,
                'quantity' => 1,
                'amount' => $session->amount_total,
                'currency' => $session->currency ? strtoupper((string) $session->currency) : null,
            ],
        );

        if (! $ledger->wasRecentlyCreated) {
            return false;
        }

        ($this->grant)($accountId, $capability);

        $ledger->forceFill(['processed_at' => now()])->save();

        return true;
    }
}
