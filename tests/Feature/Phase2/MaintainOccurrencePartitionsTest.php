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

function occurrenceAt(string $at): void
{
    DB::table('occurrences')->insert([
        'event_id' => (string) Str::uuid(), 'project_id' => 1, 'issue_uuid' => (string) Str::uuid(),
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

it('never retains less than the longest entitlement retention', function (): void {
    config(['g8scope.occurrences.retention_days' => 14]);
    Account::create(['name' => 'Big'])->entitlement()->updateOrCreate([], ['retention_days' => 90]);

    expect(app(MaintainOccurrencePartitions::class)->retentionDays())->toBe(90);
});

it('runs via the artisan command and is scheduled daily', function (): void {
    $this->artisan('g8scope:occurrences:maintain')->assertSuccessful();

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command, 'g8scope:occurrences:maintain'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 0 * * *');
});
