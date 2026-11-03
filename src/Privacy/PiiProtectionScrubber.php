<?php

declare(strict_types=1);

namespace G8Scope\Core\Privacy;

use CleaniqueCoders\PiiProtection\Contracts\MaskStrategy;
use CleaniqueCoders\PiiProtection\Masking\FullStrategy;
use G8Scope\Core\Contracts\Scrubber;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PII scrubber (spec §17/§18). Runs before anything is stored, returned, or sent to g8desk.
 *  1. Redact by key name — the key is normalised (lower-case, '-' → '_', 'http_' prefix
 *     stripped) and the value replaced wholesale when it names a credential.
 *  2. Mask PII inside every other string AND number value (numbers are scanned as
 *     strings; a masked number is stored as the masked string). Each match becomes
 *     same-length '*' (pii-protection's FullStrategy).
 *
 * Per value, in order: cap at 64 KiB (remainder → [TRUNCATED]); credential detectors on
 * the whole string (so a UUID that IS a credential value is masked); whitespace tokens
 * that are URL / HTML-entity / JSON-escape encoded and decode to PII are masked whole;
 * then the PII detectors run between canonical UUIDs, which are never masked.
 *
 * Fail closed: if any regex errors (backtrack/JIT limit), the whole value becomes
 * [REDACTED] and a warning is logged without the value.
 *
 * Known gap, shared with Go: camelCase keys (accessKey, apiToken is fine — token is a
 * substring) are not split, so `accessKey` is not redacted by name.
 *
 * The patterns mirror the Go ingest scrubber (g8scope-api/internal/scrub) so the
 * dashboard and the ingest path mask identically. event_id / occurred_at are skipped
 * only at the top level of the envelope (typed uuid / timestamptz columns).
 */
class PiiProtectionScrubber implements Scrubber
{
    private const REDACTION = '[REDACTED]';

    private const TRUNCATION = '[TRUNCATED]';

    public const MAX_BYTES = 65536;

    private const UUID = '/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/';

    /** Key fragments that redact anywhere inside the normalised key. */
    private const KEY_SUBSTRINGS = [
        'password', 'passwd', 'pwd', 'secret', 'token', 'auth', 'api_key', 'apikey',
        'cookie', 'session', 'credential', 'private',
    ];

    /** Redacts only as a whole '_'-separated token (x_api_key, access_key — not monkey). */
    private const KEY_TOKEN = 'key';

    /** Decoding recursion bound (Go: maskEncoded depth). */
    private const MAX_DECODE_DEPTH = 3;

    /**
     * `<word>[=:]value` in free text: masked when the word passes sensitiveKey() (the same
     * rule as key names). A Bearer/Basic prefix is skipped; a value starting with ':' is
     * not a value (Authenticate::handle).
     */
    private const KEY_VALUE = '/(?<![A-Za-z0-9_\-])([A-Za-z0-9_\-]+)(["\']?\s*[:=]\s*["\']?(?:(?:bearer|basic)\s+)?)(?!:)([^\s"\'&,;]+)/i';

    /**
     * Credentials — run on the whole string, before UUID protection.
     * [regex, mask capture group 1 (a suffix of the match) instead of the whole match].
     *
     * @var array<int,array{0:string,1:bool}>
     */
    private const CREDENTIALS = [
        ['/\bbearer\s+([A-Za-z0-9\-._~+\/]+=*)/i', true],
        ['/\beyJ[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]+/', false],
        ['/\b(?:(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{10,}|gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|xox[abprs]-[A-Za-z0-9-]{10,}|AKIA[0-9A-Z]{16})\b/', false],
    ];

    /**
     * PII — run between UUIDs. Broader number patterns (cards) before narrower ones
     * (IC, phone) so a phone rule can't half-mask a card. Digit runs are bounded by
     * (?<![0-9]) / (?![0-9]) rather than \b so `card_4111…` / `ic900101…` are caught.
     * [regex, validator].
     *
     * @var array<int,array{0:string,1:?string}>
     */
    private const DETECTORS = [
        // Email. The lookbehind + possessive local part keep a long run with no '@' linear.
        ['/(?<![A-Za-z0-9._%+\-])[A-Za-z0-9._%+\-]++@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', null],
        // Payment card: 13–19 digits, optional space/dash separators, Luhn-valid only.
        ['/(?<![0-9])\d(?:[ -]?\d){12,18}(?![0-9])/', 'luhn'],
        // MyKad / IC: YYMMDD-PB-###G with dash or space separators, or 12 digits with a plausible YYMMDD.
        ['/(?<![0-9])\d{6}[ -]\d{2}[ -]\d{4}(?![0-9])/', null],
        ['/(?<![0-9])\d{12}(?![0-9])/', 'icDate'],
        // IPv4 (personal data under PDPA), octets ≤ 255.
        ['/\b(?:\d{1,3}\.){3}\d{1,3}\b/', 'ipv4'],
        // Malaysian phone: +60 / 60 / 0 prefix with space or dash groups, (0xx) area code, or 0xx.xxx.xxxx.
        ['/(?:\+60|(?<![0-9])60|(?<![0-9])0)[ -]?\d{1,2}[ -]?\d{3,4}[ -]?\d{4}(?![0-9])/', null],
        ['/(?<![0-9])\(0\d{1,2}\)[ -]?\d{3,4}[ -]?\d{4}(?![0-9])/', null],
        ['/(?<![0-9.])0\d{1,2}\.\d{3,4}\.\d{4}(?![0-9.])/', null],
        // Generic E.164.
        ['/\+[1-9]\d{7,14}\b/', null],
    ];

    /** Values replaced with [REDACTED] because a regex failed (process-wide). */
    public static int $failures = 0;

    /**
     * @param  array<int,string>  $redactKeys  extra exact (normalised) key names to redact
     * @param  array<int,string>  $preserveKeys
     */
    public function __construct(
        private readonly MaskStrategy $mask = new FullStrategy,
        private readonly array $redactKeys = ['credit_card', 'card_number', 'cvv', 'ssn'],
        private readonly array $preserveKeys = ['event_id', 'occurred_at'],
    ) {}

    public function scrub(array $data): array
    {
        return $this->walk($data, true);
    }

    /** Mask PII in free text. Never returns unscanned input: on a regex failure it returns [REDACTED]. */
    public function text(string $text): string
    {
        try {
            $tail = '';
            if (strlen($text) > self::MAX_BYTES) {
                // mb_strcut never splits a UTF-8 character (invalid UTF-8 would break jsonb).
                [$text, $tail] = [mb_strcut($text, 0, self::MAX_BYTES, 'UTF-8'), self::TRUNCATION];
            }

            return $this->scan($text, 0).$tail;
        } catch (RuntimeException) {
            self::$failures++;
            Log::warning('g8scope scrubber: regex failed, value redacted', ['error' => preg_last_error_msg()]);

            return self::REDACTION;
        }
    }

    public function sensitiveKey(string $key): bool
    {
        $k = str_replace('-', '_', strtolower($key));
        $k = str_starts_with($k, 'http_') ? substr($k, 5) : $k;

        if (in_array($k, $this->redactKeys, true)) {
            return true;
        }

        foreach (self::KEY_SUBSTRINGS as $s) {
            if (str_contains($k, $s)) {
                return true;
            }
        }

        return in_array(self::KEY_TOKEN, explode('_', $k), true);
    }

    /**
     * @param  array<array-key,mixed>  $data
     * @return array<array-key,mixed>
     */
    private function walk(array $data, bool $top = false): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->sensitiveKey($key)) {
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
            } elseif (is_int($value) || is_float($value)) {
                // An integral float is scanned as plain digits (4.1111111111111E+15 would hide a card).
                $plain = is_float($value) && floor($value) === $value && abs($value) < 1e19
                    ? sprintf('%.0f', $value)
                    : (string) $value;
                $masked = $this->text($plain);
                if ($masked !== $plain) {
                    $data[$key] = $masked;
                }
            }
        }

        return $data;
    }

    /** Credentials over the whole string, then PII between the (protected) UUIDs. */
    private function maskPlain(string $text): string
    {
        if (strlen($text) < 6) {
            return $text;
        }

        $text = $this->replace(self::KEY_VALUE, $text, fn (array $m): string => $this->sensitiveKey($m[1])
            ? $m[1].$m[2].$this->mask->mask($m[3])
            : $m[0]);

        foreach (self::CREDENTIALS as [$regex, $group]) {
            $text = $this->replace($regex, $text, function (array $m) use ($group): string {
                $target = $group ? $m[1] : $m[0];

                return substr($m[0], 0, strlen($m[0]) - strlen($target)).$this->mask->mask($target);
            });
        }

        $parts = preg_split(self::UUID, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new RuntimeException('preg_split');
        }

        foreach ($parts as $i => $part) {
            if ($i % 2 === 0 && strlen($part) >= 6) { // odd indexes are the captured UUIDs
                foreach (self::DETECTORS as [$regex, $valid]) {
                    $part = $this->replace($regex, $part, fn (array $m): string => $valid && ! $this->{$valid}($m[0])
                        ? $m[0]
                        : $this->mask->mask($m[0]));
                }
                $parts[$i] = $part;
            }
        }

        return implode('', $parts);
    }

    private function scan(string $text, int $depth): string
    {
        return $this->maskEncoded($this->maskPlain($text), $text, $depth);
    }

    /**
     * A whitespace token that is percent-, HTML-entity- or \u0040 / \x40-escaped and whose
     * decoded form contains PII (ali%40example.com, ali&#64;example.com) is masked whole.
     * Masks never add or remove whitespace, so the tokens of $masked and $original line up.
     */
    private function maskEncoded(string $masked, string $original, int $depth): string
    {
        if ($depth >= self::MAX_DECODE_DEPTH || ! $this->hasEscape($original)) {
            return $masked;
        }

        $split = '/([ \t\r\n]+)/';
        $orig = preg_split($split, $original, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = preg_split($split, $masked, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($orig === false || $out === false || count($orig) !== count($out)) {
            throw new RuntimeException('preg_split');
        }

        foreach ($orig as $i => $token) {
            if ($i % 2 === 0 && $this->hasEscape($token) && $this->hidesPii($token, $depth)) {
                $out[$i] = str_repeat('*', mb_strlen($token, 'UTF-8'));
            }
        }

        return implode('', $out);
    }

    private function hasEscape(string $s): bool
    {
        return strpbrk($s, '%&') !== false || stripos($s, '\\u0040') !== false || stripos($s, '\\x40') !== false;
    }

    /** Same candidates as Go: HTML-unescaped, then path- and query-unescaped; each scanned one level deeper. */
    private function hidesPii(string $token, int $depth): bool
    {
        $h = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $h = str_ireplace(['\\u0040', '\\x40'], '@', $h);

        foreach ([$h, rawurldecode($h), urldecode($h)] as $candidate) {
            if ($candidate !== '' && $candidate !== $token && $this->scan($candidate, $depth + 1) !== $candidate) {
                return true;
            }
        }

        return false;
    }

    /** preg_replace_callback that throws instead of silently returning null on a regex error. */
    private function replace(string $regex, string $text, callable $callback): string
    {
        $out = preg_replace_callback($regex, $callback, $text);
        if ($out === null) {
            throw new RuntimeException('preg_replace_callback');
        }

        return $out;
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
