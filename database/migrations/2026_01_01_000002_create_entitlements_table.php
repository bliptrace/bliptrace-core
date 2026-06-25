<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('custom_api')->default(false);
            $table->boolean('custom_mcp')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->unsignedInteger('seats')->default(1);
            $table->unsignedInteger('max_projects')->nullable(); // null => unlimited
            $table->unsignedInteger('retention_days')->default(14);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
