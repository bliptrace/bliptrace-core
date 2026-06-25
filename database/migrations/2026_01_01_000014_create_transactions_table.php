<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** APM transaction timings (spec §8, #35). The Eloquent ApmStore driver backs dev/tests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->index();
            $table->foreignId('project_id')->index();
            $table->string('name');                 // route / job / command name
            $table->string('type')->default('http'); // http | queue | command
            $table->unsignedInteger('duration_ms');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
