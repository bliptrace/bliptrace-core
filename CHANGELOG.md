# Changelog

All notable changes to `g8scope/core` will be documented in this file.

## [Unreleased]

## [0.2.2] - 2026-11-03

Security-review fixes F1–F5. The scrubber changes mirror `g8scope-api/internal/scrub`;
both are tested against the same vectors.

### Security
- **F1 (High) scrubber bypasses closed** in `PiiProtectionScrubber`:
  - key names are normalised (lower-case, `-` → `_`, `HTTP_` stripped) and redacted on any
    fragment: password, passwd, pwd, secret, token, authorization, api_key, apikey, cookie,
    session, credential, private; `key` / `auth` only as a whole `_` token (`x_api_key`,
    `access_key`, `PHP_AUTH_PW` redacted; `monkey`, `keyboard_layout`, `author` kept).
    `csrf_token` is now redacted;
  - int/float values are scanned; a masked number is stored as the masked string;
  - URL-, HTML-entity- and JSON-escape-encoded tokens (`ali%40example.com`, `ali&#64;…`)
    that decode to PII are masked whole;
  - card / IC / phone use digit lookarounds instead of `\b` (`card_4111…`, `ic900101-14-5678`);
    IC with spaces; phones as `(012) 345 6789` and `012.345.6789`;
  - free-text credentials: any word ending in secret/password/passwd/pwd/token/key followed
    by `=`/`:` (`client_secret=`, `DB_PASSWORD=`, `_token=`), and `Authorization: Basic …`.
- **F2** credential detectors run on the whole string before UUID protection, so
  `Bearer <uuid>` / `api_key=<uuid>` are masked; other UUIDs stay intact.
- **F3** fail closed: a regex error replaces the value with `[REDACTED]`, increments
  `PiiProtectionScrubber::$failures` and logs a warning without the value. Values are capped
  at 64 KiB (remainder → `[TRUNCATED]`). The email pattern no longer backtracks
  quadratically (20 KB pathological input < 200 ms with `pcre.jit=0`).
- **F4** `MaintainOccurrencePartitions` is session-time-zone independent: timestamptz
  comparisons bind ISO-8601 strings with an offset, the partition move runs under
  `SET LOCAL TIME ZONE 'UTC'`, and rows move with `insertUsing` (bound) instead of `toRawSql()`.
- **F5** per-project retention: partition drops use the longest **granted** entitlement
  only; rows past each project's own retention (granted entitlement, else the config default)
  are deleted in batches of 5,000 by primary key. Counts are logged before any `DROP TABLE`.

### Added
- `g8scope:occurrences:maintain --dry-run`.

### Changed
- The maintenance action's result gains `deleted` and `dry_run`.

## [0.2.1] - 2026-11-03

### Fixed
- `PiiProtectionScrubber` now masks exactly like the Go ingest scrubber
  (`g8scope-api/internal/scrub`), tested against the same vectors:
  - cards: only Luhn-valid 13–19 digit runs (arbitrary 16-digit ids are kept);
  - MyKad/IC: undashed 12-digit form with a valid month/day guard;
  - phones: MY `+60`/`60`/`0` with space or dash groups, and generic E.164;
  - credentials in free text: Bearer, JWT, `sk_live_`/`ghp_`/`github_pat_`/`xox?-`/`AKIA…`,
    `password=` / `api_key:` / `token=` values (`csrf_token` untouched);
  - canonical UUIDs are protected anywhere inside any string;
  - IPv4 only when every octet ≤ 255.
- `event_id` / `occurred_at` are preserved only at the top level of the envelope;
  a nested `extra.event_id` is now masked (security-review Low).

### Added
- `ProjectRole::permissions()` / `ProjectRole::can(string $ability)` — the
  project-RBAC ability vocabulary (view, triage, manage_keys, manage_members,
  manage_rules, update, delete) consumed by the web app's `ProjectPolicy`.
- Initial scaffold: service provider with config publishing.
- Domain enums with `label()` / `color()`: IssueStatus, Level, Environment,
  ProjectRole, EndpointType, ReportType, SupportStatus.
- `HasDualKey` trait (UUID public / bigint internal).
- Driver contracts: Fingerprinter, Normalizer, Scrubber, IssueStore,
  OccurrenceStore, EndpointResolver, EntitlementGate, SupportProvider,
  ReportGenerator, Notifier.
