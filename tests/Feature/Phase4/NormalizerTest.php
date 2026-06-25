<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\Fingerprinter;

beforeEach(fn () => $this->fp = app(Fingerprinter::class));

it('groups python errors across venv/site-packages paths', function (): void {
    $mk = fn (string $file) => [
        'runtime' => ['name' => 'python'],
        'exception' => ['type' => 'ValueError', 'frames' => [
            ['file' => $file, 'line' => 10, 'function' => 'handle', 'in_app' => true],
        ]],
    ];
    expect(($this->fp)->fingerprint(1, $mk('/srv/app/lib/python3.12/site-packages/pkg/mod.py')))
        ->toBe(($this->fp)->fingerprint(1, $mk('/home/ci/venv/lib/python3.12/site-packages/pkg/mod.py')));
});

it('groups go errors across module cache versions', function (): void {
    $mk = fn (string $file) => [
        'runtime' => ['name' => 'go'],
        'exception' => ['type' => 'panic', 'frames' => [
            ['file' => $file, 'line' => 5, 'function' => 'pkg.Handle', 'in_app' => true],
        ]],
    ];
    expect(($this->fp)->fingerprint(1, $mk('/root/go/pkg/mod/github.com/x/y@v1.2.3/handler.go')))
        ->toBe(($this->fp)->fingerprint(1, $mk('/home/go/pkg/mod/github.com/x/y@v1.9.0/handler.go')));
});
