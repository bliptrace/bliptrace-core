<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\Scrubber;

beforeEach(function (): void {
    $this->scrubber = app(Scrubber::class);
});

it('redacts sensitive keys wholesale', function (): void {
    $out = ($this->scrubber)->scrub([
        'password' => 'hunter2',
        'authorization' => 'Bearer abc',
        'nested' => ['api_key' => 'sk_live_123', 'safe' => 'keep'],
    ]);

    expect($out['password'])->toBe('[REDACTED]')
        ->and($out['authorization'])->toBe('[REDACTED]')
        ->and($out['nested']['api_key'])->toBe('[REDACTED]')
        ->and($out['nested']['safe'])->toBe('keep');
});

it('masks PII inside string values', function (): void {
    $out = ($this->scrubber)->scrub(['note' => 'contact me at john.doe@example.com please']);
    expect($out['note'])->not->toContain('john.doe@example.com');
});

it('leaves identifier fields intact while still masking PII around them', function (): void {
    $id = '86be23b5-5e80-4186-8020-161900506889'; // all-digit last group: masked as a phone number before the fix
    $at = '2026-06-25T09:14:32.521Z';

    $out = ($this->scrubber)->scrub([
        'event_id' => $id,
        'occurred_at' => $at,
        'note' => "uuid {$id} from john.doe@example.com",
        'context' => ['user' => ['password' => 'hunter2']],
    ]);

    expect($out['event_id'])->toBe($id)
        ->and($out['occurred_at'])->toBe($at)
        ->and($out['note'])->not->toContain('john.doe@example.com')
        ->and($out['context']['user']['password'])->toBe('[REDACTED]');
});
