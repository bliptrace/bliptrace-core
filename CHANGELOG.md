# Changelog

All notable changes to `g8scope/core` will be documented in this file.

## [Unreleased]

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
