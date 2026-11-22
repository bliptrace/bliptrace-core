<?php

declare(strict_types=1);

// BLIPTRACE_* env names win; the legacy G8SCOPE_* names still work until production switches.

function loadConfigWithEnv(array $env): array
{
    foreach ($env as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    try {
        return require __DIR__.'/../../config/g8scope.php';
    } finally {
        foreach (array_keys($env) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}

it('prefers the BLIPTRACE_* name over the legacy G8SCOPE_* name', function (): void {
    $config = loadConfigWithEnv([
        'BLIPTRACE_API_URL' => 'https://new.example.test',
        'G8SCOPE_API_URL' => 'https://old.example.test',
        'BLIPTRACE_OCCURRENCES_RETENTION_DAYS' => '30',
        'G8SCOPE_OCCURRENCES_RETENTION_DAYS' => '7',
    ]);

    expect($config['endpoints']['api'])->toBe('https://new.example.test')
        ->and($config['occurrences']['retention_days'])->toBe(30);
});

it('falls back to the legacy G8SCOPE_* name', function (): void {
    $config = loadConfigWithEnv([
        'G8SCOPE_API_URL' => 'https://old.example.test',
        'G8SCOPE_OCCURRENCES_RETENTION_DAYS' => '7',
        'G8SCOPE_BILLING_DRIVER' => 'stripe',
    ]);

    expect($config['endpoints']['api'])->toBe('https://old.example.test')
        ->and($config['occurrences']['retention_days'])->toBe(7)
        ->and($config['drivers']['billing_provider'])->toContain('Stripe');
});
