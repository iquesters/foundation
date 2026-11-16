# Contributing to iquesters/foundation

Thanks for helping! Bug reports, fixes and docs improvements are welcome.

## Before you start

- **Bugs:** open an issue with steps to reproduce, expected vs actual behaviour, and your PHP/Laravel versions.
- **Features:** open an issue first so we can agree on the approach before you write code.
- **Security issues:** never in public issues; see [SECURITY.md](SECURITY.md).

## Branch naming

Every change starts from a GitHub issue. Name the branch after the issue's **label** and **number**:

```
IQU-{label}-{issue-number}
```

`{label}` is the issue's label exactly as it is on GitHub (lowercase), not a fixed list. Examples:

| Issue | Branch |
|---|---|
| #142 labelled `fix` | `IQU-fix-142` |
| #87 labelled `feat` | `IQU-feat-87` |
| #14 labelled `enhancement` | `IQU-enhancement-14` |
| #90 labelled `docs` | `IQU-docs-90` |

- Only the issue number after the label (no titles).
- If the issue has several labels, use the one that describes the kind of work.
- If the label changes before you start, use the new label.
- Reference the issue in the pull request (`Closes #142`) so it closes on merge.

## Development setup

This module has no iquesters dependencies.

```bash
git clone https://github.com/iquesters/foundation.git
cd foundation
composer install
```

To work on several modules together, use a Laravel app with path repositories pointing at your
local clones (symlinked, so edits show up immediately):

```json
"repositories": [
  { "type": "path", "url": "../foundation", "options": { "symlink": true } }
]
```

## Tests

Tests use [Orchestra Testbench](https://packages.tools/testbench) and a MySQL database named
`platform_test` (user `root`, empty password; see `phpunit.xml`).

```bash
composer test
```

## Code style and analysis

```bash
composer format    # Laravel Pint
composer lint      # Pint in check mode
composer analyse   # PHPStan (Larastan)
```

## Pull requests

- One topic per pull request; add or update tests for what you change.
- Update `CHANGELOG.md` (under *Unreleased*) and the relevant `docs/` page.
- Settings that admins change belong in the DB-backed conf (`src/Config/*Conf.php`), not in config files or `.env`.
- New API endpoints go in `routes/api.v1.php` (see foundation `docs/developer/api.md`).
