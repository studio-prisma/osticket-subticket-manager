# Security Policy

## Supported Versions

Only the latest minor release of this fork receives security fixes. Older tags are archived, not maintained.

| Version | Supported |
|---|---|
| 2.0.x | yes |
| everything older | no |

The upstream project [markus-michalski/osticket-subticket-manager](https://github.com/markus-michalski/osticket-subticket-manager) has its own release line. Fixes made here are offered upstream as pull requests; whether and when they land there is the upstream maintainer's decision.

## Reporting a Vulnerability

Use **[GitHub Security Advisories](https://github.com/studio-prisma/osticket-subticket-manager/security/advisories/new)**, which are private by default. Do not open a public issue for a suspected vulnerability.

Include:

- the plugin version, the osTicket version and the PHP version
- what an agent, a client or an unauthenticated visitor gains, not only what misbehaves
- reproduction steps, ideally against a clean osTicket 1.18 install with two departments and a non-admin agent

Expected response: an acknowledgement within 7 days, an assessment within 30. This is a spare-time project, not a vendor with an on-call rotation. That is the honest number, not a target.

Please do not include ticket contents, customer data or database dumps in a report.

## In Scope

The plugin exposes an AJAX endpoint (`scp/ajax-subticket.php`), an admin overview (`scp/subtickets.php`), a panel in the staff ticket view and a session hand-off from the ticket view into the new-ticket form. A real finding looks like one of these:

- **Access to a ticket outside the agent's permission.** An agent reads, links or unlinks a ticket they may not view, through the AJAX endpoint or through the `subticket_parent` session hand-off. The reference is osTicket's own `Ticket::checkStaffPerm()`; anything the plugin allows beyond that is a finding.
- **The panel or any hierarchy data in the client portal.** The panel, parent or child numbers, subjects or statuses rendered for a client or an unauthenticated visitor.
- **Cycle protection bypass.** A link that makes a ticket its own ancestor, or a chain that escapes the depth and children limits in a way the configuration does not allow.
- **Information disclosure in AJAX responses.** File system paths, stack traces, SQL, PHP or server details, or data of tickets the agent may not view, in any response of `scp/ajax-subticket.php`.
- **Injection.** SQL built from a request parameter without `(int)` or `db_input()`, or HTML in the panel or the admin overview that is not escaped.
- **CSRF on a writing action** (`link`, `unlink`) without osTicket's CSRF token.

## Out of Scope

Documented design decisions are not findings. Naming them here saves everyone a round trip.

- **Vulnerabilities in osTicket core.** Report those to the [osTicket project](https://github.com/osTicket/osTicket/security).
- **`batch_parent_status` reveals that a ticket has children.** The action returns the number of children for a list of ticket ids and nothing else; it exists so the queue indicator can be drawn. It does not return ticket data.
- **The admin overview is system-wide.** `scp/subtickets.php` lists all parent/child relationships of the installation to every agent. That is the page's purpose, and it shows ticket numbers and statuses only.
- **Anything requiring an already-compromised agent account, admin account or server.**
- **Findings in development dependencies** (`phpunit`, `mockery`, `phpstan`). They are not part of the release package; Dependabot tracks them.
- **The plugin stays reachable after it is disabled.** `scp/ajax-subticket.php` and `scp/subtickets.php` remain in osTicket's `scp/` directory until the plugin is uninstalled; both sit behind `staff.inc.php`. Documented in [docs/INSTALL.md](docs/INSTALL.md).
- **Automated scanner output without a working proof of concept.**

## Handling of Secrets

The plugin has no credentials of its own and stores none. No credentials belong in this repository, not in tests, not commented out, not in fixtures. `tools/build-zip.sh` refuses to build when a literal secret or a local absolute path is found in the staged files. A secret that reached a commit is rotated first and removed second; the history stays readable either way.

The debug log (`SUBTICKET_DEBUG`, off by default) writes ticket subjects and SQL to a file in the system temp directory with mode 0600. Do not enable it on a shared host and do not attach it to a public issue.

## Disclosure

Coordinated. A fix ships first, the advisory follows once a patched release is available. Reporters are credited unless they ask not to be.
