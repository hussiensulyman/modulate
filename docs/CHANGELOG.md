# Changelog

All notable changes to Modulate will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Modulate follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

- feat: implement modulate:install and modulate:init commands with full testing
- feat: implement modulate:make and scaffolding commands with full testing
- feat: add module management commands, violation scanner, and lint/check with dry-run

### Planned

- `modulate:make` — scaffold a full module with all folders and files
- `modulate:make-service` — add a Service + Interface to an existing module
- `modulate:make-event` — add an Event + Listener pair to an existing module
- `modulate:make-contract` — add a Contract interface to an existing module
- `modulate:list` — list all registered modules
- `modulate:install` — publish stubs and config
- Auto-discovery of module ServiceProviders
- `--minimal` and `--api-only` flags on `modulate:make`

---

## [0.1.0] — TBD

Initial release.
