<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integration API v1 (docs 04-integrations/01): projects gain an integrator
 * `external_ref` (unique per account among live projects), a default
 * `environment` and soft-delete archiving; issues record when they last
 * regressed; deploy markers get their own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('external_ref')->nullable();
            $table->string('environment')->nullable();
            $table->softDeletes();
        });

        // Partial unique index (Postgres and SQLite both support it): an archived
        // project frees its external_ref so the integrator can re-provision.
        DB::statement('CREATE UNIQUE INDEX projects_account_external_ref_unique ON projects (account_id, external_ref) WHERE external_ref IS NOT NULL AND deleted_at IS NULL');

        Schema::table('issues', function (Blueprint $table): void {
            $table->timestamp('regressed_at')->nullable();
        });

        Schema::create('deploys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('release');
            $table->string('environment')->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->string('url', 2048)->nullable();
            $table->timestamp('deployed_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'deployed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploys');

        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('regressed_at');
        });

        DB::statement('DROP INDEX IF EXISTS projects_account_external_ref_unique');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['external_ref', 'environment']);
        });
    }
};
