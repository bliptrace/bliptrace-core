<?php

declare(strict_types=1);

namespace G8Scope\Core\Fingerprinting\Normalizers;

/** Python stack normaliser: anchor at the app, drop site-packages/venv prefixes. */
class PythonNormalizer extends GenericNormalizer
{
    public function runtime(): string
    {
        return 'python';
    }

    protected function normalizeFile(string $file): string
    {
        $file = parent::normalizeFile($file);

        foreach (['/site-packages/', '/dist-packages/'] as $marker) {
            $pos = strpos($file, $marker);
            if ($pos !== false) {
                return ltrim(substr($file, $pos + strlen($marker)), '/');
            }
        }

        return basename($file);
    }
}
