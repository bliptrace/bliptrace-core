<?php

declare(strict_types=1);

namespace G8Scope\Core\Fingerprinting\Normalizers;

use G8Scope\Core\Contracts\Normalizer;

/** Fallback normaliser for runtimes without a dedicated one. */
class GenericNormalizer implements Normalizer
{
    public function runtime(): string
    {
        return '*';
    }

    public function normalize(array $frames): array
    {
        $out = [];
        foreach ($frames as $frame) {
            $out[] = [
                'file' => $this->normalizeFile((string) ($frame['file'] ?? '')),
                'function' => (string) ($frame['function'] ?? ''),
                'in_app' => (bool) ($frame['in_app'] ?? false),
            ];
        }

        return $out;
    }

    protected function normalizeFile(string $file): string
    {
        // Drop query/hash and collapse backslashes; keep the path tail stable.
        $file = preg_replace('/[?#].*$/', '', $file) ?? $file;

        return str_replace('\\', '/', $file);
    }
}
