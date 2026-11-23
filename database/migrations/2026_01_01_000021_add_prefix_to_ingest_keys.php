<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingest keys remember their own display prefix. New keys are issued as `blip_`;
 * keys issued before 2026-11-23 were `g8s_` and keep working (the ingest API
 * looks keys up by hash only), so existing rows backfill to `g8s_` via the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_keys', function (Blueprint $table): void {
            $table->string('prefix', 16)->default('g8s_');
        });
    }

    public function down(): void
    {
        Schema::table('ingest_keys', function (Blueprint $table): void {
            $table->dropColumn('prefix');
        });
    }
};
