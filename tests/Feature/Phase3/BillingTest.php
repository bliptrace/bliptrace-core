<?php

declare(strict_types=1);

use G8Scope\Core\Actions\GrantCapability;
use G8Scope\Core\Billing\StripeBillingProvider;
use G8Scope\Core\Contracts\EntitlementGate;
use G8Scope\Core\Models\Account;
use G8Scope\Core\Models\BillingEvent;
use G8Scope\Core\Models\Entitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

const WEBHOOK_SECRET = 'whsec_test_secret';

function billingAccount(): Account
{
    return Account::create(['name' => 'DevHub']);
}

function stripeProvider(array $prices = []): StripeBillingProvider
{
    return new StripeBillingProvider(
        new StripeClient('sk_test_dummy'),
        $prices,
        WEBHOOK_SECRET,
        'https://app.test/plan?checkout=success',
        'https://app.test/plan?checkout=cancelled',
        app(GrantCapability::class),
    );
}

/** Build a checkout.session.completed payload + a valid Stripe signature header. */
function signedCheckout(int $accountId, string $capability, string $eventId = 'evt_1', string $paymentStatus = 'paid', int $quantity = 1): array
{
    $payload = json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'object' => 'checkout.session',
            'payment_status' => $paymentStatus,
            'amount_total' => 9900,
            'currency' => 'usd',
            'metadata' => ['account_id' => (string) $accountId, 'capability' => $capability, 'quantity' => (string) $quantity],
        ]],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", WEBHOOK_SECRET);

    return [$payload, "t={$timestamp},v1={$signature}"];
}

// --- GrantCapability action ---------------------------------------------------

it('unlocks the entitlement and enables the capability', function (): void {
    $account = billingAccount();

    app(GrantCapability::class)($account->id, 'custom_api');

    $entitlement = Entitlement::where('account_id', $account->id)->firstOrFail();
    expect($entitlement->unlocked())->toBeTrue()
        ->and($entitlement->custom_api)->toBeTrue()
        ->and($entitlement->custom_mcp)->toBeFalse();
});

it('is idempotent for boolean capabilities', function (): void {
    $account = billingAccount();
    $grant = app(GrantCapability::class);

    $first = $grant($account->id, 'custom_mcp')->granted_at;
    $grant($account->id, 'custom_mcp');

    $entitlement = Entitlement::where('account_id', $account->id)->firstOrFail();
    expect($entitlement->custom_mcp)->toBeTrue()
        ->and($entitlement->granted_at->equalTo($first))->toBeTrue();
});

it('adds a seat when the seats capability is purchased', function (): void {
    $account = billingAccount();
    $account->entitlement()->create(['seats' => 1]);

    app(GrantCapability::class)($account->id, 'seats');

    expect(Entitlement::where('account_id', $account->id)->value('seats'))->toBe(2);
});

// --- StripeBillingProvider webhook -------------------------------------------

it('grants a capability from a verified checkout webhook', function (): void {
    $account = billingAccount();
    [$payload, $signature] = signedCheckout($account->id, 'custom_api');

    $granted = stripeProvider()->handleWebhook($payload, $signature);

    expect($granted)->toBeTrue()
        ->and(app(EntitlementGate::class)->allows($account->id, 'custom_api'))->toBeTrue()
        ->and(BillingEvent::where('event_id', 'evt_1')->whereNotNull('processed_at')->exists())->toBeTrue();
});

it('does not double-grant on webhook replay', function (): void {
    $account = billingAccount();
    $account->entitlement()->create(['seats' => 1]);
    [$payload, $signature] = signedCheckout($account->id, 'seats', 'evt_seat');
    $provider = stripeProvider();

    expect($provider->handleWebhook($payload, $signature))->toBeTrue()
        ->and($provider->handleWebhook($payload, $signature))->toBeFalse()
        ->and(Entitlement::where('account_id', $account->id)->value('seats'))->toBe(2)
        ->and(BillingEvent::where('event_id', 'evt_seat')->count())->toBe(1);
});

it('grants multiple seats from a bulk seat purchase', function (): void {
    $account = billingAccount();
    $account->entitlement()->create(['seats' => 3]);
    [$payload, $signature] = signedCheckout($account->id, 'seats', 'evt_bulk', 'paid', 5);

    expect(stripeProvider()->handleWebhook($payload, $signature))->toBeTrue()
        ->and(Entitlement::where('account_id', $account->id)->value('seats'))->toBe(8) // 3 + 5
        ->and(BillingEvent::where('event_id', 'evt_bulk')->value('quantity'))->toBe(5);
});

it('rejects a webhook with an invalid signature', function (): void {
    $account = billingAccount();
    [$payload] = signedCheckout($account->id, 'custom_api');

    expect(fn () => stripeProvider()->handleWebhook($payload, 't=1,v1=deadbeef'))
        ->toThrow(SignatureVerificationException::class);
});

it('ignores an unpaid checkout session', function (): void {
    $account = billingAccount();
    [$payload, $signature] = signedCheckout($account->id, 'custom_api', 'evt_unpaid', 'unpaid');

    expect(stripeProvider()->handleWebhook($payload, $signature))->toBeFalse()
        ->and(Entitlement::where('account_id', $account->id)->exists())->toBeFalse();
});

it('returns null checkout url when no price is configured', function (): void {
    expect(stripeProvider([])->checkoutUrl(1, 'custom_api'))->toBeNull();
});

// --- Seat subscriptions (ADR-002) --------------------------------------------

/** Build a customer.subscription.deleted payload + a valid Stripe signature header. */
function signedSubscriptionDeleted(int $accountId, int $quantity, string $eventId = 'evt_sub_del', string $capability = 'seats'): array
{
    $payload = json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'object' => 'subscription',
            'status' => 'canceled',
            'metadata' => ['account_id' => (string) $accountId, 'capability' => $capability, 'quantity' => (string) $quantity],
        ]],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", WEBHOOK_SECRET);

    return [$payload, "t={$timestamp},v1={$signature}"];
}

it('sells seats as a subscription and unlocks as a one-time payment', function (): void {
    $provider = stripeProvider();

    $seats = $provider->sessionParams(7, 'seats', 3, 'price_seat');
    $unlock = $provider->sessionParams(7, 'custom_api', 1, 'price_api');

    expect($seats['mode'])->toBe('subscription')
        ->and($seats['subscription_data']['metadata'])->toBe(['account_id' => '7', 'capability' => 'seats', 'quantity' => '3'])
        ->and($seats)->not->toHaveKey('payment_intent_data')
        ->and($unlock['mode'])->toBe('payment')
        ->and($unlock['payment_intent_data']['metadata']['capability'])->toBe('custom_api')
        ->and($unlock)->not->toHaveKey('subscription_data');
});

it('revokes seats once when the seat subscription is deleted', function (): void {
    $account = billingAccount();
    $account->entitlement()->create(['seats' => 6]);
    [$payload, $signature] = signedSubscriptionDeleted($account->id, 4);
    $provider = stripeProvider();

    expect($provider->handleWebhook($payload, $signature))->toBeTrue()
        ->and($provider->handleWebhook($payload, $signature))->toBeFalse()
        ->and(Entitlement::where('account_id', $account->id)->value('seats'))->toBe(2)
        ->and(BillingEvent::where('event_id', 'evt_sub_del')->whereNotNull('processed_at')->exists())->toBeTrue();
});

it('never revokes the owner seat', function (): void {
    $account = billingAccount();
    $account->entitlement()->create(['seats' => 2]);
    [$payload, $signature] = signedSubscriptionDeleted($account->id, 5, 'evt_floor');

    stripeProvider()->handleWebhook($payload, $signature);

    expect(Entitlement::where('account_id', $account->id)->value('seats'))->toBe(1);
});

it('ignores a deleted subscription that is not for seats', function (): void {
    $account = billingAccount();
    $account->entitlement()->create(['seats' => 3]);
    [$payload, $signature] = signedSubscriptionDeleted($account->id, 1, 'evt_other', 'custom_api');

    expect(stripeProvider()->handleWebhook($payload, $signature))->toBeFalse()
        ->and(Entitlement::where('account_id', $account->id)->value('seats'))->toBe(3);
});
