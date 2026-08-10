# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).
## [0.2.1] - 2026-08-10

### Fixed
- Widen fissible/transmark version constraint
## [0.2.0] - 2026-08-09

### Added
- Add PdfReader with heuristic extraction and list support
- Expose PdfWriter options and harden CI/docs cleanup

### Fixed
- Correct two silent structural-corruption bugs from independent review

### Ci
- Run php-cs-fixer on PHP 8.2 minimum
- Clarify the code style runtime
## [0.1.0] - 2026-08-08

### Added
- Add PdfWriter composing HtmlWriter output with dompdf

### Fixed
- Use HTTPS vcs URL and authenticate composer install in CI
- Relax PDF size assertion threshold to match measured baseline

