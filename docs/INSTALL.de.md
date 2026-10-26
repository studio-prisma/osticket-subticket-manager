# Installation: Subticket Manager für osTicket 1.18.x

[English version →](INSTALL.md) · [zurück zur README](../README.de.md)

Zielverzeichnis: `include/plugins/subticket-manager/` der osTicket-Installation. `support.example.com` ist durchgehend der Beispiel-Hostname, `ost_` der Beispiel-Tabellen-Prefix. Beides durch die eigenen Werte ersetzen.

**Die Reihenfolge ist bindend.** Jeder Schritt hat einen Abbruchpunkt. Bricht ein Schritt ab, wird dieser Schritt behoben; nicht weitermachen und hoffen, dass der nächste es ausgleicht.

---

## 0. Bevor irgendetwas angefasst wird

| Prüfung | Abbruch, wenn |
|---|---|
| osTicket-Version | nicht 1.18.x (`isCompatible()` lehnt ältere ab; neuere sind ungetestet) |
| PHP-Version des Webservers | unter 7.4, oder 8.4 und eine ungetestete Version ist nicht tragbar |
| Datenbank | weder MariaDB 10.3+ noch MySQL 5.7+ |
| `scp/` für den PHP-Benutzer schreibbar | `ls -ld scp/` zeigt einen Eigentümer, als der der PHP-Prozess nicht schreiben kann, und nur `sudo` auf einen Webserver-Pfad hülfe |
| Webserver-Konfiguration editierbar | das `Deny from all` in `include/.htaccess` lässt sich nicht um eine Ausnahme ergänzen (Schritt 3) |
| Rückweg | Datenbank sowie die Verzeichnisse `include/plugins/` und `scp/` lassen sich nicht binnen Minuten wiederherstellen |
| Zahl der Plugin-Kopien | `ls include/plugins/*/class.SubticketPlugin.php` listet mehr als eine Datei |

**Aussperr-Risiko.** Das Plugin berührt weder Login noch Sessions noch Authentifizierung; es kann niemanden aus osTicket aussperren. Was es kann: ein fataler Fehler im Plugin bricht jede Agentenseite, weil Plugins bei jedem Request gebootstrappt werden. Das Kundenportal ist nicht betroffen. Das Admin-Panel zum Deaktivieren ist dann unter Umständen nicht erreichbar; dafür gibt es den Kill-Switch.

**Kill-Switch.** Funktioniert ohne Admin-Panel. Plugin in der Datenbank deaktivieren, dann die beiden nach `scp/` kopierten Seiten entfernen:

```sql
-- Tabellen-Prefix ost_ angenommen; ost-config.php prüfen
UPDATE ost_plugin SET isactive = 0 WHERE name = 'Subticket Manager';
```

```bash
cd /pfad/zu/osticket
rm -f scp/subtickets.php scp/ajax-subticket.php scp/.subticket-deployed-version
```

Das Deaktivieren stoppt Bootstrap, Panel und Queue-Indikator sofort. Das Entfernen der beiden Seiten schließt den AJAX-Endpunkt und die Übersicht, die sonst (hinter dem Agenten-Login) erreichbar bleiben, solange das Plugin deaktiviert ist. Die Tabellen des Plugins und die zusätzliche Spalte an der Ticket-Tabelle können liegen bleiben; Schritt 8 entfernt sie.

---

## 1. Paket beziehen

`subticket-manager-vX.Y.Z.zip` aus dem [letzten Release](https://github.com/studio-prisma/osticket-subticket-manager/releases/latest) laden. Nicht aus einem Quell-Archiv von `main` installieren: `main` ist kein Release, und dem Quell-Archiv fehlt `vendor/autoload.php`.

**Abbruch, wenn** dem Release kein Archiv anhängt oder die Version im Dateinamen nicht zum Tag passt.

Alternativ aus git (nur für Entwicklung):

```bash
cd include/plugins
git clone https://github.com/studio-prisma/osticket-subticket-manager.git subticket-manager
cd subticket-manager && composer install --no-dev
```

**Abbruch, wenn** `composer` auf dem Server nicht verfügbar ist; ohne `vendor/autoload.php` lädt das Plugin nicht.

---

## 2. Dateien installieren

```bash
cd /pfad/zu/osticket/include/plugins
unzip /tmp/subticket-manager-vX.Y.Z.zip
chown -R <web-user>:<web-group> subticket-manager
ls subticket-manager/vendor/autoload.php subticket-manager/plugin.php
```

Das Archiv enthält den Ordner `subticket-manager/`; ihn nach `include/plugins/` zu entpacken ist die ganze Installation. Der Verzeichnisname ist frei (der AJAX-Handler findet das Plugin über seine Klassendatei), ohne Grund aber bei `subticket-manager` bleiben.

**Abbruch, wenn** in `include/plugins/` bereits eine andere Version des Plugins liegt: erst entfernen, nicht überlagern. **Abbruch, wenn** `vendor/autoload.php` nach dem Entpacken fehlt.

---

## 3. JavaScript des Plugins ausliefern lassen

Das Panel-Skript und der Queue-Indikator werden vom Browser aus `include/plugins/subticket-manager/js/` geladen. osTicket liefert `include/.htaccess` mit `Deny from all` aus, und viele nginx-Setups sperren `include/` ebenfalls. Ohne Ausnahme wird das Panel gerendert, aber jeder Button ist tot, und die Browser-Konsole zeigt `Failed to load subticket-panel.js`.

**Apache** (mit wirksamem `AllowOverride` für `include/`): `include/plugins/subticket-manager/js/.htaccess` anlegen:

```apache
# Wird vom Browser geladen; alles andere unter include/ bleibt gesperrt.
# osTickets include/.htaccess nutzt die Apache-2.2-Syntax (Deny from all).
# Ein bloßes "Require all granted" hebt das auf Apache 2.4 mit geladenem
# mod_access_compat (Standard) NICHT auf; das Order/Allow-Paar ist Pflicht.
# "Require all granted" deckt eine auf 2.4-Syntax umgeschriebene include/.htaccess ab.
Order allow,deny
Allow from all
Require all granted
```

Geprüft auf Apache 2.4.69 mit der mitgelieferten `include/.htaccess`: `Require all granted` allein antwortet weiter mit 403, die drei Zeilen oben liefern 200 für das Verzeichnis `js/` und lassen `class.SubticketPlugin.php` bei 403.

**nginx**: eine Location für das Verzeichnis `js/` **vor** der Regel eintragen, die `include/` sperrt:

```nginx
location ^~ /include/plugins/subticket-manager/js/ {
    try_files $uri =404;
}
location ^~ /include/ {
    deny all;
}
```

Von außen prüfen:

```bash
curl -sI https://support.example.com/include/plugins/subticket-manager/js/subticket-panel.js | head -1
curl -sI https://support.example.com/include/plugins/subticket-manager/class.SubticketPlugin.php | head -1
```

**Abbruch, wenn** die erste Zeile nicht `200` ist oder die zweite Zeile `200` ist. Die Ausnahme muss das Verzeichnis `js/` abdecken und sonst nichts unter `include/`.

Diese Datei liegt bewusst außerhalb des Plugin-Pakets: sie ist Webserver-Konfiguration, und ein Plugin-Update darf sie nicht verändern.

---

## 4. In osTicket installieren und aktivieren

1. **Admin Panel → Manage → Plugins → Add New Plugin**: „Subticket Manager" wird gelistet. **Install** klicken.
2. In der Plugin-Liste das Plugin anhaken und **Enable** wählen.

Das Aktivieren legt die Singleton-Instanz an, kopiert `subtickets.php` und `ajax-subticket.php` nach `scp/` und schreibt `scp/.subticket-deployed-version`. Der erste Agenten-Request danach legt die Tabellen `ost_ticket_hierarchy_metadata` und `ost_ticket_progress` an, ergänzt die Spalte `version` an `ost_ticket` und zwei Indizes darauf.

**Abbruch, wenn** das Aktivieren `Target directory not writable` meldet: Rechte von `scp/` korrigieren (Schritt 0), erneut aktivieren. **Abbruch, wenn** das Plugin nicht gelistet wird: `plugin.php` oder `class.SubticketPlugin.php` fehlt, oder das Plugin wurde eine Ebene zu tief entpackt.

Konfiguration: **Admin Panel → Manage → Plugins → Subticket Manager → Instances**. Die Voreinstellungen funktionieren; die Optionen sind in [README.de.md](../README.de.md#konfiguration) beschrieben. Nach dem Aktivieren konfigurieren, nicht davor; die Instanz existiert erst mit dem aktivierten Plugin.

---

## 5. Smoke-Test

Die Installation ist erst fertig, wenn jede Zeile das angegebene Ergebnis liefert. Mit einem **Nicht-Admin-Agenten** prüfen, der das Rollenrecht `ticket.link` und Zugriff auf eines von zwei Departments hat; ein Admin besteht jede Prüfung unabhängig von der Berechtigungslogik.

| Test | Erwartet |
|---|---|
| Ein beliebiges Ticket als dieser Agent öffnen | Panel **Parent Ticket / Child Tickets** oberhalb der Ticket-Tabs |
| Browser-Konsole auf einer Queue-Seite: `document.compatMode` | `"CSS1Compat"` (`"BackCompat"` heißt: etwas wird vor `<!DOCTYPE html>` ausgegeben) |
| Browser-Konsole nach dem Laden der Ticketseite | kein `Failed to load subticket-panel.js`, kein `Failed to load queue-indicator.js` |
| **Link to Parent** mit einer Ticketnummer aus dem eigenen Department | Panel zeigt das Elternticket; die Queue-Liste zeigt am Elternticket das Code-Fork-Symbol mit `1` |
| **Link to Parent** mit einer Ticketnummer aus dem anderen Department | abgelehnt mit generischer Meldung; weder Betreff noch Status dieses Tickets in der Antwort |
| **Create Subticket** am Elternticket, Formular für neue Tickets absenden | das neue Ticket ist Kind des Elterntickets |
| **Unlink** vom Elternticket | Panel zeigt kein Elternticket; Symbol verschwindet aus der Queue-Liste |
| Dasselbe Ticket im Kundenportal öffnen (als Benutzer des Tickets anmelden) | kein Panel, keine Eltern- oder Kindnummern irgendwo im Seitenquelltext |
| `scp/ajax-subticket.php?action=children&tid=<id>` in einem privaten Fenster (abgemeldet) | Umleitung zum Agenten-Login |
| `scp/ajax-subticket.php?action=version` als Nicht-Admin-Agent | HTTP 403 |
| `scp/subtickets.php` als der Agent | die Übersichtsseite mit den oben angelegten Verknüpfungen |

**Abbruch, wenn** eine Zeile abweicht. Kill-Switch aus §0 ziehen und offline untersuchen; das Fehlerprotokoll des Webservers enthält `[SUBTICKET-PLUGIN]`-Zeilen.

---

## 6. Security-Gate

Vor dem Abschluss durchgehen. Jede Zeile ist eine Entscheidung, kein Vorschlag.

| Prüfung | Erwartet | Bei Abweichung |
|---|---|---|
| Panel im Kundenportal | nicht vorhanden | nicht produktiv setzen; die Korrektur dafür steckt in 2.0.5, installierte Version prüfen |
| `action=children` für ein Ticket eines Departments ohne Zugriff | abgelehnt, Antwort ohne Ticketdaten | nicht produktiv setzen |
| `action=link` ohne osTickets CSRF-Token (`curl -X POST` nur mit Session-Cookie) | abgelehnt | nicht produktiv setzen |
| `include/plugins/subticket-manager/class.SubticketPlugin.php` per `curl` abgerufen | nicht `200` | die Ausnahme aus Schritt 3 ist zu weit |
| `SUBTICKET_DEBUG` in `class.SubticketPlugin.php` | `false` | auf `false` setzen; das Debug-Log schreibt Betreffs und SQL |
| Fehlerprotokoll des Webservers | keine `[SUBTICKET-PLUGIN]`-Einträge im Normalbetrieb | vor dem Produktivgang lesen |

---

## 7. Update

1. Den [CHANGELOG](../CHANGELOG.md)-Abschnitt der neuen Version auf Verhaltensänderungen und entfernte Optionen lesen.
2. Plugin-Verzeichnis ersetzen: altes wegbewegen, neues Archiv entpacken, Eigentümer wiederherstellen. Die neuen Dateien nicht über die alten legen.
3. Die `js/.htaccess` aus Schritt 3 neu anlegen, falls sie im Plugin-Verzeichnis liegt (sie ist nicht Teil des Pakets).
4. Eine beliebige Agentenseite öffnen. Der Versionswechsel löst eine frische Kopie der beiden Seiten nach `scp/` aus; `scp/.subticket-deployed-version` zeigt jetzt die neue Version.
5. Smoke-Test durchführen (Schritt 5).

**Abbruch, wenn** `scp/.subticket-deployed-version` nach einem Agenten-Request noch die alte Version zeigt: `scp/` ist nicht schreibbar, und die alte `ajax-subticket.php` bedient das neue Panel.

---

## 8. Deinstallation

1. Entscheiden, ob die Hierarchiedaten bleiben. **Remove Data on Uninstall** (Plugin-Konfiguration) löscht die beiden Tabellen, die Spalte `version` und die beiden Indizes. Die Spalte `ticket_pid` gehört osTicket und wird nie angefasst; bestehende Eltern-Verknüpfungen überleben die Deinstallation in jedem Fall.
2. **Admin Panel → Manage → Plugins**: deaktivieren, dann das Plugin löschen. Die Deinstallation entfernt `scp/subtickets.php`, `scp/ajax-subticket.php`, `scp/.subticket-deployed-version` und eine von Versionen vor 2.0.5 hinterlassene `scp/apps.php`.
3. Verzeichnis und Webserver-Ausnahme entfernen:

```bash
cd /pfad/zu/osticket
rm -rf include/plugins/subticket-manager
ls scp/subtickets.php scp/ajax-subticket.php 2>/dev/null   # darf nichts ausgeben
```

Liegen die beiden Seiten noch da, war `scp/` bei der Deinstallation nicht schreibbar; von Hand entfernen.

---

## Bekannte Grenzen

- Geprüft auf osTicket 1.18.4, PHP 8.2, Apache 2.4 (`AllowOverride All`, mitgelieferte `include/.htaccess`), MariaDB 10.11, Tabellen-Prefix `acme_`: jede Zeile des Smoke-Tests und des Security-Gates oben, als Nicht-Admin-Agentin. Nicht geprüft: PHP 8.4, andere osTicket-Versionen, nginx und der Update-Pfad von einer Version vor 2.0.5. Die CI-Tests laufen gegen Mocks der osTicket-Klassen; der Smoke-Test oben ist die Live-Prüfung für die eigene Instanz.
- Kein automatischer Status-Workflow (Auto-Close, kaskadierender Status oder kaskadierende Zuweisung).
- `scp/` muss beim Aktivieren und bei jedem Update schreibbar sein; die beiden Seiten bleiben erreichbar, solange das Plugin deaktiviert ist.
- Die Admin-Übersicht ist systemweit und auf 100 Zeilen begrenzt.
- Eine Kopie des Plugins je Installation.

## Troubleshooting

| Symptom | Ursache | Behebung |
|---|---|---|
| Panel sichtbar, Buttons tot, Konsole zeigt `Failed to load subticket-panel.js` | `include/` vom Webserver gesperrt | Schritt 3 |
| Aktivieren meldet `Target directory not writable` | `scp/` für den PHP-Benutzer nicht schreibbar | Schritt 0, dann erneut aktivieren |
| Panel fehlt, kein Konsolenfehler | Plugin deaktiviert, Kundenportal statt Agenten-Oberfläche, oder Browser-Cache | Admin Panel → Plugins prüfen; hart neu laden |
| Plugin steht auf *Active*, aber kein Panel, keine Queue-Symbole, keine `scp/.subticket-deployed-version` | die Plugin-**Instanz** ist deaktiviert (2.0.5 ließ sie bei der Installation so) | Admin Panel → Manage → Plugins → Subticket Manager → *Instances* → Instanz auf Enabled setzen; im Release nach 2.0.5 behoben |
| Dialoge öffnen auf langen Ticketseiten außerhalb des Sichtbereichs, `document.compatMode` ist `BackCompat` | etwas gibt vor dem Doctype aus; das Plugin selbst tut das seit 2.0.4 nicht mehr | andere Plugins und eigenen Code in `include/` prüfen |
| „Ticket not found" bei jedem Verknüpfungsversuch | anderer Tabellen-Prefix als `ost_` mit einer Plugin-Version vor 2.0.5 | Update |
| HTTP 500 von `scp/ajax-subticket.php`, Log meldet `Multiple SubticketPlugin installations` | zwei Kopien unter `include/plugins/` | eine behalten |
| HTTP 429 `Too many requests` | das Session-Rate-Limit des AJAX-Endpunkts | die genannten Sekunden warten; Ursache ist meist ein Skript, das den Endpunkt hämmert |
