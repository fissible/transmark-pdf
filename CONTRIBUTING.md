# Contributing to transmark-pdf

## Commit messages — Conventional Commits

All commits must follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

```
<type>: <short description>

[optional body]

[optional footer: BREAKING CHANGE: ...]
```

### Types

| Type | When to use | Version bump |
|---|---|---|
| `feat` | New feature or capability | minor |
| `fix` | Bug fix | patch |
| `refactor` | Code change that isn't a fix or feature | none |
| `test` | Adding or updating tests | none |
| `docs` | Documentation only | none |
| `chore` | Tooling, deps, release commits | none |
| `perf` | Performance improvement | none |
| `style` | Formatting, whitespace | none |
| `feat!` / `BREAKING CHANGE:` | Incompatible API change | major |

### Examples

```
feat: add page-margin option to PdfWriter
fix: correct MediaBox dimensions for legal paper size
docs: add DocxReader usage example to README
chore: release v0.2.0
```

---

## Branching

- `main` is always releasable
- Work on feature branches: `feat/<name>`, `fix/<name>`, `chore/<name>`
- Open a PR to merge into `main`; don't push directly

---

## Test-driven development

Write the test before the implementation. No exceptions.

1. Write a failing test that describes the desired behaviour
2. Confirm it fails for the right reason
3. Write the minimal code to pass it
4. Confirm all tests pass
5. Refactor if needed — keep tests green

Tests live in `tests/`, mirroring the `src/` namespace. Run the full suite with:

```bash
composer install
composer test        # vendor/bin/phpunit
composer cs          # vendor/bin/php-cs-fixer fix --dry-run --diff
```

A PR without tests for new behaviour will not be merged.

## Scope

This package is a thin composition layer: `PdfWriter` runs `fissible/transmark`'s
`HtmlWriter::write()` to get HTML, then feeds that HTML through `dompdf/dompdf`
(`loadHtml()` → `setPaper()` → `render()` → `output()`). It does not implement any
document-model or numbering logic of its own — that lives upstream in
`fissible/transmark`. Changes here should stay within that adapter role; new
document-model concepts belong in `fissible/transmark`, not this repo.

---

## Releasing

See [fissible/.github's RELEASE.md](https://github.com/fissible/.github/blob/main/RELEASE.md)
for the full release process. Use `release.sh` at the root of this repo to cut a release.
