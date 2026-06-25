<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Custom business metrics (spec §8, #34). The Eloquent MetricStore driver backs dev/tests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_points', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->index();
            $table->foreignId('project_id')->index();
            $table->string('name')->index();
            $table->double('value');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_points');
    }
};
