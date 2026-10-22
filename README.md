# VylarisAdvertise

Kompakter Werbemarktplatz für klassisches Webhosting (PHP 8.2+, MySQL/MariaDB, PDO).
Webseitenbetreiber bieten Werbeplätze zu Festpreisen an, Werbetreibende buchen sie mit ihrem Banner.
Die Zahlung wird manuell abgewickelt und vom Administrator bestätigt, die Plattformprovision ist konfigurierbar.

* Keine Composer-Abhängigkeiten, kein Framework, kein Build-Schritt im Betrieb
* Tailwind-CSS (vorkompiliert) und Boxicons liegen lokal unter `public/assets/` – keine externen CDNs, strenge CSP
* Rund 4'500 Zeilen PHP, ein Front-Controller, eine Datei pro Themenbereich

## Funktionen

| Bereich | Umfang |
|---|---|
| Konten | Registrierung als Werbetreibender, Webseitenbetreiber oder **beides** (Rolle lässt sich später im Konto ergänzen; Dashboard und Buchungen mit Umschalter „Werbetreibender / Betreiber“; eigene Werbeplätze können nicht selbst gebucht werden), Login, Profil, Passwortwechsel, Auszahlungsdaten |
| Betreiber | Webseiten und Werbeplätze (Format, Beschreibung, Tagespreis, Mindest-/Höchstlaufzeit, Verfügbarkeit), Pausieren, Buchungen annehmen/ablehnen, Einnahmen und Kontoauszug |
| Werbetreibende | Kampagnen mit Banner-Upload und Ziel-URL, Buchungsanfragen mit Laufzeit, Zahlungsinformationen mit Referenz, Zahlung melden, Rechnung |
| Administration | Freigabe von Webseiten, Werbeplätzen und Kampagnen (mit Begründung bei Ablehnung), Zahlungseingang bestätigen, Erstattung, Auszahlungen an Betreiber, Benutzerverwaltung (sperren, Rolle, Passwort), Einstellungen, Finanzjournal mit CSV-Export, Audit-Log |
| Buchung | Verfügbarkeitsprüfung, Doppelbuchungsschutz, Preis-/Provisions-Snapshot, Statusverwaltung, Belege (Rechnung und Gutschrift, druckbar) |
| **Datenschutz (DSGVO)** | Datenschutzerklärung, Impressum und Nutzungsbedingungen (Vorlagen, mit Betreiberdaten gefüllt), Zustimmungsnachweis, **Datenexport** (Art. 15/20), **Konto löschen** mit Aufbewahrungsfristen und späterer Anonymisierung (Art. 17), automatische Bereinigung von IP-Adressen – siehe [docs/DSGVO.md](docs/DSGVO.md) |
| Statistik | Dashboards je Rolle, Umsatz-/Provisionsverlauf, Impressionen und Klicks je Buchung (reine Zähler) |

### Ablauf einer Buchung

```
Kampagne (Werbetreibender) ──► Freigabe (Admin)
Webseite + Werbeplatz (Betreiber) ──► Freigabe (Admin)

Buchungsanfrage ──► Betreiber nimmt an ──► Werbetreibender überweist (und meldet das optional)
   requested            accepted                       │
                                                       ▼
                         Admin bestätigt Zahlungseingang ──► paid ──► (nach Laufzeitende) completed
                                                                          │
                                                       Erlös wird auszahlbar ──► Admin erfasst Auszahlung
```

Weitere Endzustände: `declined`, `cancelled`, `expired`, `refunded`.
Nur **angenommene** und **bezahlte/abgeschlossene** Buchungen belegen einen Zeitraum.

## Voraussetzungen

* PHP 8.2 oder neuer mit den Erweiterungen `pdo_mysql`, `fileinfo`, `mbstring`
* MySQL 5.7+/8.x oder MariaDB 10.4+ (InnoDB)
* Apache mit `mod_rewrite` (die mitgelieferten `.htaccess` schützen die internen Ordner). Bei nginx siehe unten.

## Installation

### 1. Dateien hochladen

Empfohlene Struktur: **Nur `public/` ist öffentlich erreichbar.** Alles andere (`app/`, `config/`, `database/`, `storage/`, `bin/`) liegt eine Ebene darüber.

```
/home/kunde/vylaris-advertise/      ← gesamtes Projekt (nicht öffentlich)
    app/  bin/  config/  database/  resources/  storage/
    public/                         ← Webroot / Document-Root der Domain
```

Variante A (empfohlen): In der Hosting-Oberfläche den Document-Root der (Sub-)Domain auf `vylaris-advertise/public` setzen.

Variante B: Der Webroot ist fest vorgegeben (z. B. `httpdocs/`). Dann den **Inhalt** von `public/` in den Webroot legen und den Rest daneben:

```
/home/kunde/vylaris-advertise/      ← app/, config/, storage/, …
/home/kunde/httpdocs/               ← Inhalt von public/ (index.php, install.php, assets/, .htaccess)
```

und in `httpdocs/index.php` sowie `httpdocs/install.php` den Pfad zur Anwendung anpassen:

```php
$appRoot = '/home/kunde/vylaris-advertise';   // statt dirname(__DIR__)
```

Variante C (Notlösung): Liegt das gesamte Projekt im Webroot, ist die Anwendung unter `https://ihre-domain.tld/public/` erreichbar (diese URL gehört dann in `app.url`). Die Root-`.htaccess` sperrt `app/`, `config/`, `database/`, `storage/`, `bin/`, `resources/` und `tests/` als Sicherheitsnetz. Bitte nach der Installation prüfen, dass `/config/config.php` und `/storage/` mit 403 antworten. Variante A oder B ist deutlich sicherer.

Schreibrechte benötigen `config/` (nur während der Installation) und `storage/` (mit `banners/`, `logs/`, `sessions/`).

### 2. Datenbank anlegen

In der Hosting-Oberfläche eine leere MySQL-Datenbank mit Benutzer anlegen (Zeichensatz `utf8mb4`). Das Schema legt der Installer an.

### 3a. Web-Installer (empfohlen)

`https://ihre-domain.tld/install.php` aufrufen, Systemprüfung abwarten und Datenbank, Basis-URL, Zeitzone und Administrator eintragen.
Der Installer schreibt `config/config.php` (mit zufälligem Secret, Rechte 640), richtet das Schema ein, legt den Administrator an und sperrt sich über `storage/installed.lock`.
Existiert bereits ein Administrator in der Datenbank, läuft er auch ohne Sperrdatei nicht erneut.
**Danach `public/install.php` vom Server löschen.**

### 3b. Alternative per SSH

```bash
cp config/config.example.php config/config.php      # Zugangsdaten, app.url und app.secret eintragen
php -r 'echo bin2hex(random_bytes(32)), "\n";'      # Vorschlag für app.secret
php bin/install.php --admin-email=admin@example.com --admin-name="Admin" --admin-password="mindestens-10-Zeichen"
```

### 4. Einrichten

1. Als Administrator anmelden → **Einstellungen**: Plattformname, Währung, Provision, Bankverbindung (wird Werbetreibenden angezeigt), Belegpräfix, Mindestbetrag für Teilauszahlungen.
   Danach **Rechtliches & Datenschutz** ausfüllen (Betreiber, Anschrift, Kontakt-E-Mail, Hosting-Anbieter) – bis dahin warnt ein Hinweis im Admin-Bereich. Mit dem Hosting-Anbieter muss ein Auftragsverarbeitungsvertrag bestehen (siehe [docs/DSGVO.md](docs/DSGVO.md)).
2. Cronjob (täglich) für Statuspflege, Aufbewahrungsfristen und IP-Bereinigung (empfohlen; ohne Cronjob läuft beides automatisch bei Seitenaufrufen angemeldeter Benutzer):
   ```
   5 0 * * * php /home/kunde/vylaris-advertise/bin/cron.php
   ```
3. HTTPS erzwingen (Hosting-Oberfläche). Bei `https://` in `app.url` werden Cookies als `Secure` gesetzt und HSTS gesendet.

### nginx

Document-Root auf `public/` setzen, nur `index.php` und `install.php` an PHP-FPM geben, sonst nichts ausführen:

```nginx
root /var/www/vylaris-advertise/public;
location ~ ^/(index|install)\.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.2-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
location ~ \.php$ { return 404; }
```

## Konfiguration (`config/config.php`)

| Schlüssel | Bedeutung |
|---|---|
| `app.url` | Öffentliche Basis-URL ohne Slash (für Links im Einbindungscode und Rücksprünge) |
| `app.secret` | Zufälliger Schlüssel; leitet den Sitzungs-Fingerabdruck ab. Nach Änderung müssen sich alle neu anmelden |
| `app.debug` | Nur lokal `true`; sonst Fehler nur ins Log `storage/logs/php-error.log` |
| `db.*` | Datenbankzugang |
| `session.idle_minutes` | Inaktivitäts-Timeout (Standard 60) |
| `uploads.max_bytes` / `uploads.allowed` | Grösse und erlaubte Bildtypen (PNG, JPEG, GIF, WebP) |
| `security.login_max_attempts` / `login_window_min` | Login-Drosselung (5 Fehlversuche je E-Mail bzw. 15 je IP in 15 Minuten) |
| `privacy.retention_years` | Aufbewahrungsfrist für Abrechnungsdaten nach Ende des Geschäftsjahres (Standard 10) |
| `privacy.log_ip_days` | Anzahl Tage, nach denen IP-Adressen im Protokoll gelöscht werden (Standard 30) |
| `integrations.update.*` | Optional: `token` (nur bei privatem Release-Repository), `check_hours`, `webroot` (siehe „Updates aus GitHub“). Lizenzserver, Lizenz-Schlüssel und Update-Repository sind **nicht** konfigurierbar |
| `integrations.listeners_file` | Optionale Event-Listener für das VylarisGroup-Ökosystem (siehe unten) |

Geschäftsparameter (Provision, Währung, Bankdaten, …) stehen in der Datenbank und werden im Admin-Bereich gepflegt.

## Lizenz und Berechtigungs-Scopes

VylarisAdvertise ist lizenzpflichtig und prüft den Schlüssel gegen **TRS License** (`https://licence.vylaris.ch`, API v1; Klasse `TrsLicence` in `app/lib/Licence.php`).

**Einrichtung**
1. Auf dem Lizenzserver ein Produkt mit der Kennung `vylaris-advertise` und Tarife anlegen; die `features` des Tarifs sind die Scopes (siehe unten).
2. **Lizenzserver (`https://licence.vylaris.ch`), Produktkennung und öffentlicher Ed25519-Schlüssel sind fest im Programm hinterlegt** (`TrsLicence`) und lassen sich weder in der Weboberfläche noch in `config/config.php` ändern; der Schlüssel wird nie vom Server geladen.
3. Lizenzschlüssel im Installer (`--licence-key=…` bei `bin/install.php`) oder unter **Administration → Lizenz** eintragen. Das Gerät wird automatisch aktiviert; *Gerät abmelden* gibt den Platz wieder frei.

**Ablauf:** Heartbeat alle ca. 15 Minuten (bei Seitenaufrufen und per `bin/cron.php`, Timeout 5 s, bei Fehlern Wiederholung nach 5 Minuten). Jede Antwort wird gegen den öffentlichen Schlüssel geprüft (Ed25519) und bei jeder Auswertung erneut verifiziert, der Zeitstempel muss innerhalb von 5 Minuten liegen. Zusätzlich wird eine signierte **Offline-Lizenz** gespeichert (an die Geräte-ID gebunden): Ist der Server nicht erreichbar, läuft die Anwendung damit bis `validUntil` weiter (laut Server max. 30 Tage). Eine definitive Ablehnung (gesperrt, abgelaufen, unbekannt, falsches Produkt, Gerätelimit) wirkt sofort. Die Geräte-ID wird aus `app.secret` abgeleitet – bei Umzug `config.php` mitnehmen oder das Gerät vorher abmelden.

**Ohne gültige Lizenz: Lesemodus.** Erreichbar bleiben Marktplatz, Anmeldung, Konto inkl. Datenauskunft und -löschung, eigene Buchungen/Belege, Administration → Lizenz/Einstellungen sowie die rein lesenden Ansichten (Statistik, Audit-Log, CSV-Export). Alle verändernden Funktionen sind gesperrt. Die Auslieferung bereits gebuchter Banner (`serve`/`click`) läuft weiter.

**Scopes** (= `features` im Tarif; `*` schaltet alles frei, `bookings.*` eine Gruppe):

| Scope | Schaltet frei |
|---|---|
| `market.browse` | Marktplatz und Werbeplätze ansehen |
| `sites.manage` | Webseiten und Werbeplätze verwalten (Betreiber) |
| `campaigns.manage` | Kampagnen und Banner (Werbetreibende) |
| `bookings.manage` | Buchungen anfragen, annehmen, ablehnen, stornieren, Zahlung melden |
| `admin.review` | Freigaben von Webseiten, Werbeplätzen, Kampagnen |
| `payments.manage` | Zahlungseingang bestätigen, erstatten |
| `payouts.manage` | Auszahlungen an Betreiber |
| `users.manage` | Benutzerverwaltung |
| `export.csv` | CSV-Export des Finanzjournals |
| `stats.view` | Einnahmen- und Finanzstatistiken |
| `legal.edit` | Impressum/Datenschutzangaben bearbeiten |
| `audit.view` | Audit-Log |
| `multi_site` | mehr als eine Webseite pro Betreiber (sonst höchstens eine) |
| `dual_role` | Konten mit beiden Rollen (Registrierung, Upgrade, Admin) |

Die Zuordnung Route → Scope steht als fünftes Element in `app/routes.php` und wird zentral in `public/index.php` erzwungen; Scopes innerhalb von Aktionen prüft `require_scope()`. Neue Funktionen: Scope in `licence_scopes()` eintragen und der Route zuweisen.

**Datenschutz:** Der Lizenzserver erhält Lizenzschlüssel, Geräte-ID (Hash), Produktkennung und einen Anzeigenamen (Plattformname und Hostname) sowie die IP-Adresse des Servers; keine Daten von Benutzern der Plattform.

## Updates aus GitHub

Die Anwendung kann sich aus **GitHub-Releases** selbst aktualisieren (öffentliches oder privates Repository).

**Quelle:** Das Release-Repository ist fest im Programm hinterlegt (`Updater::DEFAULT_REPO` = `VylarisGroup/vylaris-ads`) und lässt sich weder in der Weboberfläche noch in `config/config.php` ändern – Administratoren sehen die Quelle nur. Ist das Repository privat, hinterlegt der Betreiber in `config/config.php` ein Lesetoken (`integrations.update.token`).

**Veröffentlichen einer neuen Version:**
1. Im Repository liegt der Projektstamm (Ordner `app/`, `public/`, `database/` …); `.gitignore` ist enthalten (keine `config.php`, kein `storage/`).
2. `APP_VERSION` in `app/bootstrap.php` erhöhen, committen.
3. In GitHub ein **Release** mit Tag `vX.Y.Z` erstellen (Tag = `APP_VERSION`, sonst wird das Update abgelehnt). Entwürfe und Vorabversionen werden ignoriert.
4. Schema-Änderungen als neuen Schritt in `Installer::migrate()` anlegen; sie laufen nach dem Update beim ersten Aufruf automatisch.

**Ablauf beim Kunden:** Täglich prüft die Anwendung (bei Admin-Aufrufen und per `bin/cron.php`) auf ein neueres Release und zeigt Administratoren einen Hinweis – installiert wird nur per Klick auf *Jetzt aktualisieren* (oder `php bin/update.php install`). Dabei: ZIP laden (Weiterleitungen nur zu GitHub-Hosts, Token nur an die API) → sicher entpacken (keine `..`-Pfade, keine Symlinks, Grössenlimits) → prüfen (Struktur, Version = Tag, PHP-Syntax aller Dateien) → Sicherung der Programmdateien nach `storage/backups/` (die letzten 3) → Wartungsmodus (503) → Dateien austauschen → bei Fehler automatische Wiederherstellung. `config/config.php`, `storage/` und Uploads werden nie angefasst; veraltete Dateien in `app/`, `bin/`, `database/`, `docs/`, `resources/`, `tests/` werden entfernt, in `public/` nur überschrieben.

**Oberfläche und Assistenten:** Die Navigation ist eine Seitenleiste mit ausklappbaren Gruppen (Aufbau je Rolle in `app/lib/Nav.php`; auf dem Handy als Menü). Für das Anlegen gibt es zusätzlich Assistenten unter `wizard.site` (Webseite + Werbeplatz), `wizard.campaign`, `wizard.booking`, `wizard.setup` (Administratoren) sowie `register&wizard=1`. Sie sind reine Bedienhilfen: Die Felder werden mit derselben Logik geprüft und gespeichert wie in den klassischen Formularen (`site_input()`, `slot_input()`, `settings_apply()`, `legal_apply()`, bzw. direkt `campaign.form` und `booking.request`), die Formulare bleiben bestehen. Ohne JavaScript zeigt ein Assistent alle Schritte untereinander als normales Formular. Neue Assistenten: Formular mit `data-wizard`, Schritte als `<fieldset data-step="…">`, Felder für die Zusammenfassung mit `data-sum="Beschriftung"` (siehe `app/views/wizard/`).

**Änderungsprotokoll:** Die Datei `CHANGELOG.md` (Format `## [X.Y.Z] - JJJJ-MM-TT`, Abschnitte `### Neu` / `### Geändert` / `### Behoben` / `### Sicherheit`, Stichpunkte mit `- `) wird mit jedem Update mitgeliefert. Nach einem Update sieht jeder Administrator beim nächsten Aufruf einmalig ein Fenster mit allen Einträgen seit der zuletzt gesehenen Version (gespeichert pro Administrator in `settings`, Schlüssel `changelog_seen_u<ID>`). Die gesamte Historie steht unter *Administration → Änderungsprotokoll* (Link über die Versionsnummer in der Fusszeile), auch im Lesemodus ohne Lizenz. Beim Veröffentlichen: neuen Abschnitt oben in `CHANGELOG.md` ergänzen und denselben Text als Release-Hinweise verwenden.

**Rollback:** unter *Updates → Sicherungen* oder `php bin/update.php rollback`. Notfall ohne funktionierende Anwendung: die Sicherungs-ZIP aus `storage/backups/` über das Projektverzeichnis entpacken. Die **Datenbank wird nicht gesichert** und Migrationen werden nicht zurückgenommen – vor jedem Update ein Datenbank-Backup erstellen.

**Hinweise:** Voraussetzungen sind PHP-Erweiterungen `zip` und `curl` sowie Schreibrechte auf die Programmverzeichnisse (die Seite zeigt den Status). Liegt das Webroot ausserhalb von `public/`, `integrations.update.webroot` setzen. Vertrauensmodell: HTTPS zu GitHub und Zugriff auf das Repository – wer Releases veröffentlichen kann, kann Code in alle Installationen einspielen; GitHub-Konto per 2FA und Tag-Schutz absichern. Eine zusätzliche Signaturprüfung ist nicht eingebaut.

## E-Mail-Versand und Bestätigung

SMTP wird ausschliesslich im Admin-UI eingerichtet (**Administration → System → E-Mail**; nicht in `config.php`): Server, Port, STARTTLS oder SSL/TLS (Zertifikat wird stets geprüft), Zugangsdaten (Passwort verschlüsselt mit einem aus `app.secret` abgeleiteten Schlüssel), Absender, Test-Mail. Mails laufen über die Tabelle `mail_queue` und werden nach der Antwort an den Browser zugestellt; Fehlversuche werden bis zu 5-mal wiederholt (`bin/cron.php` stellt zusätzlich zu).

Neue Nutzer müssen ihre Adresse über einen Link bestätigen (48 h gültig, einmalig, nur als Hash gespeichert), bevor sie sich anmelden können. Ist kein Versand eingerichtet, bestätigt ein Administrator unter **Benutzer** manuell. Bestehende Konten gelten bei der Migration als bestätigt. Benachrichtigungen (Buchungen, Freigaben) lassen sich im Konto abschalten. Ein Passwort-Zurücksetzen ist bewusst noch nicht enthalten.

## Zentrale Zahlungsabwicklung

Sämtliche Zahlungen laufen ausschliesslich über den Plattformbetreiber: Der Werbetreibende überweist den Buchungsbetrag an das unter *Einstellungen* hinterlegte Konto (mit Zahlungsreferenz), der Administrator bestätigt den Eingang, nach Laufzeitende erfasst der Administrator die Auszahlung an den Webseitenbetreiber (abzüglich Provision). Direkte Zahlungen zwischen Werbetreibenden und Betreibern sind in der Oberfläche und in den Nutzungsbedingungen ausgeschlossen. Konten mit beiden Rollen werden pro Buchung nach ihrer Beziehung berechtigt (als Betreiber annehmen/ablehnen, als Werbetreibender zahlen/stornieren), nie nach der Rolle allein.

## Sicherheit und Datenintegrität

* **SQL-Injection:** ausschliesslich PDO mit echten Prepared Statements (`ATTR_EMULATE_PREPARES = false`); dynamische Teile sind Whitelists oder Ganzzahlen.
* **Authentifizierung:** `password_hash()` (bcrypt/Argon-Standard von PHP), Rehash beim Login, Sitzungs-ID-Wechsel bei Login/Logout, Sitzung ist an den Passwort-Hash gebunden (Passwortwechsel oder Sperre beendet andere Sitzungen sofort), Login-Drosselung mit gleicher Laufzeit für unbekannte Konten.
* **Sitzungen:** Cookie `HttpOnly`, `SameSite=Lax`, `Secure` unter HTTPS, Strict-Mode; Sitzungsdateien liegen in `storage/sessions/` statt im gemeinsam genutzten `/tmp`.
* **Berechtigungen:** Rollenprüfung zentral in `app/routes.php` (Spalte „Zugriff“), Eigentümerprüfung in jedem Controller (fremde Datensätze liefern 404). Selbstregistrierung als Administrator ist nicht möglich.
* **CSRF:** Jede POST-Route wird zentral über ein Sitzungs-Token geprüft (`hash_equals`); Abmelden nur per POST.
* **XSS:** Ausgabe konsequent über `e()`; Content-Security-Policy ohne `unsafe-inline` (Skripte nur mit Nonce, Styles nur lokal), `X-Frame-Options: DENY`, `nosniff`.
* **Uploads:** Inhaltsprüfung per `finfo` und `getimagesize()`, Typ-Whitelist, Grössen- und Pixellimit, **keine Bytes hinter dem Bildende und kein eingebetteter PHP-/Skriptcode**, Zufallsdateiname, Ablage in `storage/banners/` ausserhalb des Webroots, Auslieferung nur über PHP mit festem Content-Type, `nosniff` und `Content-Security-Policy: sandbox`. SVG ist bewusst nicht erlaubt.
* **Doppelbuchungen:** Jede Operation, die einen Zeitraum belegt (anfragen, annehmen, Zahlung bestätigen), sperrt zuerst die Zeile des Werbeplatzes (`SELECT … FOR UPDATE`) und prüft dann Überschneidungen in derselben Transaktion. Paralleles Annehmen überschneidender Anfragen ergibt genau eine Buchung; konkurrierende offene Anfragen werden automatisch abgelehnt.
* **Preise und Status:** Preis, Laufzeit, Provision und Beträge werden ausschliesslich serverseitig aus der Datenbank berechnet und als Snapshot in der Buchung gespeichert; Formularfelder dafür werden ignoriert. Statuswechsel laufen nur über `Booking::*` mit Prüfung des Ausgangsstatus (`UPDATE … WHERE status = …`). Zahlungen erfasst nur der Administrator, der Betrag ist immer der gespeicherte Buchungsbetrag. Belegnummern werden unter Sperre fortlaufend vergeben.
* **Datenschutz (DSGVO):** keine Tracker und keine externen Ressourcen, nur ein notwendiges Sitzungs-Cookie; Auskunft/Export, Löschung mit Aufbewahrungsfristen und Anonymisierung, Löschung von IP-Adressen nach 30 Tagen. Details, Verarbeitungsverzeichnis-Vorlage, Löschkonzept und Betreiber-Checkliste: [docs/DSGVO.md](docs/DSGVO.md). **Die Rechtstexte sind Vorlagen – vor dem Livebetrieb juristisch prüfen und unter *Einstellungen → Rechtliches* ausfüllen.**
* **Nachvollziehbarkeit:** Unveränderliches Finanzjournal (`ledger`: Zahlungseingang, Provision, Erlös, Auszahlung, Erstattung) und Audit-Log (wer, was, wann, IP). Webseiten, Werbeplätze und Kampagnen mit Buchungen können nicht gelöscht werden.
* **Dateien ausserhalb des Webroots:** Konfiguration, Quellcode, Schema, Uploads, Sitzungen und Logs; zusätzlich `.htaccess`-Sperren in jedem internen Ordner.

### Bekannte Grenzen des MVP

* Kein E-Mail-Versand und damit kein „Passwort vergessen“ – der Administrator setzt Passwörter unter *Benutzer* zurück. (Die Hooks erlauben später Benachrichtigungen.) Entsprechend werden Betroffene bei Änderungen der Rechtstexte nicht per E-Mail informiert; die gültige Fassung ist immer online einsehbar.
* Administrator-Konten lassen sich nicht über die Löschfunktion entfernen. Gelöschte Daten verschwinden aus Backups erst mit deren Rotation (siehe [docs/DSGVO.md](docs/DSGVO.md)).
* Die Login-Drosselung gilt je E-Mail-Adresse; ein Angreifer kann ein Konto vorübergehend sperren, aber nicht übernehmen.
* Hinter einem Reverse-Proxy sieht die Anwendung nur die Proxy-IP (`X-Forwarded-For` wird bewusst nicht ausgewertet).
* Die Impressions-/Klickzähler sind eine einfache Zählung ohne Betrugsfilter und werden nicht abgerechnet.

## Betrieb

* **Backup:** Datenbank (`mysqldump`) und `storage/banners/` sichern.
* **Logs:** `storage/logs/php-error.log`.
* **Updates:** Dateien ersetzen, `config/config.php` und `storage/` behalten. Schemaänderungen spielt die Anwendung beim ersten Aufruf automatisch und idempotent ein (`Installer::migrate()`, Schemaversion in der Tabelle `settings`; aktuell 3 = DSGVO und Doppelrolle `both`; Lizenzzustand liegt in `settings`). Für ein Upgrade von 1.x: Dateien ersetzen und den Lizenzschlüssel unter *Administration → Lizenz* eintragen – bis dahin läuft die Anwendung im Lesemodus. Alte Einträge `integrations.licence*` und `licence_provider` in `config/config.php` werden ignoriert und können entfernt werden.
* **Neuinstallation:** `storage/installed.lock` löschen und `install.php` erneut bereitstellen (nur in leerer Datenbank).

## Einbindung auf der Webseite des Betreibers

Für bezahlte Buchungen zeigt die Buchungsseite dem Betreiber einen fertigen HTML-Code. Der Banner wird über `index.php?p=serve&t=<Token>` ausgeliefert, der Klick läuft über `p=click` zur Ziel-URL. Ausserhalb der bezahlten Laufzeit erscheint ein transparentes 1×1-Pixel. Der Token ist zufällig (128 Bit) und gibt keine Datenbankzugriffe frei.

## Projektstruktur

```
public/            Webroot: index.php (Front-Controller), install.php, assets/
app/bootstrap.php  Laden von Konfiguration und Bibliotheken (Web und CLI)
app/routes.php     Route → Controller, Methoden, Zugriffsrolle
app/controllers/   Eine Datei je Bereich (auth, market, publisher, advertiser, bookings, admin, media, dashboard, legal, privacy)
app/lib/           helpers (DB, Sitzung, CSRF, Format), Booking (Geschäftslogik), Billing, Upload, Privacy (Export/Löschung/Fristen), Legal (Rechtstexte), Hooks, Licence, Installer (inkl. Migration)
app/views/         PHP-Templates (wizard/ = Assistenten)
database/schema.sql
docs/DSGVO.md      Datenschutz: Massnahmen, Löschkonzept, Verarbeitungsverzeichnis, Checkliste
CHANGELOG.md       Änderungsprotokoll (wird nach Updates angezeigt)
bin/               install.php (CLI), cron.php, update.php (Updates/Rollback per CLI)
resources/         Tailwind-Quellen (nur für Entwicklung)
storage/           Uploads, Logs, Sitzungen (nicht öffentlich)
tests/e2e.php      End-to-End-Test
```

## Tests

Der Test läuft über HTTP gegen eine **frisch installierte Test-Datenbank** (er legt Daten an und verändert sie direkt):

```bash
# Tests lenken Lizenz- und Update-Prüfung nur per PHP-Ini auf die Simulatoren um (nicht per config.php):
O="-d auto_prepend_file=$PWD/tests/dev_overrides.php"
php bin/install.php --admin-email=admin@example.com --admin-name=Admin --admin-password='AdminPass123!' --licence-key=VA-TEST-0001-AAAA
php -S 127.0.0.1:8081 tests/fake_licence.php &     # Lizenzserver-Simulator (Steuerdatei: <tmp>/fakelic.json, Key mit features ["*"] vorab anlegen)
php $O -S 127.0.0.1:8082 tests/fake_github.php &    # GitHub-Simulator
php $O -S 127.0.0.1:8080 -t public &
php $O tests/e2e.php http://127.0.0.1:8080 admin@example.com 'AdminPass123!'
```

Geprüft werden u. a.: Registrierung und Rollenschutz, CSRF und Berechtigungen, Freigabe-Workflow, Upload-Angriffe (getarnter PHP-Code, Polyglott-Dateien, angehängter Code), serverseitige Preisberechnung trotz manipulierter Formularfelder, Doppelbuchungsschutz inklusive paralleler Annahme, manuelle Zahlung, Provision, Finanzjournal, Auszahlungsregeln, Erstattung, Sperren und Login-Drosselung sowie ein Smoke-Test aller Seiten auf PHP-Warnungen. Für den Datenschutz: Rechtstexte und Escaping eigener Texte, Zustimmungsnachweis, Export (Passwortpflicht, keine Fremddaten, kein Hash), vollständige Löschung samt Banner-Dateien, Blockierung bei laufender Buchung/Guthaben, Sperrung mit Aufbewahrungsfrist, Anonymisierung nach Fristablauf bei unveränderten Finanzdaten, Löschung durch Administratoren, IP-Bereinigung und Schema-Migration. Für Updates: feste Update-Quelle (nicht per Oberfläche änderbar), Release-Prüfung (Tag, Version, Syntax), Zip-Slip/Strukturangriffe, Austausch mit Wartungsmodus, Rollback byte-identisch. Für die Lizenz: Aktivierung, Scope-Durchsetzung (Route und Aktion, Wildcards), Sperre/Ablauf/falsches Produkt/Gerätelimit, Lesemodus, abgelehnte Serverantworten (Signatur, veralteter Zeitstempel, manipulierte Datenbank), Offline-Lizenz bei Serverausfall und deren Ablauf, Abmelden/Wiederaktivieren.

## Erweiterbarkeit

Der MVP ist bewusst auf Festpreise und manuelle Zahlung beschränkt; die folgenden Stellen sind für Erweiterungen vorbereitet:

| Erweiterung | Ansatzpunkt |
|---|---|
| **CPC/CPM-Abrechnung** | `ad_slots.pricing_model` und `bookings.pricing_model` (aktuell `ENUM('fixed')`) um `cpc`/`cpm` erweitern; Preisberechnung ist in `Booking::quote()` gekapselt; Zählerbasis liefert `booking_stats` (Impressionen/Klicks je Tag) |
| **Automatische Auslieferung** | `media_serve()` / `media_click()` in `app/controllers/media.php` sind der Auslieferungspunkt; später z. B. mehrere Buchungen je Werbeplatz rotieren oder per JavaScript-Tag einbinden |
| **Zahlungsanbieter** | `payments.method` und `payments.provider_ref`; `Booking::confirmPayment()` ist der einzige Ort, der eine Buchung auf „bezahlt“ setzt – ein Webhook ruft ihn auf, statt dass der Admin bestätigt. Auszahlungen laufen analog über `Billing::recordPayout()` |
| **Benachrichtigungen, Webhooks** | `Hooks::on('booking.paid', fn ($p) => …)` – Ereignisse: `user.registered`, `booking.requested/accepted/declined/cancelled/paid/refunded/completed`, `payout.recorded`, `entity.reviewed`, `user.deleted`; `'*'` abonniert alle. Fehler in Listenern blockieren den Kernablauf nie |

### Integration in das VylarisGroup-Ökosystem / Vylaris-Licence

Die Anwendung hat keine Abhängigkeit zu weiteren VylarisGroup-Diensten ausser der Lizenzprüfung. Vorbereitet sind:

1. **Lizenzprüfung:** siehe Abschnitt „Lizenz und Berechtigungs-Scopes“ (fest verdrahtet, nicht konfigurierbar).
2. **Benutzer-Verknüpfung / SSO:** `users.external_source` und `users.external_id` (eindeutig kombiniert) nehmen die ID eines Kontos im Ökosystem auf. Ein SSO-Login legt dort an bzw. sucht und ruft `login_user()` auf – die bestehende Anmeldung bleibt unverändert.
3. **Ereignisse:** Über `Hooks` lassen sich Buchungen, Zahlungen und Registrierungen an andere VylarisGroup-Dienste melden.

Beispiel `integrations/listeners.php`:

```php
<?php
Hooks::on('booking.paid', function (array $p) {
    // z. B. Ereignis an Vylaris-Licence oder ein anderes System melden
});
```

## CSS neu bauen (nur bei Layoutänderungen)

```bash
npx tailwindcss@3 -c resources/tailwind.config.js -i resources/app.css -o public/assets/app.css --minify
```

## Lizenzen

Boxicons (MIT/CC BY 4.0, Lizenzdatei in `public/assets/boxicons/`), Tailwind CSS (MIT, nur als vorkompiliertes CSS enthalten).
