<?php

declare(strict_types=1);

use G8scope\Core\Contracts\Scrubber;

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
