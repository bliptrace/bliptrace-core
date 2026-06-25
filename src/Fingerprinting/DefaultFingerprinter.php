<?php

declare(strict_types=1);

namespace G8scope\Core\Fingerprinting;

use G8scope\Core\Contracts\Fingerprinter;

/**
 * Default fingerprint (spec §11): hash of project_id + exception.type + normalised
 * top in-app frames + culprit. A non-empty client fingerprint_hint overrides it.
 */
class DefaultFingerprinter implements Fingerprinter
{
    public function __construct(
        private readonly NormalizerRegistry $registry,
        private readonly int $maxFrames = 5,
    ) {}

    public function fingerprint(int $projectId, array $envelope): string
    {
        $hint = $envelope['fingerprint_hint'] ?? null;
        if (is_string($hint) && $hint !== '') {
            return $this->hash([$projectId, 'hint', $hint]);
        }

        $exception = $envelope['exception'] ?? [];
        $type = (string) ($exception['type'] ?? 'Error');
        $runtime = (string) ($envelope['runtime']['name'] ?? '*');

        $frames = $this->registry->for($runtime)->normalize($exception['frames'] ?? []);

        // Prefer in-app frames; fall back to all frames when none are flagged.
        $inApp = array_values(array_filter($frames, static fn ($f) => ($f['in_app'] ?? false) === true));
        $relevant = ($inApp !== [] ? $inApp : $frames);
        $top = array_slice($relevant, 0, $this->maxFrames);

        $signature = array_map(
            static fn ($f) => ($f['file'] ?? '').':'.($f['function'] ?? ''),
            $top
        );

        $culprit = $top[0]['function'] ?? ($top[0]['file'] ?? '');

        return $this->hash([$projectId, $type, implode('|', $signature), $culprit]);
    }

    /** @param array<int,mixed> $parts */
    private function hash(array $parts): string
    {
        return hash('sha256', implode("\x1f", array_map('strval', $parts)));
    }
}
