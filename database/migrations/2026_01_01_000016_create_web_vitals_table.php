<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** RUM Web-Vitals beacons (spec §8, #36). The Eloquent RumStore driver backs dev/tests. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_vitals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->index();
            $table->foreignId('project_id')->index();
            $table->string('metric', 8)->index();  // lcp | inp | cls | fcp | ttfb
            $table->double('value');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_vitals');
    }
};
