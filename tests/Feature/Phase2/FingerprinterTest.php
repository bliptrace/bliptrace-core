<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\Fingerprinter;

beforeEach(function (): void {
    $this->fp = app(Fingerprinter::class);
});

function phpEnvelope(int $line, string $type = 'RuntimeException'): array
{
    return [
        'runtime' => ['name' => 'php'],
        'exception' => [
            'type' => $type,
            'frames' => [
                ['file' => '/var/www/releases/123/app/Actions/Reset.php', 'line' => $line, 'function' => 'Reset::__invoke', 'in_app' => true],
                ['file' => '/var/www/releases/123/vendor/laravel/x.php', 'line' => 9, 'function' => 'handle', 'in_app' => false],
            ],
        ],
    ];
}

it('groups the same error across line drift and deploy paths (php)', function (): void {
    $a = ($this->fp)->fingerprint(1, phpEnvelope(42));
    $b = ($this->fp)->fingerprint(1, phpEnvelope(57)); // line moved
    expect($a)->toBe($b);
});

it('separates different exception types', function (): void {
    expect(($this->fp)->fingerprint(1, phpEnvelope(42, 'RuntimeException')))
        ->not->toBe(($this->fp)->fingerprint(1, phpEnvelope(42, 'LogicException')));
});

it('separates the same error across projects', function (): void {
    expect(($this->fp)->fingerprint(1, phpEnvelope(42)))
        ->not->toBe(($this->fp)->fingerprint(2, phpEnvelope(42)));
});

it('honours a client fingerprint_hint', function (): void {
    $env = phpEnvelope(42);
    $env['fingerprint_hint'] = 'custom-group';
    $hinted = ($this->fp)->fingerprint(1, $env);
    expect($hinted)->not->toBe(($this->fp)->fingerprint(1, phpEnvelope(42)));
});

it('strips host and content-hash from minified JS bundles', function (): void {
    $mk = fn (string $url) => [
        'runtime' => ['name' => 'javascript'],
        'exception' => ['type' => 'TypeError', 'frames' => [
            ['file' => $url, 'line' => 1, 'function' => 'n', 'in_app' => true],
        ]],
    ];
    $a = ($this->fp)->fingerprint(1, $mk('https://cdn.a.com/app.4f3a9b1c.js'));
    $b = ($this->fp)->fingerprint(1, $mk('https://cdn.b.com/app.99887766.js'));
    expect($a)->toBe($b);
});
