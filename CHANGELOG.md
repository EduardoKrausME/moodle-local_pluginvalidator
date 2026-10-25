# Changelog

This changelog documents **only the two most recent published GitHub releases** of `local_pluginvalidator`. Each entry reflects the verified Git diff between its tag and the immediately preceding **published release** tag. Changes from other versions are not attributed to these releases.

## [1.5.16] - 2026-10-23

Published: 2026-10-23 22:41:55 UTC.

Comparison: [1.5.15...1.5.16](https://github.com/EduardoKrausME/moodle-local_pluginvalidator/compare/1.5.15...1.5.16)

### Changed: release workflow

- Added a GitHub Actions release-specific concurrency configuration in `.github/workflows/release.yml`.
- Set the concurrency group to `release-${{ github.ref }}`, with `cancel-in-progress: false` and `queue: max`. These settings control the release workflow queue; they do not change plugin validation behavior.

### Maintenance and template cleanup

- Removed the unused `use core_text;` import from `classes/plugin_repository.php`. The published-tag diff does not change any executable statements in that class.
- Removed empty `Example context (json)` examples from the Mustache comment headers of `templates/index.mustache`, `templates/plugin.mustache`, and `templates/plugins.mustache`. These are comment-only changes and do not alter rendered template markup.
- Updated `version.php` from numeric plugin version `2026101501` / release `1.5.15` to version `2026102300` / release `1.5.16`. The minimum Moodle build remains `2022112800`.

### Scope

The comparison contains four commits and changes only `.github/workflows/release.yml`, `classes/plugin_repository.php`, three Mustache templates, and `version.php`. It provides no evidence of new validator checks, changed scanning algorithms, changed validation results, or new user-facing features.

## [1.5.15] - 2026-10-15

Published: 2026-10-15 20:28:07 UTC.

Comparison: [1.5.14...1.5.15](https://github.com/EduardoKrausME/moodle-local_pluginvalidator/compare/1.5.14...1.5.15)

### Changed: release metadata

- Updated `version.php` from numeric plugin version `2026101500` / release `1.5.14` to numeric version `2026101501` / release `1.5.15`.
- The minimum required Moodle build remained `2022112800`, and the plugin component and stable maturity metadata were unchanged.

### Scope

The comparison contains a single commit, `b80567755458e23ea2bac6acad9242f5a3799a81`, and modifies **only `version.php`**. No executable plugin source, validation logic, templates, tests, or workflows changed in this published release.

---

These entries are based exclusively on published Git tag comparisons. No tests or deployments were executed, and no existing release descriptions were edited, while preparing this changelog.
