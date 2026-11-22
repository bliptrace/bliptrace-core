# g8scope/core

The **domain layer** for [g8scope](https://g8suite.com) — the central, runtime-agnostic
error-capture platform by Developers Hub Sdn Bhd. A Laravel composer package.

> `bliptrace-app` (the web dashboard) depends on this package. The `bliptrace-mcp` server delegates its
> gated writes to the web app, which executes them through this package — so **mutations, audit and
> PII scrub are single-sourced here** and the dashboard never drifts from the MCP. See spec §5, §18.

## Status

**Scaffold.** Ships the seams — enums, the dual-ID trait, and driver contracts — plus config
publishing and a Pest/Testbench suite. Concrete drivers, Actions, Observers and Models are the
feature work (tracked as issues).

## Requirements

- PHP ^8.5 · Laravel ^13.0

## Install

```bash
composer require g8scope/core
php artisan vendor:publish --tag="g8scope-config"
```

## What's inside

| Group | Items |
|---|---|
| Enums (`label()`/`color()`) | `IssueStatus`, `Level`, `Environment`, `ProjectRole`, `EndpointType`, `ReportType`, `SupportStatus` |
| Concern | `HasDualKey` — UUID public / bigint internal (spec §17) |
| Contracts (driver seams) | `Fingerprinter`, `Normalizer`, `Scrubber`, `IssueStore`, `OccurrenceStore`, `EndpointResolver`, `EntitlementGate`, `SupportProvider`, `ReportGenerator`, `Notifier` |

Each contract is bound to a concrete driver by the app (array/stub driver in tests) — see
`config/g8scope.php`.

## Testing

```bash
composer test     # vendor/bin/pest — Orchestra Testbench, SQLite :memory:
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE).

---

_Developers Hub Sdn Bhd · devhub.my · g8scope/core_
