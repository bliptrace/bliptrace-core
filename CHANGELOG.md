# Changelog

All notable changes to `g8scope/core` will be documented in this file.

## [Unreleased]

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
