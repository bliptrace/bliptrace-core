<?php

declare(strict_types=1);

namespace G8scope\Core\Fingerprinting;

use G8scope\Core\Contracts\Normalizer;
use G8scope\Core\Fingerprinting\Normalizers\GenericNormalizer;

/** Selects a Normalizer by runtime name, falling back to the generic one. */
class NormalizerRegistry
{
    /** @var array<string,Normalizer> */
    private array $normalizers = [];

    private Normalizer $fallback;

    /** @param array<int,Normalizer> $normalizers */
    public function __construct(array $normalizers = [], ?Normalizer $fallback = null)
    {
        foreach ($normalizers as $normalizer) {
            $this->register($normalizer);
        }
        $this->fallback = $fallback ?? new GenericNormalizer;
    }

    public function register(Normalizer $normalizer): void
    {
        $this->normalizers[strtolower($normalizer->runtime())] = $normalizer;
    }

    public function for(string $runtime): Normalizer
    {
        return $this->normalizers[strtolower($runtime)] ?? $this->fallback;
    }
}
