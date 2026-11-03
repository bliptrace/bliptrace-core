<?php

declare(strict_types=1);

use G8Scope\Core\Actions\MaintainOccurrencePartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Occurrences — append-only, one issue groups thousands. Shared contract with the Go
 * api (writes) and mcp (reads): core owns this shape, do not diverge without
 * re-pinning them. Postgres: range-partitioned monthly on occurred_at; retention
 * drops whole partitions (g8scope:occurrences:maintain).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // Non-Postgres (the app's sqlite test suite): same columns, no partitioning.
            Schema::create('occurrences', function (Blueprint $table): void {
                $table->uuid('event_id');
                $table->unsignedBigInteger('project_id');
                $table->uuid('issue_uuid');
                $table->text('fingerprint');
                $table->text('level');
                $table->text('environment')->default('');
                $table->text('release')->default('');
                $table->text('message')->default('');
                $table->timestampTz('occurred_at');
                $table->jsonb('payload');
                $table->timestampTz('received_at')->useCurrent();
                $table->primary(['project_id', 'occurred_at', 'event_id']);
                $table->index(['project_id', 'issue_uuid', 'occurred_at']);
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE occurrences (
                event_id     uuid        NOT NULL,
                project_id   bigint      NOT NULL,
                issue_uuid   uuid        NOT NULL,
                fingerprint  text        NOT NULL,
                level        text        NOT NULL,
                environment  text        NOT NULL DEFAULT '',
                release      text        NOT NULL DEFAULT '',
                message      text        NOT NULL DEFAULT '',
                occurred_at  timestamptz NOT NULL,
                payload      jsonb       NOT NULL,
                received_at  timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (project_id, occurred_at, event_id)
            ) PARTITION BY RANGE (occurred_at)
        SQL);
        DB::statement('CREATE INDEX occurrences_project_issue_occurred_idx ON occurrences (project_id, issue_uuid, occurred_at DESC)');
        DB::statement('CREATE TABLE occurrences_default PARTITION OF occurrences DEFAULT');

        app(MaintainOccurrencePartitions::class)->ensurePartitions();
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrences'); // drops every partition with it
    }
};
