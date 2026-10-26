# Contributing

Thanks for taking the time. This is a fork of [markus-michalski/osticket-subticket-manager](https://github.com/markus-michalski/osticket-subticket-manager), maintained in spare time. The rules below exist so a pull request can be reviewed in one sitting.

## Before you open a pull request

- Open an issue first for anything beyond a typo. A rejected PR wastes more of your time than a rejected issue.
- Check whether the change belongs upstream. Fixes that are not specific to this fork are offered upstream as well; say in the issue if you intend to do that yourself.
- One change per PR. A refactor bundled with a fix is two PRs.
- Security issues never go into a public PR or issue. See [SECURITY.md](SECURITY.md).
- Security-relevant changes need a threat-model note. If your change touches `TicketAccessGuard`, the AJAX controller, the `subticket_parent` hand-off, SQL or `AssetDeployer`, describe in the PR what an agent or a client could do afterwards that they could not do before, and why that is acceptable. The PR template asks for it.

## Development setup

PHP 7.4 or newer and [Composer](https://getcomposer.org/).

```bash
git clone https://github.com/studio-prisma/osticket-subticket-manager.git
cd osticket-subticket-manager
composer install
vendor/bin/phpunit
```

The suite runs against mocks of the osTicket classes in `tests/mocks/` and needs no database and no osTicket checkout. `composer.lock` is pinned to the PHP 7.4 platform so the same dependency set installs on every supported version.

To build the install package:

```bash
composer install --no-dev          # vendor/ must not contain phpunit & co.
./tools/build-zip.sh               # writes dist/subticket-manager-v<version>.zip
```

The script refuses to build when a class under `src/` is missing from `.releaseinclude`, when `vendor/` is a development install, or when a literal secret or a local absolute path is found in the staged files.

## What a change has to pass

| Check | Command |
|---|---|
| Syntax, all production files | `find . -name '*.php' -not -path './vendor/*' -not -path './tests/*' -exec php -l {} \;` |
| Unit tests | `vendor/bin/phpunit` |
| Composer manifest | `composer validate --strict` |
| Release package | `composer install --no-dev && ./tools/build-zip.sh` |

CI runs the same checks on PHP 7.4 to 8.3 on every push and pull request, plus `actionlint` for the workflows. The aggregating job is called **Gate**; it is the only status check the branch ruleset requires, and it is green only when every other job is.

A red pipeline is not mergeable. If a check fails for a reason unrelated to your change, say so in the PR instead of rerunning until it passes.

`composer analyse` runs PHPStan at level 8. It is configured but not enforced, and it reports existing findings; do not add new ones in the files you touch.

For anything touching access checks, the hand-off or the panel, also verify against a **real osTicket 1.18.x instance as a non-admin agent** with access to one of two departments. The mocks mirror osTicket's signatures, not its permission model.

## Code conventions

- **PHP 7.4 compatible.** `isCompatible()` refuses anything older, and CI lints on 7.4. No `match`, no enums, no readonly properties, no named arguments, no `never` return type.
- **No new runtime dependency.** `vendor/` in the release package is the Composer autoloader and nothing else. Keep it that way.
- **Every table name goes through `Table::name()`.** osTicket's table prefix is configurable; `TablePrefixTest` fails on a hardcoded `ost_`.
- **Every access decision goes through `TicketAccessGuard`.** Do not re-implement department or role logic; osTicket's `Ticket::checkStaffPerm()` is the reference.
- **Failures are generic to the client, specific to the log.** Exception messages, file paths and SQL never reach the browser.
- **SQL takes `(int)` or `db_input()` values only.** No string building with request data.
- **Every new configuration option is read somewhere, validated in `pre_save()` and listed in the configuration table of both READMEs.** Options nobody reads were removed once; they do not come back.
- **A new file under `src/` is listed in `.releaseinclude`.** CI builds the package on every pull request and fails otherwise.
- **No hostnames, credentials, absolute paths or ticket data**, including in tests. Use `example.com` and the reserved documentation ranges.

## Documentation

Documentation is part of the change, not a follow-up.

- Behaviour change: update `README.md` **and** `README.de.md` in the same PR.
- Install, update or configuration change: update `docs/INSTALL.md` **and** `docs/INSTALL.de.md`.
- New limitation discovered: add it to "Known limitations". A limitation users find in production is a bug report; one they read in advance is a decision.
- Add a `CHANGELOG.md` entry under `## [Unreleased]` using the Keep a Changelog categories (`Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`).

If you are not comfortable writing the German version, write the English one and say so in the PR. The translation is handled during review rather than blocking your change.

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org/), imperative, English:

```
fix(security): check ticket access on every subticket operation
fix: respect the configured table prefix in every query
docs(install): describe the kill switch without a working UI
ci: pin actions to major versions
chore(release): v2.0.6
```

Pull requests are squash-merged; the PR title becomes the commit message, so write it like one.

## Release process

Maintainer only.

1. `## [Unreleased]` becomes `## [X.Y.Z] - YYYY-MM-DD`; add a fresh empty `Unreleased` block and a compare link at the bottom.
2. Bump `'version'` in `plugin.php`. It lives in exactly one place; the release workflow fails if the tag and the manifest disagree.
3. `git commit -m "chore(release): vX.Y.Z"`, merge via PR.
4. `git tag -a vX.Y.Z -m "vX.Y.Z"` from the merged `main`, push the tag.
5. The `Release` workflow installs the runtime autoloader, builds `subticket-manager-vX.Y.Z.zip` with `tools/build-zip.sh`, takes the notes from the changelog section and publishes the GitHub release.

Tags are protected: they cannot be moved or deleted. A wrong tag is corrected with the next patch version, never by overwriting.

## License

The plugin is written by [Markus Michalski](https://github.com/markus-michalski) and licensed under the [GNU General Public License v2.0](LICENSE), as required for code that extends osTicket. By contributing you agree that your contribution is licensed under the same terms.
