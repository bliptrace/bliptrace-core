<?php

declare(strict_types=1);

use G8Scope\Core\Contracts\Scrubber;
use G8Scope\Core\Privacy\PiiProtectionScrubber;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->scrubber = app(Scrubber::class);
});

it('redacts sensitive keys wholesale', function (): void {
    $out = ($this->scrubber)->scrub([
        'password' => 'hunter2',
        'authorization' => 'Bearer abc',
        'nested' => ['api_key' => 'sk_live_123', 'safe' => 'keep'],
    ]);

    expect($out['password'])->toBe('[REDACTED]')
        ->and($out['authorization'])->toBe('[REDACTED]')
        ->and($out['nested']['api_key'])->toBe('[REDACTED]')
        ->and($out['nested']['safe'])->toBe('keep');
});

it('masks PII inside string values', function (): void {
    $out = ($this->scrubber)->scrub(['note' => 'contact me at john.doe@example.com please']);
    expect($out['note'])->not->toContain('john.doe@example.com');
});

it('leaves identifier fields intact while still masking PII around them', function (): void {
    $id = '86be23b5-5e80-4186-8020-161900506889'; // all-digit last group: masked as a phone number before the fix
    $at = '2026-06-25T09:14:32.521Z';

    $out = ($this->scrubber)->scrub([
        'event_id' => $id,
        'occurred_at' => $at,
        'note' => "uuid {$id} from john.doe@example.com",
        'context' => ['user' => ['password' => 'hunter2']],
    ]);

    expect($out['event_id'])->toBe($id)
        ->and($out['occurred_at'])->toBe($at)
        ->and($out['note'])->not->toContain('john.doe@example.com')
        ->and($out['context']['user']['password'])->toBe('[REDACTED]');
});

// The vectors below mirror g8scope-api/internal/scrub/scrub_test.go (TestText / TestScrub)
// so the Go ingest scrubber and this one are tested against the same inputs AND outputs.
// Keep them identical: a change here is a change there (security-review 0.2.2 F1/F2).
const DIGIT_TAIL_UUID = '53a33b29-3630-48c1-83f2-123456789012';
const CRED_UUID = '3f2a1b4c-1111-2222-3333-444455556666';

dataset('scrub_text_vectors', function (): array {
    $stars = fn (string $s): string => str_repeat('*', strlen($s));
    $u = DIGIT_TAIL_UUID;
    $pat = 'github_pat_'.str_repeat('A1b2C3d4E5', 8).'xy'; // github_pat_ + 82 chars

    return [
        // --- masked ---
        'email' => ['mail ali.bin-abu@example.gov.my now', 'mail '.$stars('ali.bin-abu@example.gov.my').' now'],
        'MyKad dashed' => ['IC 900101-14-5678 ok', 'IC ************** ok'],
        'MyKad undashed with valid date' => ['ic=900101145678', 'ic=************'],
        'MY mobile dashed' => ['call 012-3456789', 'call ***********'],
        'MY mobile spaced' => ['call 012-345 6789', 'call ************'],
        'MY mobile +60' => ['call +60123456789.', 'call ************.'],
        'MY landline' => ['tel 03-2345 6789', 'tel ************'],
        'E.164 foreign' => ['ring +447911123456', 'ring *************'],
        'Luhn-valid card spaced' => ['card 4111 1111 1111 1111 declined', 'card ******************* declined'],
        'Luhn-valid card plain 15 digits (Amex)' => ['378282246310005', '***************'],
        'bearer token' => ['Authorization: Bearer abc.DEF-123_xyz=', 'Authorization: Bearer ****************'],
        'credential in key=value text' => ['retry with password=hunter2&x=1', 'retry with password=*******&x=1'],
        'api key in JSON-ish text' => ['{"api_key": "k-123456"}', '{"api_key": "********"}'],
        'JWT' => ['jwt eyJhbGciOiJ.eyJzdWIiOiIx.SflKxwRJSM', 'jwt '.$stars('eyJhbGciOiJ.eyJzdWIiOiIx.SflKxwRJSM')],
        'GitHub token' => ['ghp_abcdefghijklmnopqrstuvwxyz0123', '**********************************'],
        'IPv4' => ['from 203.0.113.42 at', 'from ************ at'],
        'PII next to a UUID is still masked' => [$u.' ali@example.com', $u.' ***************'],
        // Built by concatenation so the secret-guard hook does not flag a literal PAT.
        'GitHub fine-grained PAT' => [$pat, $stars($pat)],

        // --- 0.2.2 F1b–e: encoded, digit-boundary and free-text credential bypasses ---
        'URL-encoded email' => ['ali%40example.com', '*****************'],
        'HTML-entity email' => ['ali&#64;example.com', '*******************'],
        'card glued to a word' => ['card_4111111111111111', 'card_****************'],
        'IC glued to a word' => ['ic900101-14-5678', 'ic**************'],
        'IC with spaces' => ['900101 14 5678', '**************'],
        'phone with (area code)' => ['(012) 345 6789', '**************'],
        'phone with dots' => ['012.345.6789', '************'],
        'client_secret= in text' => ['client_secret=abc123', 'client_secret=******'],
        'DB_PASSWORD= in text' => ['DB_PASSWORD=hunter2', 'DB_PASSWORD=*******'],
        '_token= in text' => ['_token=xyz', '_token=***'],
        'Authorization: Basic' => ['Authorization: Basic dXNlcjpwYXNz', 'Authorization: Basic ************'],

        // --- 0.2.2 F2: a UUID that is a credential value is masked; other UUIDs stay ---
        'Bearer <uuid>' => ['Bearer '.CRED_UUID, 'Bearer '.$stars(CRED_UUID)],
        'api_key=<uuid>' => ['api_key='.CRED_UUID, 'api_key='.$stars(CRED_UUID)],
        'plain UUID in a message' => ['request '.CRED_UUID.' failed', 'request '.CRED_UUID.' failed'],

        // --- left alone (false-positive guards) ---
        'UUID with an all-digit tail' => [$u, $u],
        'upper-case UUID' => [strtoupper($u), strtoupper($u)],
        'UUID embedded in text' => ["issue {$u} failed", "issue {$u} failed"],
        'RFC 3339 timestamp' => ['2026-06-25T09:14:32.521Z', '2026-06-25T09:14:32.521Z'],
        'date' => ['on 2026-06-25', 'on 2026-06-25'],
        'semver' => ['php 8.3.7 / laravel 11.34.2', 'php 8.3.7 / laravel 11.34.2'],
        'stack frame line number' => ['/app/Actions/Reset.php:1234', '/app/Actions/Reset.php:1234'],
        'unix ms timestamp' => ['at 1782379672521', 'at 1782379672521'],
        'Luhn-invalid 16 digits' => ['order 1234567812345678', 'order 1234567812345678'],
        '12 digits that are not a date' => ['id 991399123456', 'id 991399123456'],
        'invalid IP octet' => ['v 300.1.2.3', 'v 300.1.2.3'],
        'trace id' => ['4bf92f3577b34da6a3ce929d0e0e4736', '4bf92f3577b34da6a3ce929d0e0e4736'],
        'csrf_token word is not a credential key' => ['csrf_token missing', 'csrf_token missing'],
        'short string' => ['12345', '12345'],
        'plain message' => ['Undefined index: user', 'Undefined index: user'],
    ];
});

it('masks free text exactly like the Go ingest scrubber', function (string $in, string $want): void {
    expect(app(Scrubber::class)->scrub(['m' => $in])['m'])->toBe($want);
})->with('scrub_text_vectors');

dataset('scrub_envelope_vectors', function (): array {
    $u = DIGIT_TAIL_UUID;

    return [
        'event_id and occurred_at are never masked' => [
            ['event_id' => $u, 'occurred_at' => '2026-06-25T09:14:32.521Z'],
            ['event_id' => $u, 'occurred_at' => '2026-06-25T09:14:32.521Z'],
        ],
        'nested ids and trace ids are untouched' => [
            ['context' => ['request' => ['id' => $u]], 'trace' => ['trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736', 'span_id' => '00f067aa0ba902b7']],
            ['context' => ['request' => ['id' => $u]], 'trace' => ['trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736', 'span_id' => '00f067aa0ba902b7']],
        ],
        'values are masked at any depth, including arrays' => [
            ['breadcrumbs' => [['message' => 'login ali@example.com']], 'context' => ['user' => ['phone' => '0123456789']]],
            ['breadcrumbs' => [['message' => 'login ***************']], 'context' => ['user' => ['phone' => '**********']]],
        ],
        'sensitive keys are redacted wholesale, case-insensitively' => [
            ['context' => ['user' => ['Password' => 'hunter2', 'id' => $u], 'headers' => [['Authorization' => 'Bearer x']]]],
            ['context' => ['user' => ['Password' => '[REDACTED]', 'id' => $u], 'headers' => [['Authorization' => '[REDACTED]']]]],
        ],
        'non-PII scalars untouched' => [
            ['line' => 1234, 'ratio' => 0.5, 'in_app' => true, 'x' => null],
            ['line' => 1234, 'ratio' => 0.5, 'in_app' => true, 'x' => null],
        ],
        // 0.2.2 F1b: numbers are scanned; a masked number is stored as the masked string.
        'card as a JSON number' => [['card' => 4111111111111111], ['card' => '****************']],
        'IC as a JSON number' => [['ic' => 900101145678], ['ic' => '************']],
        // 0.2.2 F1a: keys are normalised (lower-case, - → _, HTTP_ stripped) and matched by fragment.
        'credential-ish keys are redacted' => [
            ['X-Api-Key' => 'a', 'db_password' => 'b', 'client_secret' => 'c', 'HTTP_AUTHORIZATION' => 'd', 'PHP_AUTH_PW' => 'e', 'access_key' => 'f', 'csrf_token' => 'g'],
            ['X-Api-Key' => '[REDACTED]', 'db_password' => '[REDACTED]', 'client_secret' => '[REDACTED]', 'HTTP_AUTHORIZATION' => '[REDACTED]', 'PHP_AUTH_PW' => '[REDACTED]', 'access_key' => '[REDACTED]', 'csrf_token' => '[REDACTED]'],
        ],
        "'key' only as a whole token" => [
            ['monkey' => 'banana', 'keyboard_layout' => 'qwerty'],
            ['monkey' => 'banana', 'keyboard_layout' => 'qwerty'],
        ],
        // core only: preserve keys apply at the top level, so a nested event_id cannot smuggle PII.
        'nested event_id / occurred_at are masked' => [
            ['extra' => ['event_id' => 'ali@example.com', 'occurred_at' => 'call 012-3456789']],
            ['extra' => ['event_id' => '***************', 'occurred_at' => 'call ***********']],
        ],
    ];
});

it('scrubs envelopes exactly like the Go ingest scrubber', function (array $in, array $want): void {
    expect(app(Scrubber::class)->scrub($in))->toBe($want);
})->with('scrub_envelope_vectors');

// --- core-only (not mirrored in Go) ---

it('masks a JSON-escaped email', function (): void {
    expect(app(Scrubber::class)->scrub(['m' => 'ali\\u0040example.com'])['m'])->toBe(str_repeat('*', 20));
});

it('fails closed when a regex errors, logging without the value', function (): void {
    Log::spy();
    $before = PiiProtectionScrubber::$failures;
    $limit = ini_get('pcre.backtrack_limit');
    $jit = ini_get('pcre.jit');
    ini_set('pcre.jit', '0');
    ini_set('pcre.backtrack_limit', '1');

    try {
        $out = (new PiiProtectionScrubber)->scrub(['m' => 'contact ali@example.com now please']);
    } finally {
        ini_set('pcre.backtrack_limit', (string) $limit);
        ini_set('pcre.jit', (string) $jit);
    }

    expect($out['m'])->toBe('[REDACTED]')
        ->and(PiiProtectionScrubber::$failures)->toBe($before + 1);
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $msg, array $ctx): bool => ! str_contains($msg.json_encode($ctx), 'ali@example.com'),
    );
});

it('caps each value at 64 KiB and marks the remainder truncated', function (): void {
    $in = str_repeat('x', PiiProtectionScrubber::MAX_BYTES).' ali@example.com';
    $out = app(Scrubber::class)->scrub(['m' => $in])['m'];

    expect($out)->toBe(str_repeat('x', PiiProtectionScrubber::MAX_BYTES).'[TRUNCATED]');
});

it('stays linear on pathological input without the PCRE JIT', function (string $in): void {
    $jit = ini_get('pcre.jit');
    ini_set('pcre.jit', '0');

    try {
        $start = hrtime(true);
        $out = (new PiiProtectionScrubber)->text($in);
        $ms = (hrtime(true) - $start) / 1e6;
    } finally {
        ini_set('pcre.jit', (string) $jit);
    }

    expect($out)->not->toBe('[REDACTED]')->and($ms)->toBeLessThan(200);
})->with([
    'dotted local part then a bare @ (1.5 s before the fix)' => [str_repeat('a.', 10_000).'@x'],
    'local-part run with no @' => [str_repeat('a', 20_000)],
    'dotted run with no TLD' => [str_repeat('a.', 10_000)],
    'repeated @' => [str_repeat('a@', 10_000)],
    'long credential-ish word' => [str_repeat('k', 20_000).'=v'],
]);
