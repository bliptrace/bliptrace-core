<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use G8Scope\Core\Actions\MaintainOccurrencePartitions;
use G8Scope\Core\Models\Account;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function partitions(): array
{
    return array_column(DB::select(
        "SELECT c.relname FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid
         WHERE i.inhparent = 'occurrences'::regclass ORDER BY c.relname"
    ), 'relname');
}

function occurrenceAt(string $at, int $projectId = 1): void
{
    DB::table('occurrences')->insert([
        'event_id' => (string) Str::uuid(), 'project_id' => $projectId, 'issue_uuid' => (string) Str::uuid(),
        'fingerprint' => 'fp', 'level' => 'error', 'occurred_at' => $at, 'payload' => '{}',
    ]);
}

it('migrates a partitioned table with the current + next two months and a default', function (): void {
    $now = CarbonImmutable::now('UTC')->startOfMonth();

    expect(partitions())->toEqualCanonicalizing([
        'occurrences_default',
        'occurrences_'.$now->format('Y_m'),
        'occurrences_'.$now->addMonth()->format('Y_m'),
        'occurrences_'.$now->addMonths(2)->format('Y_m'),
    ]);
});

it('keeps two months ahead across a rollover and drops partitions past retention', function (): void {
    config(['g8scope.occurrences.retention_days' => 30]);
    $later = CarbonImmutable::now('UTC')->startOfMonth()->addMonths(3)->addDays(14); // mid-month, 3 months on
    $current = $later->startOfMonth();

    $result = app(MaintainOccurrencePartitions::class)($later);

    // Created: up to two months beyond $later's month.
    expect($result['created'])->toContain(
        'occurrences_'.$current->format('Y_m'),
        'occurrences_'.$current->addMonths(2)->format('Y_m'),
    );

    // Cutoff = $later - 30 days, which falls in the previous month: anything ending
    // on/before it goes; the month containing the cutoff stays.
    $cutoff = $later->subDays(30);
    foreach (partitions() as $name) {
        if (preg_match('/_(\d{4})_(\d{2})$/', $name, $m)) {
            $end = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, 'UTC')->addMonth();
            expect($end->greaterThan($cutoff))->toBeTrue("{$name} should have been dropped");
        }
    }
    expect($result['dropped'])->toContain('occurrences_'.CarbonImmutable::now('UTC')->format('Y_m'))
        ->and(partitions())->toContain('occurrences_'.$cutoff->format('Y_m'), 'occurrences_default');
});

it('is idempotent when run twice', function (): void {
    $action = app(MaintainOccurrencePartitions::class);
    $action();

    expect($action()['created'])->toBe([])->and($action()['dropped'])->toBe([]);
});

it('moves default-partition rows into a newly created partition instead of failing', function (): void {
    $far = CarbonImmutable::now('UTC')->startOfMonth()->addMonths(5);
    occurrenceAt($far->addDays(3)->format('Y-m-d H:i:s\Z'));   // lands in default (no partition yet)
    occurrenceAt($far->addMonths(3)->format('Y-m-d H:i:s\Z')); // beyond the 3 created months: stays in default

    $created = app(MaintainOccurrencePartitions::class)->ensurePartitions($far);
    $name = 'occurrences_'.$far->format('Y_m');

    expect($created)->toContain($name)
        ->and(DB::table($name)->count())->toBe(1)
        ->and(DB::table('occurrences_default')->count())->toBe(1) // the out-of-range row stays
        ->and(DB::table('occurrences')->count())->toBe(2)
        ->and(partitions())->toContain('occurrences_default');
});

it('deletes expired rows from the default partition', function (): void {
    occurrenceAt('2001-01-01 00:00:00Z');
    occurrenceAt(now()->subDay()->format('Y-m-d H:i:s\Z'));

    app(MaintainOccurrencePartitions::class)();

    expect(DB::table('occurrences')->count())->toBe(1);
});

it('never retains less than the longest granted entitlement retention', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    Account::create(['name' => 'Big'])->entitlement()->updateOrCreate([], ['retention_days' => 90, 'granted_at' => now()]);
    Account::create(['name' => 'Pending'])->entitlement()->updateOrCreate([], ['retention_days' => 365]);

    expect(app(MaintainOccurrencePartitions::class)->retentionDays())->toBe(90);
});

it('ignores ungranted entitlements when computing the global retention', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    Account::create(['name' => 'Pending'])->entitlement()->updateOrCreate([], ['retention_days' => 365]);

    expect(app(MaintainOccurrencePartitions::class)->retentionDays())->toBe(14);
});

it('moves default-partition rows by UTC month even when the session time zone is not UTC', function (): void {
    DB::statement("SET TIME ZONE 'Asia/Kuala_Lumpur'"); // UTC+8: a naive '…-01 00:00:00' bind would mean 16:00Z the day before

    $far = CarbonImmutable::now('UTC')->startOfMonth()->addMonths(5);
    occurrenceAt($far->subMinutes(30)->toIso8601String()); // 23:30Z on the last day of the previous month
    occurrenceAt($far->addMinutes(30)->toIso8601String()); // 00:30Z on the 1st

    $created = app(MaintainOccurrencePartitions::class)->ensurePartitions($far);
    $name = 'occurrences_'.$far->format('Y_m');

    expect($created)->toContain($name)
        ->and(DB::table($name)->count())->toBe(1)
        ->and(DB::table('occurrences_default')->count())->toBe(1)
        ->and(DB::table('occurrences')->count())->toBe(2);
});

it('expires default-partition rows by the UTC cutoff when the session time zone is not UTC', function (): void {
    config(['g8scope.occurrences.retention_days' => 30]);
    DB::statement("SET TIME ZONE 'Asia/Kuala_Lumpur'");
    $now = CarbonImmutable::now('UTC')->startOfMonth()->addMonths(9); // beyond the created partitions: rows sit in default
    $cutoff = $now->subDays(30);
    occurrenceAt($cutoff->subHour()->toIso8601String());
    occurrenceAt($cutoff->addHour()->toIso8601String());

    app(MaintainOccurrencePartitions::class)->dropExpired($cutoff);

    expect(DB::table('occurrences_default')->count())->toBe(1)
        ->and(DB::table('occurrences_default')->value('occurred_at'))->not->toBeNull();
    expect(CarbonImmutable::parse(DB::table('occurrences_default')->value('occurred_at'))->greaterThan($cutoff))->toBeTrue();
});

it('deletes a shared-tier project\'s old rows while a 365-day project in the same partition keeps them', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    $shared = Account::create(['name' => 'Shared'])->projects()->create(['name' => 'S']);
    $paidAccount = Account::create(['name' => 'Paid']);
    $paidAccount->entitlement()->updateOrCreate([], ['retention_days' => 365, 'granted_at' => now()]);
    $paid = $paidAccount->projects()->create(['name' => 'P']);

    $old = now()->subDays(30)->toIso8601String();
    $fresh = now()->subDay()->toIso8601String();
    foreach ([$shared, $paid] as $project) {
        occurrenceAt($old, $project->id);
        occurrenceAt($fresh, $project->id);
    }

    $result = app(MaintainOccurrencePartitions::class)();

    expect($result['deleted'])->toBe(1)
        ->and($result['dropped'])->toBe([])
        ->and(DB::table('occurrences')->where('project_id', $shared->id)->count())->toBe(1)
        ->and(DB::table('occurrences')->where('project_id', $paid->id)->count())->toBe(2);
});

it('does not extend a project\'s retention for an ungranted entitlement', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    $account = Account::create(['name' => 'Pending']);
    $account->entitlement()->updateOrCreate([], ['retention_days' => 365]);
    $project = $account->projects()->create(['name' => 'P']);
    occurrenceAt(now()->subDays(30)->toIso8601String(), $project->id);

    expect(app(MaintainOccurrencePartitions::class)()['deleted'])->toBe(1)
        ->and(DB::table('occurrences')->where('project_id', $project->id)->count())->toBe(0);
});

it('deletes past-retention rows in batches', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    $project = Account::create(['name' => 'Shared'])->projects()->create(['name' => 'S']);
    $at = now()->subDays(30)->toIso8601String();
    DB::statement(
        "INSERT INTO occurrences (event_id, project_id, issue_uuid, fingerprint, level, occurred_at, payload)
         SELECT gen_random_uuid(), ?, gen_random_uuid(), 'fp', 'error', ?::timestamptz, '{}'
         FROM generate_series(1, ?)",
        [$project->id, $at, MaintainOccurrencePartitions::BATCH + 7],
    );

    expect(app(MaintainOccurrencePartitions::class)()['deleted'])->toBe(MaintainOccurrencePartitions::BATCH + 7)
        ->and(DB::table('occurrences')->count())->toBe(0);
});

it('changes nothing on --dry-run', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    $project = Account::create(['name' => 'Shared'])->projects()->create(['name' => 'S']);
    occurrenceAt(now()->subDays(30)->toIso8601String(), $project->id);
    $later = CarbonImmutable::now('UTC')->addMonths(6);
    $before = partitions();

    $result = app(MaintainOccurrencePartitions::class)($later, dryRun: true);

    expect($result['dry_run'])->toBeTrue()
        ->and($result['deleted'])->toBe(1)
        ->and($result['created'])->not->toBe([])
        ->and($result['dropped'])->not->toBe([])
        ->and(partitions())->toBe($before)
        ->and(DB::table('occurrences')->count())->toBe(1);

    $this->artisan('g8scope:occurrences:maintain', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();
    expect(DB::table('occurrences')->count())->toBe(1);
});

it('runs via the artisan command and is scheduled daily', function (): void {
    $this->artisan('g8scope:occurrences:maintain')->assertSuccessful();

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'g8scope:occurrences:maintain'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 0 * * *');
});
