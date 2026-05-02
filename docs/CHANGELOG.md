# Changelog

All notable changes to Modulate will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Modulate follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

- feat: add VS Code extension MVP for inline violation highlighting

---

## [0.2.2] — 2026-05-02

- feat: add reusable GitHub Action for modulate:lint in CI pipelines

---

## [0.2.1] — 2026-05-02

- feat: add AST-based violation detection with nikic/php-parser (optional, backward compatible)

---

## [0.2.0] — 2026-05-02

- feat: implement modulate:doctor compatibility scanner skeleton
- feat: complete modulate:doctor compatibility scanner with YAML parsing and colored output

---

## [0.1.0] — 2026-05-01

- ci: add GitHub Actions workflow for multi-version PHP/Laravel testing
- feat: implement modulate:install and modulate:init commands with full testing
- feat: implement modulate:make and scaffolding commands with full testing
- feat: add module management commands, violation scanner, and lint/check with dry-run
- feat: add health, graph, extract commands and finalize Phase 1 documentation
