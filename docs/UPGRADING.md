# Upgrading Modulate

This file documents breaking changes between major versions and how to migrate.

Modulate follows [Semantic Versioning](https://semver.org). Breaking changes only happen on major version bumps. Minor and patch updates are always backward compatible.

---

## Unreleased → v1.0.0

Initial release. No upgrade needed.

---

## v0.3.x → v1.0.0

No breaking changes. This release stabilizes all existing features for production use.

Migration notes:
- No config key renames or removals were introduced.
- Existing `config/modulate.php` files remain valid as-is.
- You can safely update Composer constraints to `hussiensulyman/modulate:^1.0`.

---

*Future upgrade guides will be added here as major versions are released.*
