# Changelog

All notable changes to `g8scope/core` will be documented in this file.

## [Unreleased]

## [0.3.4] - 2026-11-25

### Changed
- Seats are sold as a Stripe subscription: `StripeBillingProvider` opens Checkout in `mode=subscription` for
  `seats` (metadata mirrored onto the Subscription); endpoint unlocks stay one-time `mode=payment` (ADR-002:
  Stripe only, USD). `STRIPE_PRICE_SEAT` must now be a **recurring** USD Price.

### Added
- `RevokeSeats` action and handling for `customer.subscription.deleted`: seats bought through the subscription
  are taken back once (idempotent via `billing_events`), never below the owner's seat.

## [0.3.3] - 2026-11-23

### Changed
- New ingest keys use the prefix `blip_` (`IssueIngestKey::PREFIX`); the old `g8s_` is kept as
  `IssueIngestKey::LEGACY_PREFIX`. Existing `g8s_` keys keep working — the ingest API looks keys up by hash only.
- The default key provisioned on project creation and the key re-issued by `SwitchEndpoint` now go through
  `IssueIngestKey`, so they carry the prefix too.

### Added
- Migration `add_prefix_to_ingest_keys`: `ingest_keys.prefix` (string 16, default `g8s_`), so existing rows
  backfill to `g8s_` and keep displaying as `g8s_…`; new keys store `blip_` explicitly. Hosts should show
  `$key->prefix` instead of the constant.

## [0.3.2] - 2026-11-23

### Changed
- Environment variables renamed from `G8SCOPE_*` to `BLIPTRACE_*` (same suffix): `BLIPTRACE_API_URL`,
  `BLIPTRACE_MCP_URL`, `BLIPTRACE_BILLING_DRIVER`, `BLIPTRACE_BILLING_SUCCESS_URL`, `BLIPTRACE_BILLING_CANCEL_URL`,
  `BLIPTRACE_BILLING_TAX`, `BLIPTRACE_DEFAULT_FREE_SEATS`, `BLIPTRACE_G8DESK_URL`/`_TOKEN`/`_ORG`/`_PRODUCT`,
  `BLIPTRACE_OCCURRENCES_RETENTION_DAYS`, and the test-only `BLIPTRACE_PG_*`. The old `G8SCOPE_*` names are still
  read as a fallback and will be removed once production is switched. Config keys (`config('g8scope.*')`) are unchanged.

## [0.3.1] - 2026-11-22

### Added
- `PurgeProject` action: permanently deletes a project after it has been archived, purging the
  FK-less append-only tables (`occurrences`, `web_vitals`, `transactions`, `metric_points`, plus any
  host-app tables passed in) in chunks by `project_id`, then force-deleting the project row (FK
  tables cascade). Run it delayed after `ArchiveProject`, never in place of it: the Go ingest caches
  key lookups, so an immediate hard delete breaks the next multi-tenant flush on the issues FK.

## [0.3.0] - 2026-11-22

### Added
- Integration API support (bliptrace-app `/api/v1`):
  - migration `add_integration_api_support`: `projects.external_ref` (partial unique per
    account among live projects), `projects.environment`, `projects.deleted_at`;
    `issues.regressed_at`; new `deploys` table + `Deploy` model (`Project::deploys()`).
  - Actions: `CreateProject` (idempotent on `external_ref`, drops the observer's
    unrevealable key), `IssueIngestKey` (`g8s_` prefix, hash only, plain returned once),
    `RevokeIngestKey` (idempotent), `ArchiveProject` (soft delete + revoke keys),
    `RecordDeploy`.
  - `PostgresOccurrenceStore::countsSince()` — per-project event counts for a window.

### Changed
- `Project` uses `SoftDeletes`; archived projects drop out of every Eloquent query.
- `EloquentIssueStore` stamps `regressed_at` when a resolved issue reoccurs. The Go ingest
  (`bliptrace-api`) does not set it yet — follow-up.

## [0.2.3] - 2026-11-03

### Fixed
- Scrubber parity with the Go ingest scrubber (`bliptrace-api` 4af40fa); the Pest vectors
  now mirror `scrub_test.go` TestText / TestScrub one for one (same names, order, outputs):
  - free-text `<word>[=:]value` uses the same key rule as key names (so `monkey=5`,
    `keyboard_layout=us` are kept, `X-Api-Key: …` is masked); a `Bearer `/`Basic ` prefix is
    skipped; a value cannot start with `:` (`Authenticate::handle` is kept);
  - `auth` redacts as a key substring (as in Go); `key` only as a whole `_` token;
  - decoding matches Go: tokens containing `%`, `&`, `\u0040` or `\x40`; HTML-unescaped,
    then path- and query-unescaped candidates, up to 3 levels; masked by character count;
  - an integral float (`4111111111111111.0`) is scanned as plain digits;
  - truncation at 64 KiB never splits a UTF-8 character.

### Known gaps
- camelCase keys (`accessKey`) are not split, so they are not redacted by name (same in Go).

## [0.2.2] - 2026-11-03

Security-review fixes F1–F5. The scrubber changes mirror `bliptrace-api/internal/scrub`;
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
  (`bliptrace-api/internal/scrub`), tested against the same vectors:
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
