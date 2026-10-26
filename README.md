# Subticket Manager Plugin for osTicket

[![Validate](https://github.com/studio-prisma/osticket-subticket-manager/actions/workflows/validate.yml/badge.svg)](https://github.com/studio-prisma/osticket-subticket-manager/actions/workflows/validate.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](LICENSE)

Parent/child ticket links for osTicket 1.18: a panel in the staff ticket view to link and unlink tickets, a code-fork indicator in queue lists, and a hand-off from a parent ticket into the new-ticket form.

**[Deutsche Fassung →](README.de.md)**

> This is the [studio-prisma](https://github.com/studio-prisma) fork of [markus-michalski/osticket-subticket-manager](https://github.com/markus-michalski/osticket-subticket-manager). It carries the security and compatibility fixes listed in the [CHANGELOG](CHANGELOG.md); the fixes are offered upstream as pull requests. Releases of this fork: [github.com/studio-prisma/osticket-subticket-manager/releases](https://github.com/studio-prisma/osticket-subticket-manager/releases). The upstream [documentation and FAQ](https://faq.markus-michalski.net/en/osticket/subticket-manager) describe the plugin as a whole; where this README differs, this README applies to the fork.

---

## What it does

osTicket stores a parent for every ticket (`ticket_pid`) and has API methods for it, but no user interface. This plugin adds the interface:

- **Panel in the ticket view**: parent ticket and child tickets above the ticket tabs, with link, unlink and "Create Subticket" actions.
- **Queue indicator**: a code-fork icon with the number of children next to parent tickets in every queue list.
- **Parent badge**: "Parent Ticket (X Sub-Tickets)" below the ticket number.
- **Hand-off into the new-ticket form**: "Create Subticket" opens osTicket's regular new-ticket form; the created ticket is linked to the parent.
- **Admin overview**: `scp/subtickets.php` lists the hierarchies of the installation.
- **Permission-aware**: every read, link and unlink goes through osTicket's own `Ticket::checkStaffPerm()` and the `ticket.link` role permission. Tickets the agent may not view are not shown.
- **Configurable limits**: maximum depth, maximum children per parent, nesting, open-parent requirement.
- **No core modifications**: the plugin uses osTicket's signals and copies two pages into `scp/`; it patches no core file.

---

## Requirements

- osTicket **1.18.x**. `isCompatible()` refuses anything older.
- PHP **7.4 to 8.3**. CI lints and tests on every version in that range; 8.4 is not in the matrix.
- MariaDB **10.3+** or MySQL **5.7+**. Uninstall cleanup is written for both.
- The web server must serve `include/plugins/<plugin dir>/js/`. osTicket ships `include/.htaccess` with `Deny from all`; see [docs/INSTALL.md](docs/INSTALL.md#3-allow-the-plugins-javascript-to-be-served).
- `scp/` must be writable by the PHP user when the plugin is enabled and after every update.

---

## Install

Full guide with abort points, kill switch and smoke test: **[docs/INSTALL.md](docs/INSTALL.md)** ([deutsch](docs/INSTALL.de.md)).

Short version:

1. Download `subticket-manager-vX.Y.Z.zip` from the [latest release](https://github.com/studio-prisma/osticket-subticket-manager/releases/latest) and unzip it into `include/plugins/`. The archive contains the `subticket-manager/` folder and the Composer autoloader; nothing has to be built.
2. Allow the web server to serve `include/plugins/subticket-manager/js/` (osTicket's `include/.htaccess` denies it).
3. **Admin Panel → Manage → Plugins → Install**, then **Enable**. Enabling copies `subtickets.php` and `ajax-subticket.php` into `scp/`.
4. Open a ticket: the **Parent Ticket / Child Tickets** panel sits above the ticket tabs.

Installing from git instead of the release package requires `composer install --no-dev` inside the plugin directory; `vendor/autoload.php` is not in the repository.

---

## Usage

**Create a subticket.** Open the parent ticket, click **Create Subticket** in the Child Tickets section. osTicket's new-ticket form opens; the ticket created there is linked to the parent. The hand-off expires after 10 minutes and is used once.

**Link an existing ticket.** Open the ticket that is to become a child, click **Link to Parent**, enter the parent's ticket number or id, confirm.

**Unlink.** In the child's Parent Ticket section click **Unlink from Parent**, or in the parent's Child Tickets list click **Unlink** next to the child.

**Indicators.** Queue lists show a code-fork icon with the child count on parent tickets; the ticket view shows the parent badge below the ticket number.

Who may link or unlink is decided by osTicket: the agent needs access to **both** tickets and the `ticket.link` permission in the effective role.

---

## Configuration

The defaults work out of the box. **Admin Panel → Manage → Plugins → Subticket Manager → Instances** offers these options:

| Option | Default | Effect |
|---|---|---|
| Maximum Hierarchy Depth | 3 | How many levels a chain of subtickets may have below a root ticket (1-10). Linking beyond that depth is refused. |
| Maximum Children per Parent | 50 | Linking a further subticket to a parent at the limit is refused (1-1000). |
| Require Parent Ticket to be Open | on | Linking to a parent that is not open is refused. |
| Allow Nested Subtickets | on | When off, a subticket cannot become a parent and a parent cannot itself be a subticket. |
| Remove Data on Uninstall | off | Drops the plugin's tables, the `version` column and the two indexes on uninstall. Cannot be undone. |

Values outside the stated ranges are rejected when the configuration is saved.

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

100 unit tests run against mocks of the osTicket classes (`tests/mocks/`): access checks for every AJAX action and the hand-off, cycle and limit enforcement, the table prefix (a subprocess with `TABLE_PREFIX=acme_`), the queue indicator injection into `<head>`, the panel rendering, and the plugin directory resolution of the AJAX handler. CI runs them on PHP 7.4 to 8.3 and builds the release package on every pull request.

**Verified against a live osTicket 1.18.4** (PHP 8.2, Apache 2.4 with `AllowOverride All`, MariaDB 10.11, table prefix `acme_`), installed from the v2.0.5 release package with the fixes of the following release, as a non-admin agent with access to one of two departments: panel in the ticket view, `document.compatMode === "CSS1Compat"`, link and unlink within the agent's department, link to a ticket of another department refused without disclosing it, cycle refused, hand-off from **Create Subticket** links the new ticket, queue icon with child count, admin overview, client portal without panel or hierarchy data, logged-out endpoint redirects to the login, `action=version` answers 403 to a non-admin, writing actions without the CSRF token answer 400. The smoke test in [docs/INSTALL.md](docs/INSTALL.md#5-smoke-test) is that list; run it after installing on your own instance.

**Not verified:** PHP 8.4, osTicket versions other than 1.18.4, nginx, and the update path from a version before 2.0.5.

---

## Known limitations

- **No automatic status workflow.** Auto-close of the parent, cascading status or assignment are not implemented. Earlier versions offered options for them that nothing read; they were removed in 2.0.5.
- **`scp/` must be writable by the PHP user.** The two pages are copied into osTicket's `scp/` directory on enable and again whenever the plugin version changes. A read-only `scp/` leaves the panel without its AJAX endpoint.
- **JavaScript is served from `include/plugins/`.** The panel script and the queue indicator are loaded from `include/plugins/<plugin dir>/js/`. osTicket's `include/.htaccess` denies that path; without an exception for the `js/` directory the panel renders but does nothing, and the queue icons stay empty.
- **The deployed pages outlive a disabled plugin.** `scp/subtickets.php` and `scp/ajax-subticket.php` stay in place until the plugin is uninstalled. Both sit behind osTicket's staff login.
- **The admin overview is system-wide** and shows the first 100 relationships to every agent, by design. It shows ticket numbers and statuses only.
- **One copy of the plugin per installation.** The AJAX handler finds the plugin by searching `include/plugins/*/class.SubticketPlugin.php`; a second copy makes it stop with a 500.
- **PHP 8.4 is not in the CI matrix.**

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Panel is visible but buttons do nothing; queue icons missing | Browser console shows `Failed to load subticket-panel.js`: the web server denies `include/plugins/<plugin dir>/js/`. See [docs/INSTALL.md](docs/INSTALL.md#3-allow-the-plugins-javascript-to-be-served). |
| Panel not visible in the ticket view | Plugin enabled under Admin Panel → Plugins? You are in the staff interface, not the client portal? Browser cache cleared? |
| "No parent ticket" shown but the relationship exists | `SELECT ticket_pid FROM <prefix>ticket WHERE ticket_id = X` in the database. If it is `NULL`, there is no relationship; unlink and link again. |
| Cannot link or create a subticket | The agent needs `ticket.link` and access to both tickets; the parent must be open unless **Require Parent Ticket to be Open** is off; depth and children limits must not be reached; the hand-off expires after 10 minutes. The web server error log shows `[SUBTICKET-PLUGIN]` lines. |
| Dialogs (due date, print) open off-screen on long ticket pages | Fixed in 2.0.4; `document.compatMode` in the browser console must read `CSS1Compat`. |
| Every lookup fails with "Ticket not found" | Fixed in 2.0.5 for installations with a table prefix other than `ost_`. |
| HTTP 500 from `scp/ajax-subticket.php` | More than one copy of the plugin under `include/plugins/`. Keep one. |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: see [SECURITY.md](SECURITY.md), never a public issue.

Issues that are not specific to this fork belong [upstream](https://github.com/markus-michalski/osticket-subticket-manager/issues).

## License

[GNU General Public License v2.0](LICENSE), compatible with osTicket core. The plugin is written by [Markus Michalski](https://github.com/markus-michalski); this fork is maintained by studio-prisma.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.
