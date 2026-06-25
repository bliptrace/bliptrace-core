<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\OccurrenceStore;
use G8Scope\Core\Contracts\OccurrenceWriter;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->writer = app(OccurrenceWriter::class);
    $this->reader = app(OccurrenceStore::class);
    $this->writer->migrate();
    $this->uuid = (string) Str::uuid(); // unique per test → isolation in the shared CH table
});

it('records an occurrence and reads it back', function (): void {
    $this->writer->record([
        'project_id' => 1,
        'issue_uuid' => $this->uuid,
        'fingerprint' => 'fp',
        'event_id' => 'e1',
        'level' => 'error',
        'environment' => 'production',
        'message' => 'boom',
        'occurred_at' => '2026-06-25 09:00:00.000',
        'payload' => ['k' => 'v'],
    ]);

    $rows = iterator_to_array((function () {
        yield from $this->reader->forIssue($this->uuid, 10);
    })());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['message'])->toBe('boom');
});

it('builds a sparkline grouped by hour', function (): void {
    foreach (['09:00:00', '09:30:00', '11:00:00'] as $t) {
        $this->writer->record([
            'project_id' => 1, 'issue_uuid' => $this->uuid, 'fingerprint' => 'fp',
            'event_id' => Str::uuid(), 'level' => 'error', 'message' => 'x',
            'occurred_at' => "2026-06-25 {$t}.000",
        ]);
    }

    $spark = $this->reader->sparkline($this->uuid, '1h');
    $total = array_sum(array_column($spark, 'count'));
    expect($total)->toBe(3)->and(count($spark))->toBe(2); // 09:00 bucket (2) + 11:00 bucket (1)
});
