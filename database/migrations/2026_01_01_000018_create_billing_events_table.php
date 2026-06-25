<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing event ledger (spec §12–13, #82). Records every processed provider event
 * (e.g. Stripe checkout.session.completed) keyed by a unique provider event id, so
 * webhook redelivery never double-grants a capability. Also an audit trail of
 * one-time capability purchases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider')->default('stripe'); // stripe | billplz | …
            $table->string('event_id');                    // provider event id (idempotency key)
            $table->foreignId('account_id')->nullable()->index();
            $table->string('capability')->nullable();      // custom_api | custom_mcp | seats
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('amount')->nullable(); // minor units (e.g. cents)
            $table->string('currency', 3)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
    }
};
