# Changelog

All notable changes to this extension are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.1] - 2026-10-29

### Fixed
- Backend module uses the core module layout again: docheader and padded
  module body in TYPO3 13.4 and 14 (the extension's own layout dropped both,
  and with them the flash messages after "Refresh now" and "Send test mail").
  Both buttons now sit in the docheader button bar.
- The "security" chip was unreadable (white text on a light background): the
  danger and warning chips now use the core surface-container colour pairs
  (contrast about 13:1, light and dark mode).
- The trend sparkline is rendered as the last chip of the summary row instead
  of being pushed against the right edge.
- Plain-text email part was HTML-escaped (`&quot;Latest&quot;`, `&amp;` and
  `&lt;` in advisory titles). The HTML part stays escaped.
- "Triggered by" in the module shows translated values (CLI command, manually
  in the backend, scheduler task) instead of `cli` / `manual` / `scheduler`.
- Extension configuration: the tabs are labelled *Privacy*, *Email* and *AI
  summary* (en/de) and the settings are grouped under fitting headings instead
  of "Enable features".
- Scheduler: the task is listed in the group "Composer Report" instead of the
  raw extension key.
- `Documentation/guides.xml` validates against the render-guides schema
  (namespace, TYPO3 docs theme extension).
- README and documentation name the module locations for TYPO3 13.4 and 14.

### Changed
- Documentation: screenshots of frontend and backend (TYPO3 14) in README and manual.
- TER upload comment is generated as plain text from this changelog.

## [1.2.0] - 2026-10-28

First public release, prepared for the TYPO3 Extension Repository and Packagist.

### Added
- TYPO3 14 support next to 13.4 LTS (PHP 8.2 to 8.4).
- CLI command `pxe:composer-report:run` with `--mail` and `--json`; exit code 1
  when security advisories exist, 2 when the run failed (for cron and CI).
- Privacy opt-outs: `resolveLatestViaPackagist` (off = the extension never
  contacts packagist.org itself) and `privateVendors` (vendor prefixes or package
  names that are never sent to Packagist, the advisories API or the AI summary;
  shown as "private, not checked" in the report).
- `composerBinary` setting (full path or `composer` on the PATH, `.phar`
  supported) and `keepRuns` (history cap, default 50, pruned after each run).
- `emailLanguage` (en/de) for the report email and the AI summary; plain-text
  alternative part for the email.
- `anthropicModel` setting (default `claude-haiku-5-5`).
- English/German localisation of the module, email, scheduler task, extension
  configuration and TCA.
- Clear error states in the module and on the CLI: not a composer installation,
  `composer.lock` missing, `proc_open()` disabled, composer binary not found,
  composer failed, Packagist disabled or unreachable; automatic fallbacks are
  reported as notices.
- "Abandoned" flag per package (from composer's own output).
- "Newer major available" section for packages that are current within their
  installed major (previously such packages were not shown at all); they are
  not counted as outdated, `counts.nextMajor` includes them.
- Functional tests for the full pipeline with a fake composer binary, unit tests
  for privacy filtering, the Anthropic request (mocked) and the configuration
  reader; PHPStan level 8, php-cs-fixer (typo3/coding-standards), rector config;
  GitHub Actions CI matrix and TER publishing workflow.

### Changed
- The composer collector runs `composer outdated` twice (plain and
  `--minor-only`), so "latest within the installed major" comes from composer and
  no Packagist lookup of the extension's own is needed in that mode.
- The Packagist collector queries security advisories for all checked direct
  dependencies, not only for outdated ones.
- Backend module uses core backend styling only; "Refresh now" and the test mail
  are POST requests (CSRF-protected routes). Branding reduced to one footer line.
- Scheduler task reports a failed run as a task failure (the run is still stored
  with status "error").
- Service `ComposerReportService::run()` returns a `RunResult` object.
- Author and support links point to pixelegg / github.com/pixelegg/pxe-composer-report.

### Removed
- Design-system CSS and inline styles in the backend module, the unused
  `Schema.yaml` and `Tests/bootstrap-standalone.php`.
- The "Refresh now" action on the optional pxe_hub card (the route is POST only now).

## [1.1.0] - 2026-08-13
- "Latest" means the newest stable version within the installed major; newer
  majors are shown as a separate hint (LatestVersionResolver).
- Backend module JavaScript as an ES module.

## [1.0.0] - 2026-06-16
- Initial version: composer CLI and Packagist collectors with a read-only safety
  test, report builder, run history, backend module with refresh and test mail,
  scheduler task, optional email and optional AI summary, optional pxe_hub card.

[1.2.0]: https://github.com/pixelegg/pxe-composer-report/releases/tag/v1.2.0
[1.1.0]: https://github.com/pixelegg/pxe-composer-report/releases/tag/v1.1.0
[1.0.0]: https://github.com/pixelegg/pxe-composer-report/releases/tag/v1.0.0
