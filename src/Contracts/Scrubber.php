<?php

declare(strict_types=1);

namespace G8scope\Core\Contracts;

/**
 * PII scrubber (spec §17, §18). Runs before anything is stored, returned, or sent
 * to g8desk. Backed by cleaniquecoders/pii-protection in the app binding.
 */
interface Scrubber
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function scrub(array $data): array;
}
