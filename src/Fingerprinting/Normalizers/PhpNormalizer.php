<?php

declare(strict_types=1);

namespace G8Scope\Core\Fingerprinting\Normalizers;

/**
 * PHP stack normaliser: strip volatile absolute path prefixes and closure suffixes
 * so the fingerprint is stable across deploys and machines.
 */
class PhpNormalizer extends GenericNormalizer
{
    public function runtime(): string
    {
        return 'php';
    }

    protected function normalizeFile(string $file): string
    {
        $file = parent::normalizeFile($file);

        // Anchor at the app root markers so /var/www/abc/app/X == /home/y/app/X.
        foreach (['/app/', '/src/', '/vendor/'] as $marker) {
            $pos = strpos($file, $marker);
            if ($pos !== false) {
                return ltrim(substr($file, $pos), '/');
            }
        }

        return basename($file);
    }
}
