<?php

declare(strict_types=1);

namespace G8Scope\Core\Fingerprinting\Normalizers;

/**
 * JavaScript stack normaliser: strip origin/host, cache-busting hashes and query
 * strings from bundle URLs so minified builds group consistently.
 */
class JavascriptNormalizer extends GenericNormalizer
{
    public function runtime(): string
    {
        return 'javascript';
    }

    protected function normalizeFile(string $file): string
    {
        $file = parent::normalizeFile($file);

        // Drop scheme://host, keep the path.
        $file = preg_replace('#^[a-z]+://[^/]+/#i', '', $file) ?? $file;

        // Strip content hashes: app.4f3a9b.js -> app.js
        $file = preg_replace('/\.[0-9a-f]{8,}(\.js)/i', '$1', $file) ?? $file;

        return $file;
    }
}
