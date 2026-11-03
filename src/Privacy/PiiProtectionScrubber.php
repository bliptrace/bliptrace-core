<?php

declare(strict_types=1);

namespace G8Scope\Core\Privacy;

use CleaniqueCoders\PiiProtection\Contracts\MaskStrategy;
use CleaniqueCoders\PiiProtection\Masking\FullStrategy;
use G8Scope\Core\Contracts\Scrubber;

/**
 * PII scrubber (spec §17/§18). Two layers:
 *  1. Redact by key name (password, token, secret, …) — value replaced wholesale.
 *  2. Mask PII patterns inside every other string value — each match replaced by
 *     same-length '*' (pii-protection's FullStrategy).
 * Runs before anything is stored, returned, or sent to g8desk.
 *
 * The patterns mirror the Go ingest scrubber (g8scope-api/internal/scrub) so the
 * dashboard and the ingest path mask identically. pii-protection's own detectors
 * mask any 16-digit run and miss the undashed IC, so they are implemented here.
 *
 * Canonical UUIDs inside any string are never masked. event_id / occurred_at are
 * skipped only at the top level of the envelope (typed uuid / timestamptz columns);
 * the same keys nested anywhere else are user data and are masked as usual.
 */
class PiiProtectionScrubber implements Scrubber
{
    private const REDACTION = '[REDACTED]';

    private const UUID = '/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/';

    /**
     * Ordered: credentials first, then broader number patterns (cards) before narrower
     * ones (IC, phone) so a phone rule can't half-mask a card.
     * [regex, mask capture group 1 (a suffix of the match) instead of the whole match, validator].
     *
     * @var array<int,array{0:string,1:bool,2:?string}>
     */
    private const DETECTORS = [
        // Credentials in free text.
        ['/\bbearer\s+([A-Za-z0-9\-._~+\/]+=*)/i', true, null],
        ['/\b(?:api[_-]?key|access[_-]?token|auth[_-]?token|token|secret|password|passwd)(?:["\']?\s*[:=]\s*["\']?)([^\s"\'&,;]+)/i', true, null],
        ['/\beyJ[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]+/', false, null],
        ['/\b(?:(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{10,}|gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|xox[abprs]-[A-Za-z0-9-]{10,}|AKIA[0-9A-Z]{16})\b/', false, null],
        // Email (pii-protection's pattern).
        ['/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', false, null],
        // Payment card: 13–19 digits, optional space/dash separators, Luhn-valid only.
        ['/\b\d(?:[ -]?\d){12,18}\b/', false, 'luhn'],
        // MyKad / IC: dashed YYMMDD-PB-###G, or 12 undashed digits with a plausible YYMMDD.
        ['/\b\d{6}-\d{2}-\d{4}\b/', false, null],
        ['/\b\d{12}\b/', false, 'icDate'],
        // IPv4 (personal data under PDPA), octets ≤ 255.
        ['/\b(?:\d{1,3}\.){3}\d{1,3}\b/', false, 'ipv4'],
        // Malaysian phone (+60 / 60 / 0 prefix, optional space or dash groups).
        ['/(?:\+60|\b60|\b0)[ -]?\d{1,2}[ -]?\d{3,4}[ -]?\d{4}\b/', false, null],
        // Generic E.164.
        ['/\+[1-9]\d{7,14}\b/', false, null],
    ];

    /**
     * @param  array<int,string>  $redactKeys
     * @param  array<int,string>  $preserveKeys
     */
    public function __construct(
        private readonly MaskStrategy $mask = new FullStrategy,
        private readonly array $redactKeys = [
            'password', 'passwd', 'secret', 'token', 'access_token', 'refresh_token',
            'api_key', 'apikey', 'authorization', 'auth', 'cookie', 'set-cookie',
            'credit_card', 'card_number', 'cvv', 'ssn', 'private_key',
        ],
        private readonly array $preserveKeys = ['event_id', 'occurred_at'],
    ) {}

    public function scrub(array $data): array
    {
        return $this->walk($data, true);
    }

    /**
     * Mask PII patterns in free text, leaving canonical UUIDs intact: the text between
     * them is masked segment by segment, so no pattern can reach into a UUID.
     */
    public function text(string $text): string
    {
        $parts = preg_split(self::UUID, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) { // odd indexes are the captured UUIDs
                $parts[$i] = $this->maskSegment($part);
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @return array<array-key,mixed>
     */
    private function walk(array $data, bool $top = false): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->redactKeys, true)) {
                $data[$key] = self::REDACTION;

                continue;
            }

            if ($top && is_string($key) && in_array(strtolower($key), $this->preserveKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->walk($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->text($value);
            }
        }

        return $data;
    }

    private function maskSegment(string $text): string
    {
        if (strlen($text) < 6) {
            return $text;
        }

        foreach (self::DETECTORS as [$regex, $group, $valid]) {
            $text = preg_replace_callback($regex, function (array $m) use ($group, $valid): string {
                $target = $group ? $m[1] : $m[0];

                if ($valid && ! $this->{$valid}($target)) {
                    return $m[0];
                }

                return substr($m[0], 0, strlen($m[0]) - strlen($target)).$this->mask->mask($target);
            }, $text) ?? $text;
        }

        return $text;
    }

    private function luhn(string $match): bool
    {
        $digits = strrev((string) preg_replace('/\D/', '', $match));
        $sum = 0;

        foreach (str_split($digits) as $i => $d) {
            $d = (int) $d;
            if ($i % 2 === 1 && ($d *= 2) > 9) {
                $d -= 9;
            }
            $sum += $d;
        }

        return strlen($digits) >= 13 && $sum % 10 === 0;
    }

    private function icDate(string $match): bool
    {
        $mm = (int) substr($match, 2, 2);
        $dd = (int) substr($match, 4, 2);

        return $mm >= 1 && $mm <= 12 && $dd >= 1 && $dd <= 31;
    }

    private function ipv4(string $match): bool
    {
        foreach (explode('.', $match) as $octet) {
            if ((int) $octet > 255) {
                return false;
            }
        }

        return true;
    }
}
