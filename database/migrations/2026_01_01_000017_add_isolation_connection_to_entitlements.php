<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated own-DB/schema isolation for gov tenants (spec §10, #40). Names the
 * database connection (configured at deploy time in config/database.php) this
 * account's data lives on; null = the shared connection. Gated by the entitlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table): void {
            $table->string('isolation_connection')->nullable()->after('custom_mcp');
        });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table): void {
            $table->dropColumn('isolation_connection');
        });
    }
};
