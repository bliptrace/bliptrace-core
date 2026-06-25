# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

`g8scope/core` is the **Laravel domain package** for g8scope (Developers Hub Sdn Bhd). It holds **all**
Laravel-side domain logic so it is single-sourced: `g8scope-app` (web dashboard) depends on it, and
`g8scope-mcp`'s gated writes delegate to the web app, which runs them through this package. That is
what stops the dashboard and the MCP from drifting (spec §5, §18).

One of five repos (multi-repo build). The platform spec is the source of truth; the component view is
in `docs/architecture.md`.

**Guardrail:** the architecture is locked — do not redesign. Build what the current phase calls for.

## Current state

**Scaffold.** Enums, the `HasDualKey` trait, and driver **contracts** (interfaces) only — the seams.
No concrete drivers, Models, Actions or Observers yet (feature work).

## Commands

| Task | Command |
|---|---|
| Install deps | `composer install` |
| Test | `composer test` / `./vendor/bin/pest` |
| Single test | `./vendor/bin/pest --filter="label"` |
| Lint | `composer lint` / `./vendor/bin/pint` |

Tests run on **Orchestra Testbench** with SQLite `:memory:`.

## Conventions (spec §17)

- **Dual ID** on every model via `HasDualKey` (UUID public / bigint internal); route-binds on `uuid`.
- **Invokable Action** classes; Contracts; Form Requests (in the app); **Observers** for side effects
  (e.g. Project created → provision default endpoint + ingest key).
- **Enums** carry `label()` / `color()`. Every enum case must return non-empty strings (enforced by a
  test) — keep it that way when adding cases.
- **Driver-based seams everywhere** with an array/stub driver for tests. Each contract is bound via
  `config/g8scope.php` `drivers`.
- **PII scrub before anything is stored, returned, or sent to g8desk** — the `Scrubber` runs first
  (`cleaniquecoders/pii-protection` in the app binding).
- **Pest** for tests; British spelling; lowercase `g8` prefix; `declare(strict_types=1)` in every file.
- Namespace `G8scope\Core\`; package name `g8scope/core`.

## The contracts (driver seams) — what each owns

| Contract | Responsibility | Store / backend |
|---|---|---|
| `Fingerprinter` + `Normalizer` | Grouping (spec §11) — shared concept with the Go api | — |
| `Scrubber` | PII scrub | pii-protection |
| `IssueStore` | Issue upsert/status/assign | Postgres (upsert ON CONFLICT project_id+fingerprint) |
| `OccurrenceStore` | Read occurrences + sparkline | ClickHouse (read-only here) |
| `EndpointResolver` | default ▸ custom endpoint | — |
| `EntitlementGate` | `custom_api`/`custom_mcp` + caps/seats | — |
| `SupportProvider` | Attach issue → g8desk ticket | g8desk driver |
| `ReportGenerator` | Snapshot report | pdf/csv driver |
| `Notifier` | Multi-channel alerts | email/Slack/Telegram/webhook |

## Relationship to g8scope-contracts

The error-envelope **shape** is owned by `g8scope-contracts` (JSON Schema). When this package needs
envelope DTOs, derive them from that pinned schema — do not redefine the envelope here.

## Phase scope (features matrix → this package)

| Phase | Build |
|---|---|
| 🟢 MVP | Enums, dual-ID, `Fingerprinter`+PHP/JS `Normalizer`, `Scrubber`, `IssueStore`, `OccurrenceStore`, `Notifier` + channels, new-issue/regression alert logic, smart grouping, Project Observer, audit log |
| 🔵 P2 | `EntitlementGate` + flags/caps, `EndpointResolver`, `SwitchEndpoint`/`InviteMember`/`RequestSupport`/`GenerateReport` Actions, `SupportProvider` (g8desk), `ReportGenerator`, incidents/MTTR |
| 🟣 P3 | SLO/anomaly alert strategies, more `Normalizer`s (Python/Go), release health |

## Do NOT

Reimplement mutations outside this package (the MCP must delegate, not fork). Add envelope field
definitions here (they live in `g8scope-contracts`). Write occurrences (the Go api does that).
