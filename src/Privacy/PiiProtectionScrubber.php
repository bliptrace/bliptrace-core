<?php

declare(strict_types=1);

namespace G8scope\Core\Privacy;

use CleaniqueCoders\PiiProtection\Detection\PiiScrubber;
use G8scope\Core\Contracts\Scrubber;

/**
 * PII scrubber (spec §17/§18). Two layers:
 *  1. Redact by key name (password, token, secret, …) — value replaced wholesale.
 *  2. Mask PII patterns inside string values (emails, phones, …) via pii-protection.
 * Runs before anything is stored, returned, or sent to g8desk.
 */
class PiiProtectionScrubber implements Scrubber
{
    private const REDACTION = '[REDACTED]';

    /** @param array<int,string> $redactKeys */
    public function __construct(
        private readonly PiiScrubber $scrubber = new PiiScrubber,
        private readonly array $redactKeys = [
            'password', 'passwd', 'secret', 'token', 'access_token', 'refresh_token',
            'api_key', 'apikey', 'authorization', 'auth', 'cookie', 'set-cookie',
            'credit_card', 'card_number', 'cvv', 'ssn', 'private_key',
        ],
    ) {}

    public function scrub(array $data): array
    {
        return $this->walk($data);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function walk(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->shouldRedact($key)) {
                $data[$key] = self::REDACTION;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->walk($value);
            } elseif (is_string($value) && $value !== '') {
                $data[$key] = $this->scrubber->scrub($value);
            }
        }

        return $data;
    }

    private function shouldRedact(string $key): bool
    {
        return in_array(strtolower($key), $this->redactKeys, true);
    }
}
