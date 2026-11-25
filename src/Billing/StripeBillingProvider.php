<?php

declare(strict_types=1);

namespace G8Scope\Core\Billing;

use G8Scope\Core\Actions\GrantCapability;
use G8Scope\Core\Actions\RevokeSeats;
use G8Scope\Core\Contracts\BillingProvider;
use G8Scope\Core\Models\BillingEvent;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

/**
 * Stripe billing driver (spec §12–13, #82, ADR-002). Stripe is the only gateway and
 * prices are in USD. Endpoint unlocks are one-time, permanent Checkout purchases
 * (`mode=payment`); seats are a recurring Stripe subscription (`mode=subscription`,
 * no Cashier). Currency and tax follow the Stripe Price + Checkout session. A paid,
 * verified checkout grants the capability; a deleted seat subscription revokes it.
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
        private readonly bool $taxEnabled = false,
        private readonly RevokeSeats $revokeSeats = new RevokeSeats,
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
            (bool) ($config['tax_enabled'] ?? false),
        );
    }

    public function checkoutUrl(int $accountId, string $capability, int $quantity = 1): ?string
    {
        $price = $this->prices[$capability] ?? null;

        // No price configured for this capability → manual unlock (no online checkout).
        if (! is_string($price) || $price === '') {
            return null;
        }

        // Quantity only applies to quantifiable capabilities (seats); endpoint
        // unlocks are always a single purchase.
        $quantity = $capability === 'seats' ? max(1, $quantity) : 1;

        return $this->stripe->checkout->sessions->create($this->sessionParams($accountId, $capability, $quantity, $price))->url;
    }

    /**
     * Checkout session parameters. Seats need a recurring Stripe Price and run as a
     * subscription; endpoint unlocks need a one-time Price and run as a payment.
     *
     * @return array<string, mixed>
     */
    public function sessionParams(int $accountId, string $capability, int $quantity, string $price): array
    {
        $metadata = [
            'account_id' => (string) $accountId,
            'capability' => $capability,
            'quantity' => (string) $quantity,
        ];
        $subscription = $capability === 'seats';

        $params = [
            'mode' => $subscription ? 'subscription' : 'payment',
            'line_items' => [['price' => $price, 'quantity' => $quantity]],
            'success_url' => (string) ($this->successUrl ?: ''),
            'cancel_url' => (string) ($this->cancelUrl ?: ''),
            'client_reference_id' => (string) $accountId,
            'metadata' => $metadata,
        ];

        // Mirror the metadata onto the object Stripe keeps, so later events carry it:
        // the Subscription (needed to revoke seats on cancel) or the PaymentIntent.
        $params[$subscription ? 'subscription_data' : 'payment_intent_data'] = ['metadata' => $metadata];

        if ($this->taxEnabled) {
            $params['automatic_tax'] = ['enabled' => true];
        }

        return $params;
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

        if ($event->type === 'customer.subscription.deleted') {
            return $this->revokeSeatsFor($event);
        }

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
        $quantity = max(1, (int) ($session->metadata->quantity ?? 1));

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
                'quantity' => $quantity,
                'amount' => $session->amount_total,
                'currency' => $session->currency ? strtoupper((string) $session->currency) : null,
            ],
        );

        if (! $ledger->wasRecentlyCreated) {
            return false;
        }

        ($this->grant)($accountId, $capability, $quantity);

        $ledger->forceFill(['processed_at' => now()])->save();

        return true;
    }

    /**
     * A seat subscription ended (cancelled, or unpaid after Stripe's retries):
     * take its seats back exactly once. Returns true when seats were revoked.
     */
    private function revokeSeatsFor(Event $event): bool
    {
        /** @var Subscription $subscription */
        $subscription = $event->data->object;

        $accountId = (int) ($subscription->metadata->account_id ?? 0);
        $quantity = max(1, (int) ($subscription->metadata->quantity ?? 1));

        if ($accountId <= 0 || ($subscription->metadata->capability ?? null) !== 'seats') {
            return false;
        }

        $ledger = BillingEvent::firstOrCreate(
            ['provider' => 'stripe', 'event_id' => $event->id],
            ['account_id' => $accountId, 'capability' => 'seats', 'quantity' => $quantity],
        );

        if (! $ledger->wasRecentlyCreated) {
            return false;
        }

        ($this->revokeSeats)($accountId, $quantity);

        $ledger->forceFill(['processed_at' => now()])->save();

        return true;
    }
}
