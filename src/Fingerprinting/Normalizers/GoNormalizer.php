<?php

declare(strict_types=1);

namespace G8scope\Core\Fingerprinting\Normalizers;

/** Go stack normaliser: strip module versions and the module cache path. */
class GoNormalizer extends GenericNormalizer
{
    public function runtime(): string
    {
        return 'go';
    }

    protected function normalizeFile(string $file): string
    {
        $file = parent::normalizeFile($file);

        // Strip the module cache prefix and the @vX.Y.Z version suffix on modules.
        $pos = strpos($file, '/pkg/mod/');
        if ($pos !== false) {
            $file = substr($file, $pos + strlen('/pkg/mod/'));
        }

        return preg_replace('/@v[0-9][^\/]*/', '', $file) ?? $file;
    }
}
