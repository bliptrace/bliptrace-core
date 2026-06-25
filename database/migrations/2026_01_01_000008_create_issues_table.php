<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint');
            $table->string('type');
            $table->text('message')->nullable();
            $table->string('culprit')->nullable();
            $table->string('level')->default('error');
            $table->string('status')->default('open'); // IssueStatus
            $table->string('environment')->nullable();
            $table->string('release')->nullable();
            $table->unsignedBigInteger('assignee_user_id')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('occurrences_count')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'fingerprint']);
            $table->index(['project_id', 'status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
