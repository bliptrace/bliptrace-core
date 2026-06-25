# Changelog

All notable changes to `g8scope/core` will be documented in this file.

## [Unreleased]

### Added
- Initial scaffold: service provider with config publishing.
- Domain enums with `label()` / `color()`: IssueStatus, Level, Environment,
  ProjectRole, EndpointType, ReportType, SupportStatus.
- `HasDualKey` trait (UUID public / bigint internal).
- Driver contracts: Fingerprinter, Normalizer, Scrubber, IssueStore,
  OccurrenceStore, EndpointResolver, EntitlementGate, SupportProvider,
  ReportGenerator, Notifier.
