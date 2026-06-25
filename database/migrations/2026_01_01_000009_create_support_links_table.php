<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('reference'); // DESK-YYYY-NNNNN
            $table->string('status')->default('open'); // SupportStatus
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_links');
    }
};
