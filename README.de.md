# Subticket Manager Plugin für osTicket

[![Validate](https://github.com/studio-prisma/osticket-subticket-manager/actions/workflows/validate.yml/badge.svg)](https://github.com/studio-prisma/osticket-subticket-manager/actions/workflows/validate.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](LICENSE)

Eltern-/Kind-Verknüpfungen für osTicket 1.18: ein Panel in der Agenten-Ticketansicht zum Verknüpfen und Lösen, ein Code-Fork-Symbol in den Queue-Listen und eine Übergabe vom Elternticket in das Formular für neue Tickets.

**[English version →](README.md)**

> Inhaltsgleich mit `README.md`. Wer eine Fassung ändert, ändert beide im selben PR.

> Dies ist der [studio-prisma](https://github.com/studio-prisma)-Fork von [markus-michalski/osticket-subticket-manager](https://github.com/markus-michalski/osticket-subticket-manager). Er enthält die im [CHANGELOG](CHANGELOG.md) aufgeführten Sicherheits- und Kompatibilitätskorrekturen; die Korrekturen werden Upstream als Pull Requests angeboten. Releases dieses Forks: [github.com/studio-prisma/osticket-subticket-manager/releases](https://github.com/studio-prisma/osticket-subticket-manager/releases). Die Upstream-[Dokumentation und FAQ](https://faq.markus-michalski.net/de/osticket/subticket-manager) beschreiben das Plugin insgesamt; wo diese README abweicht, gilt für den Fork diese README.

---

## Was es tut

osTicket speichert zu jedem Ticket ein Elternticket (`ticket_pid`) und hat API-Methoden dafür, aber keine Oberfläche. Dieses Plugin liefert die Oberfläche:

- **Panel in der Ticketansicht**: Elternticket und Kindtickets oberhalb der Ticket-Tabs, mit Verknüpfen, Lösen und „Create Subticket".
- **Queue-Indikator**: ein Code-Fork-Symbol mit der Zahl der Kinder neben Elterntickets in jeder Queue-Liste.
- **Eltern-Badge**: „Parent Ticket (X Sub-Tickets)" unter der Ticketnummer.
- **Übergabe in das Formular für neue Tickets**: „Create Subticket" öffnet das reguläre osTicket-Formular; das dort erzeugte Ticket wird mit dem Elternticket verknüpft.
- **Admin-Übersicht**: `scp/subtickets.php` listet die Hierarchien der Installation.
- **Berechtigungsbewusst**: jedes Lesen, Verknüpfen und Lösen läuft über osTickets eigenes `Ticket::checkStaffPerm()` und das Rollenrecht `ticket.link`. Tickets, die der Agent nicht sehen darf, werden nicht angezeigt.
- **Konfigurierbare Grenzen**: maximale Tiefe, maximale Kinder je Elternticket, Verschachtelung, Pflicht auf offenes Elternticket.
- **Keine Core-Änderungen**: das Plugin nutzt osTickets Signale und kopiert zwei Seiten nach `scp/`; es patcht keine Core-Datei.

---

## Voraussetzungen

- osTicket **1.18.x**. `isCompatible()` lehnt ältere Versionen ab.
- PHP **7.4 bis 8.3**. CI lintet und testet auf jeder Version in diesem Bereich; 8.4 ist nicht in der Matrix.
- MariaDB **10.3+** oder MySQL **5.7+**. Die Aufräumlogik bei der Deinstallation ist für beide geschrieben.
- Der Webserver muss `include/plugins/<Plugin-Verzeichnis>/js/` ausliefern. osTicket liefert `include/.htaccess` mit `Deny from all` aus; siehe [docs/INSTALL.de.md](docs/INSTALL.de.md#3-javascript-des-plugins-ausliefern-lassen).
- `scp/` muss beim Aktivieren des Plugins und nach jedem Update für den PHP-Benutzer schreibbar sein.

---

## Installation

Vollständige Anleitung mit Abbruchpunkten, Kill-Switch und Smoke-Test: **[docs/INSTALL.de.md](docs/INSTALL.de.md)** ([english](docs/INSTALL.md)).

Kurzfassung:

1. `subticket-manager-vX.Y.Z.zip` aus dem [letzten Release](https://github.com/studio-prisma/osticket-subticket-manager/releases/latest) laden und nach `include/plugins/` entpacken. Das Archiv enthält den Ordner `subticket-manager/` und den Composer-Autoloader; nichts muss gebaut werden.
2. Dem Webserver erlauben, `include/plugins/subticket-manager/js/` auszuliefern (osTickets `include/.htaccess` verbietet es).
3. **Admin Panel → Manage → Plugins → Install**, dann **Enable**. Das Aktivieren kopiert `subtickets.php` und `ajax-subticket.php` nach `scp/`.
4. Ein Ticket öffnen: das Panel **Parent Ticket / Child Tickets** sitzt oberhalb der Ticket-Tabs.

Wer statt des Release-Pakets aus git installiert, braucht `composer install --no-dev` im Plugin-Verzeichnis; `vendor/autoload.php` liegt nicht im Repository.

---

## Bedienung

**Subticket anlegen.** Elternticket öffnen, **Create Subticket** im Abschnitt Child Tickets klicken. Das osTicket-Formular für neue Tickets öffnet sich; das dort erzeugte Ticket wird mit dem Elternticket verknüpft. Die Übergabe verfällt nach 10 Minuten und wird einmal verwendet.

**Bestehendes Ticket verknüpfen.** Das Ticket öffnen, das Kind werden soll, **Link to Parent** klicken, Ticketnummer oder ID des Elterntickets eingeben, bestätigen.

**Lösen.** Im Abschnitt Parent Ticket des Kindes **Unlink from Parent** klicken, oder in der Liste Child Tickets des Elterntickets **Unlink** neben dem Kind.

**Indikatoren.** Queue-Listen zeigen bei Elterntickets ein Code-Fork-Symbol mit der Zahl der Kinder; die Ticketansicht zeigt das Eltern-Badge unter der Ticketnummer.

Wer verknüpfen oder lösen darf, entscheidet osTicket: der Agent braucht Zugriff auf **beide** Tickets und das Recht `ticket.link` in der wirksamen Rolle.

---

## Konfiguration

Die Voreinstellungen funktionieren ohne Anpassung. **Admin Panel → Manage → Plugins → Subticket Manager → Instances** bietet diese Optionen:

| Option | Default | Wirkung |
|---|---|---|
| Maximum Hierarchy Depth | 3 | Wie viele Ebenen eine Kette von Subtickets unterhalb eines Wurzeltickets haben darf (1-10). Verknüpfen jenseits dieser Tiefe wird abgelehnt. |
| Maximum Children per Parent | 50 | Ein weiteres Subticket an einem Elternticket am Limit wird abgelehnt (1-1000). |
| Require Parent Ticket to be Open | an | Verknüpfen mit einem nicht offenen Elternticket wird abgelehnt. |
| Allow Nested Subtickets | an | Aus: ein Subticket kann kein Elternticket werden, und ein Elternticket kann nicht selbst Subticket sein. |
| Remove Data on Uninstall | aus | Löscht bei der Deinstallation die Tabellen des Plugins, die Spalte `version` und die beiden Indizes. Nicht umkehrbar. |

Werte außerhalb der genannten Bereiche werden beim Speichern der Konfiguration abgewiesen.

---

## Tests

```bash
composer install
vendor/bin/phpunit
```

100 Unit-Tests laufen gegen Mocks der osTicket-Klassen (`tests/mocks/`): Zugriffsprüfungen für jede AJAX-Aktion und die Übergabe, Zyklus- und Limit-Durchsetzung, der Tabellen-Prefix (ein Subprozess mit `TABLE_PREFIX=acme_`), das Einfügen des Queue-Indikators in `<head>`, das Rendern des Panels und die Plugin-Verzeichnis-Auflösung des AJAX-Handlers. CI führt sie auf PHP 7.4 bis 8.3 aus und baut bei jedem Pull Request das Release-Paket.

**Geprüft gegen ein laufendes osTicket 1.18.4** (PHP 8.2, Apache 2.4 mit `AllowOverride All`, MariaDB 10.11, Tabellen-Prefix `acme_`), installiert aus dem Release-Paket v2.0.5 mit den Korrekturen des folgenden Releases, als Nicht-Admin-Agentin mit Zugriff auf eines von zwei Departments: Panel in der Ticketansicht, `document.compatMode === "CSS1Compat"`, Verknüpfen und Lösen im eigenen Department, Verknüpfen mit einem Ticket eines anderen Departments abgelehnt ohne es preiszugeben, Zyklus abgelehnt, Übergabe über **Create Subticket** verknüpft das neue Ticket, Queue-Symbol mit Kinderzahl, Admin-Übersicht, Kundenportal ohne Panel und ohne Hierarchiedaten, abgemeldeter Endpunkt leitet zum Login, `action=version` antwortet einem Nicht-Admin mit 403, schreibende Aktionen ohne CSRF-Token mit 400. Der Smoke-Test in [docs/INSTALL.de.md](docs/INSTALL.de.md#5-smoke-test) ist genau diese Liste; nach der Installation auf der eigenen Instanz durchführen.

**Nicht geprüft:** PHP 8.4, andere osTicket-Versionen als 1.18.4, nginx und der Update-Pfad von einer Version vor 2.0.5.

---

## Bekannte Grenzen

- **Kein automatischer Status-Workflow.** Auto-Close des Elterntickets, kaskadierender Status oder kaskadierende Zuweisung sind nicht implementiert. Frühere Versionen boten Optionen dafür an, die nichts gelesen hat; sie wurden in 2.0.5 entfernt.
- **`scp/` muss für den PHP-Benutzer schreibbar sein.** Die beiden Seiten werden beim Aktivieren und bei jedem Versionswechsel des Plugins nach osTickets `scp/` kopiert. Ein schreibgeschütztes `scp/` lässt das Panel ohne AJAX-Endpunkt zurück.
- **JavaScript wird aus `include/plugins/` ausgeliefert.** Das Panel-Skript und der Queue-Indikator werden aus `include/plugins/<Plugin-Verzeichnis>/js/` geladen. osTickets `include/.htaccess` verbietet diesen Pfad; ohne Ausnahme für das Verzeichnis `js/` wird das Panel gerendert, tut aber nichts, und die Queue-Symbole bleiben leer.
- **Die kopierten Seiten überleben ein deaktiviertes Plugin.** `scp/subtickets.php` und `scp/ajax-subticket.php` bleiben bis zur Deinstallation liegen. Beide liegen hinter osTickets Agenten-Login.
- **Die Admin-Übersicht ist systemweit** und zeigt jedem Agenten die ersten 100 Beziehungen, so gewollt. Sie zeigt nur Ticketnummern und Status.
- **Eine Kopie des Plugins je Installation.** Der AJAX-Handler findet das Plugin über `include/plugins/*/class.SubticketPlugin.php`; eine zweite Kopie lässt ihn mit 500 abbrechen.
- **PHP 8.4 ist nicht in der CI-Matrix.**

---

## Troubleshooting

| Symptom | Prüfen |
|---|---|
| Panel sichtbar, Buttons tun nichts; Queue-Symbole fehlen | Browser-Konsole zeigt `Failed to load subticket-panel.js`: der Webserver verbietet `include/plugins/<Plugin-Verzeichnis>/js/`. Siehe [docs/INSTALL.de.md](docs/INSTALL.de.md#3-javascript-des-plugins-ausliefern-lassen). |
| Panel in der Ticketansicht nicht sichtbar | Plugin unter Admin Panel → Plugins aktiviert? Agenten-Oberfläche, nicht Kundenportal? Browser-Cache geleert? |
| „No parent ticket", obwohl die Beziehung existiert | `SELECT ticket_pid FROM <prefix>ticket WHERE ticket_id = X` in der Datenbank. Ist der Wert `NULL`, gibt es keine Beziehung; lösen und neu verknüpfen. |
| Verknüpfen oder Subticket anlegen geht nicht | Der Agent braucht `ticket.link` und Zugriff auf beide Tickets; das Elternticket muss offen sein, sofern **Require Parent Ticket to be Open** nicht aus ist; Tiefen- und Kinderlimit dürfen nicht erreicht sein; die Übergabe verfällt nach 10 Minuten. Das Fehlerprotokoll des Webservers zeigt `[SUBTICKET-PLUGIN]`-Zeilen. |
| Dialoge (Fälligkeit, Drucken) öffnen auf langen Ticketseiten außerhalb des Sichtbereichs | In 2.0.4 behoben; `document.compatMode` in der Browser-Konsole muss `CSS1Compat` liefern. |
| Jede Abfrage endet mit „Ticket not found" | In 2.0.5 behoben für Installationen mit einem anderen Tabellen-Prefix als `ost_`. |
| HTTP 500 von `scp/ajax-subticket.php` | Mehr als eine Kopie des Plugins unter `include/plugins/`. Eine behalten. |

---

## Mitarbeit

Siehe [CONTRIBUTING.md](CONTRIBUTING.md). Sicherheitslücken: [SECURITY.md](SECURITY.md), niemals als öffentliches Issue.

Issues, die nicht spezifisch für diesen Fork sind, gehören [Upstream](https://github.com/markus-michalski/osticket-subticket-manager/issues).

## Lizenz

[GNU General Public License v2.0 oder später](LICENSE), kompatibel mit dem osTicket-Core. Das Plugin stammt von [Markus Michalski](https://github.com/markus-michalski); dieser Fork wird von studio-prisma gepflegt.

## Changelog

Versionshistorie in [CHANGELOG.md](CHANGELOG.md).
