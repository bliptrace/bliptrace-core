<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-project ingest sampling (spec §6.2, #42). `sample_rate` is the fraction of
 * non-failure occurrences kept (0.0–1.0; failures are always kept). The Go ingest
 * API reads this; the web only configures it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->decimal('sample_rate', 4, 3)->default(1.000)->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('sample_rate');
        });
    }
};
