# Installation: Subticket Manager for osTicket 1.18.x

[Deutsche Fassung →](INSTALL.de.md) · [back to README](../README.md)

Target directory: `include/plugins/subticket-manager/` of your osTicket installation. `support.example.com` is the example hostname throughout; `ost_` is the example table prefix. Replace both with your own.

**The order below is binding.** Each step has an abort condition. If a step aborts, fix that step; do not continue and hope the next one compensates.

---

## 0. Before you touch anything

| Check | Abort if |
|---|---|
| osTicket version | not 1.18.x (`isCompatible()` refuses older versions; newer ones are untested) |
| PHP version of the web server | below 7.4, or 8.4 and you cannot tolerate an untested version |
| Database | neither MariaDB 10.3+ nor MySQL 5.7+ |
| `scp/` writable by the PHP user | `ls -ld scp/` shows an owner the PHP process cannot write as, and you would have to `sudo` a web-server-owned path |
| Web server configuration you can edit | you cannot add an exception to the `Deny from all` in `include/.htaccess` (step 3) |
| A restore path | you cannot restore the database and the `include/plugins/` and `scp/` directories within minutes |
| Number of plugin copies | `ls include/plugins/*/class.SubticketPlugin.php` lists more than one file |

**Lockout risk.** The plugin does not touch login, sessions or authentication; it cannot lock you out of osTicket. What it can do: a fatal error in the plugin breaks every staff page, because plugins are bootstrapped on every request. The client portal is not affected. Reaching the admin panel to disable the plugin may then be impossible, which is what the kill switch is for.

**Kill switch.** Works without the admin panel. Deactivate the plugin in the database, then remove the two pages it copied into `scp/`:

```sql
-- table prefix ost_ assumed; check ost-config.php
UPDATE ost_plugin SET isactive = 0 WHERE name = 'Subticket Manager';
```

```bash
cd /path/to/osticket
rm -f scp/subtickets.php scp/ajax-subticket.php scp/.subticket-deployed-version
```

Deactivating stops the bootstrap, the panel and the queue indicator at once. Removing the two pages closes the AJAX endpoint and the overview, which otherwise stay reachable (behind the staff login) while the plugin is disabled. The plugin's tables and the extra column on the ticket table are harmless to leave in place; step 8 removes them.

---

## 1. Get the package

Download `subticket-manager-vX.Y.Z.zip` from the [latest release](https://github.com/studio-prisma/osticket-subticket-manager/releases/latest). Do not install from a source archive of `main`: `main` is not a release, and the source archive lacks `vendor/autoload.php`.

**Abort if** the release has no attached archive, or the version in the filename does not match the tag.

From git instead (development only):

```bash
cd include/plugins
git clone https://github.com/studio-prisma/osticket-subticket-manager.git subticket-manager
cd subticket-manager && composer install --no-dev
```

**Abort if** `composer` is not available on the server; the plugin does not load without `vendor/autoload.php`.

---

## 2. Install the files

```bash
cd /path/to/osticket/include/plugins
unzip /tmp/subticket-manager-vX.Y.Z.zip
chown -R <web-user>:<web-group> subticket-manager
ls subticket-manager/vendor/autoload.php subticket-manager/plugin.php
```

The archive contains the `subticket-manager/` folder; unzipping it into `include/plugins/` is the whole install. The directory name is free (the AJAX handler finds the plugin by its class file), but keep it `subticket-manager` unless you have a reason.

**Abort if** `include/plugins/` already contains a different version of the plugin: remove it first, do not overlay. **Abort if** `vendor/autoload.php` is missing after unzipping.

---

## 3. Allow the plugin's JavaScript to be served

The panel script and the queue indicator are loaded by the browser from `include/plugins/subticket-manager/js/`. osTicket ships `include/.htaccess` with `Deny from all`, and many nginx setups deny `include/` as well. Without an exception the panel renders but every button is dead, and the browser console shows `Failed to load subticket-panel.js`.

**Apache** (with `AllowOverride` in effect for `include/`): create `include/plugins/subticket-manager/js/.htaccess`:

```apache
# Served by the browser; everything else under include/ stays denied.
<IfModule mod_authz_core.c>
    Require all granted
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Allow from all
</IfModule>
```

**nginx**: add a location for the `js/` directory **before** the rule that denies `include/`:

```nginx
location ^~ /include/plugins/subticket-manager/js/ {
    try_files $uri =404;
}
location ^~ /include/ {
    deny all;
}
```

Test from outside:

```bash
curl -sI https://support.example.com/include/plugins/subticket-manager/js/subticket-panel.js | head -1
curl -sI https://support.example.com/include/plugins/subticket-manager/class.SubticketPlugin.php | head -1
```

**Abort if** the first line is not `200` or the second line is `200`. The exception must cover the `js/` directory and nothing else under `include/`.

This file lives outside the plugin package on purpose: it is web server configuration, and a plugin update must not change it.

---

## 4. Install and enable in osTicket

1. **Admin Panel → Manage → Plugins → Add New Plugin**: "Subticket Manager" is listed. Click **Install**.
2. In the plugin list, tick the plugin and choose **Enable**.

Enabling creates the singleton instance, copies `subtickets.php` and `ajax-subticket.php` into `scp/`, and writes `scp/.subticket-deployed-version`. The first staff request afterwards creates the tables `ost_ticket_hierarchy_metadata` and `ost_ticket_progress`, adds the column `version` to `ost_ticket` and two indexes on it.

**Abort if** enabling reports `Target directory not writable`: fix the permissions of `scp/` (step 0) and enable again. **Abort if** the plugin is not listed: `plugin.php` or `class.SubticketPlugin.php` is missing, or the plugin was unzipped one level too deep.

Configuration: **Admin Panel → Manage → Plugins → Subticket Manager → Instances**. The defaults work; the options are described in [README.md](../README.md#configuration). Configure after enabling, not before; the instance exists only once the plugin is enabled.

---

## 5. Smoke test

The install is not finished until every line below produces the stated result. Use a **non-admin agent** with the `ticket.link` role permission and access to one of two departments; an admin passes every check regardless of the permission logic.

| Test | Expected |
|---|---|
| Open any ticket as that agent | Panel **Parent Ticket / Child Tickets** above the ticket tabs |
| Browser console on a queue page: `document.compatMode` | `"CSS1Compat"` (`"BackCompat"` means something is printed before `<!DOCTYPE html>`) |
| Browser console, after loading the ticket page | no `Failed to load subticket-panel.js`, no `Failed to load queue-indicator.js` |
| **Link to Parent** with a ticket number of the agent's own department | panel shows the parent; the queue list shows the code-fork icon with `1` on the parent |
| **Link to Parent** with a ticket number of the other department | refused with a generic message; no subject or status of that ticket in the response |
| **Create Subticket** on the parent, submit the new-ticket form | the new ticket is a child of the parent |
| **Unlink** from the parent | panel shows no parent; icon disappears from the queue list |
| Open the same ticket in the client portal (log in as the ticket's user) | no panel, no parent or child numbers anywhere in the page source |
| `scp/ajax-subticket.php?action=children&tid=<id>` in a private window (logged out) | redirect to the staff login |
| `scp/ajax-subticket.php?action=version` as the non-admin agent | HTTP 403 |
| `scp/subtickets.php` as the agent | the overview page, listing the links made above |

**Abort if** any row disagrees. Use the kill switch from §0 and investigate offline; the web server error log carries `[SUBTICKET-PLUGIN]` lines.

---

## 6. Security gate

Run before declaring the install done. Each row is a decision, not a suggestion.

| Check | Expected | If it differs |
|---|---|---|
| Panel in the client portal | absent | do not go live; the fix for this is in 2.0.5, check the installed version |
| `action=children` for a ticket of a department the agent cannot access | refused, response carries no ticket data | do not go live |
| `action=link` without osTicket's CSRF token (`curl -X POST` with the session cookie only) | refused | do not go live |
| `include/plugins/subticket-manager/class.SubticketPlugin.php` fetched with `curl` | not `200` | the exception from step 3 is too wide |
| `SUBTICKET_DEBUG` in `class.SubticketPlugin.php` | `false` | set it to `false`; the debug log writes subjects and SQL |
| Web server error log | no `[SUBTICKET-PLUGIN]` entries during normal use | read them before going live |

---

## 7. Update

1. Read the [CHANGELOG](../CHANGELOG.md) section of the new version for behaviour changes and removed options.
2. Replace the plugin directory: move the old one away, unzip the new archive, restore ownership. Do not overlay the new files on the old ones.
3. Re-create the `js/.htaccess` from step 3 if you keep it inside the plugin directory (it is not part of the package).
4. Open any staff page. The version change triggers a fresh copy of the two pages into `scp/`; `scp/.subticket-deployed-version` now shows the new version.
5. Run the smoke test (step 5).

**Abort if** `scp/.subticket-deployed-version` still shows the old version after a staff request: `scp/` is not writable, and the old `ajax-subticket.php` serves the new panel.

---

## 8. Uninstall

1. Decide whether the hierarchy data stays. **Remove Data on Uninstall** (plugin configuration) drops the two tables, the `version` column and the two indexes. The `ticket_pid` column is osTicket's own and is never touched; existing parent links survive an uninstall either way.
2. **Admin Panel → Manage → Plugins**: disable, then delete the plugin. Uninstall removes `scp/subtickets.php`, `scp/ajax-subticket.php`, `scp/.subticket-deployed-version` and a `scp/apps.php` left by versions before 2.0.5.
3. Remove the directory and the web server exception:

```bash
cd /path/to/osticket
rm -rf include/plugins/subticket-manager
ls scp/subtickets.php scp/ajax-subticket.php 2>/dev/null   # must print nothing
```

If the two pages are still there, `scp/` was not writable during uninstall; remove them by hand.

---

## Known limitations

- Not verified: the plugin's behaviour on PHP 8.4, on osTicket versions other than 1.18.x, and with a web server that serves `include/` through a front controller. The CI suite runs against mocks of the osTicket classes, not against a live installation; the smoke test above is the live check.
- No automatic status workflow (auto-close, cascading status or assignment).
- `scp/` must be writable on enable and on every update; the two pages stay reachable while the plugin is disabled.
- The admin overview is system-wide and limited to 100 rows.
- One copy of the plugin per installation.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Panel visible, buttons dead, console shows `Failed to load subticket-panel.js` | `include/` denied by the web server | step 3 |
| Enable reports `Target directory not writable` | `scp/` not writable by the PHP user | step 0, then enable again |
| Panel missing, no console error | plugin disabled, client portal instead of staff interface, or browser cache | check Admin Panel → Plugins; hard reload |
| Dialogs open off-screen on long ticket pages, `document.compatMode` is `BackCompat` | something prints before the doctype; the plugin itself stopped doing that in 2.0.4 | check other plugins and custom code in `include/` |
| "Ticket not found" on every link attempt | table prefix other than `ost_` with a plugin version before 2.0.5 | update |
| HTTP 500 from `scp/ajax-subticket.php`, log says `Multiple SubticketPlugin installations` | two copies under `include/plugins/` | keep one |
| HTTP 429 `Too many requests` | the per-session rate limit of the AJAX endpoint | wait the stated seconds; a script hammering the endpoint is the usual cause |
