# DSGVO / Datenschutz – Umsetzung und Betreiber-Checkliste

> **Hinweis:** Dieses Dokument und die mitgelieferten Rechtstexte sind eine technische und organisatorische Arbeitsgrundlage, **keine Rechtsberatung**.
> Verantwortlicher im Sinne der DSGVO ist der Betreiber der Installation. Er muss die Angaben prüfen, an seine Situation anpassen und die unten aufgeführten organisatorischen Punkte selbst erledigen.
> Für Betreiber in der Schweiz gilt zusätzlich das revidierte DSG (nDSG), das in den hier umgesetzten Punkten (Information, Auskunft, Löschung, Datensicherheit) weitgehend deckungsgleich ist.

## 1. Was die Software abdeckt

| Anforderung | Umsetzung |
|---|---|
| Transparenz (Art. 12–14) | Öffentliche **Datenschutzerklärung**, **Impressum** und **Nutzungsbedingungen** (`/index.php?p=privacy`, `imprint`, `terms`), im Footer jeder Seite und bei der Registrierung verlinkt. Standardtexte beschreiben die tatsächlichen Verarbeitungen dieser Anwendung und werden mit den Betreiberangaben gefüllt (Admin → Einstellungen → Rechtliches). Eigene Texte sind möglich (escaped, kein HTML). Ohne Betreiberangaben warnt ein Banner die Administratoren |
| Nachweis der Zustimmung (Art. 7 Abs. 1) | Bei Registrierung werden Zeitpunkt (`users.consent_at`) und Fassung (`consent_version`) der Nutzungsbedingungen gespeichert. Die Fassung wird bei jeder Textänderung aktualisiert |
| Auskunft und Datenübertragbarkeit (Art. 15, 20) | *Mein Konto → Daten exportieren*: JSON mit Profil, Webseiten, Werbeplätzen, Kampagnen, Buchungen, Zahlungen, Auszahlungen, Journal und Protokoll. Passwortbestätigung erforderlich. Der Export enthält keinen Passwort-Hash und keine personenbezogenen Daten von Vertragspartnern (Art. 15 Abs. 4). Administratoren können den Export im Auftrag Betroffener erstellen (auch für gesperrte Konten) |
| Berichtigung (Art. 16) | *Mein Konto → Profil* |
| Löschung (Art. 17) | *Mein Konto → Konto löschen* bzw. Admin → Benutzer → *Löschen / anonymisieren*. Siehe Löschkonzept (Abschnitt 4) |
| Einschränkung (Art. 18) | Konten mit aufbewahrungspflichtigen Daten werden nach der Löschung gesperrt und nicht mehr genutzt; Administratoren können Konten sperren |
| Datenminimierung, Speicherbegrenzung (Art. 5 Abs. 1 lit. c, e) | IP-Adressen im Protokoll werden nach 30 Tagen gelöscht, Login-Versuche nach 24 Stunden; die Auslieferung/Zählung der Banner speichert keine IP-Adressen und setzt keine Cookies; Aufbewahrungsfrist für Abrechnungsdaten konfigurierbar (Standard 10 Jahre) mit anschliessender Anonymisierung |
| Sicherheit (Art. 32) | Siehe README „Sicherheit und Datenintegrität“ (Passwort-Hashing, CSRF, CSP, Rollenprüfung, Prepared Statements, sichere Uploads, Dateien ausserhalb des Webroots, Protokollierung) |
| Datenschutz durch Voreinstellung (Art. 25) | Keine Tracker, keine externen Ressourcen (Schriften, Symbole und CSS lokal), nur ein technisch notwendiges Sitzungs-Cookie (kein Einwilligungsbanner erforderlich, § 25 Abs. 2 Nr. 2 TDDDG) |
| Rechenschaftspflicht (Art. 5 Abs. 2) | Audit-Log für Anmeldung, Freigaben, Buchungen, Zahlungen, Exporte, Löschungen und Rechtstext-Änderungen |

## 2. Wo personenbezogene Daten gespeichert sind

| Ort | Daten | Löschung |
|---|---|---|
| `users` | E-Mail, Name, Firma, Passwort-Hash, Auszahlungsdaten, Zustimmung, letzter Login, optionale Kennung (`external_id`) | Konto löschen; bei Aufbewahrungspflicht gesperrt und nach Fristablauf anonymisiert |
| `sites`, `ad_slots`, `campaigns`, `storage/banners/` | Inhalte des Nutzers (Namen, URLs, Beschreibungen, Bilder) | mit dem Konto; bei Buchungen anonymisiert nach Fristablauf |
| `bookings` (`message`, `decision_note`) | Freitext zwischen den Parteien | `message` bei Kontolöschung geleert |
| `payments`, `payouts`, `ledger`, `bookings` | Abrechnungsdaten, Belegnummern | Aufbewahrungsfrist (Handels-/Steuerrecht), danach ohne Personenbezug |
| `audit_log` | Aktion, Zeitpunkt, Benutzer-ID, **IP-Adresse** | IP nach `privacy.log_ip_days` (30 Tage); Benutzerbezug beim Löschen gelöst bzw. Details bei Anonymisierung entfernt |
| `login_attempts` | **IP-Adresse**, E-Mail | nach 24 Stunden |
| `storage/sessions/` | Sitzungsdaten (Benutzer-ID, CSRF-Token) | automatisch (Inaktivität + Garbage Collection) |
| `storage/logs/php-error.log` | technische Fehler (ohne gezielte Personendaten) | durch den Betreiber rotieren/löschen |
| Server-Logs, Datenbank-Backups | beim Hosting-Anbieter bzw. Betreiber | Rotation durch Hoster/Betreiber, siehe Checkliste |

## 3. Verarbeitungsverzeichnis (Art. 30) – Vorlage

| Nr. | Tätigkeit | Betroffene | Daten | Zweck | Rechtsgrundlage | Empfänger | Frist |
|---|---|---|---|---|---|---|---|
| 1 | Kontoverwaltung | Nutzer | Name, Firma, E-Mail, Passwort-Hash, Zustimmung, Login-Zeit | Nutzung der Plattform | Art. 6 (1) b | Administratoren; Hoster (AV) | bis Kontolöschung |
| 2 | Marktplatz und Buchungen | Nutzer | Webseiten-/Werbeplatz-/Kampagnendaten, Buchungen, Nachrichten | Vertragsabwicklung | Art. 6 (1) b | Vertragspartner einer Buchung (Name/Firma, E-Mail) | bis Kontolöschung; Buchungen siehe 3 |
| 3 | Abrechnung | Nutzer | Zahlungen, Provision, Auszahlungsdaten, Belege | Zahlungsabwicklung, Buchführung | Art. 6 (1) b, c | Administratoren; ggf. Steuerberatung, Behörden | 10 Jahre (konfigurierbar) |
| 4 | Sicherheit/Protokoll | Nutzer, Besucher der Login-Seite | IP, E-Mail, Aktionen | Missbrauchsabwehr, Nachvollziehbarkeit | Art. 6 (1) f; Art. 32 | Administratoren | IP 30 Tage; Login-Versuche 24 h |
| 5 | Bannerauslieferung | Besucher von Betreiberseiten | technisch: IP/User-Agent nur flüchtig im Request; gespeichert werden nur anonyme Tageszähler | Leistungsnachweis | Art. 6 (1) b, f | – | Zähler: Dauer der Buchungsdaten |
| 6 | Hosting | alle | IP, Zeitpunkt, URL, Browserkennung (Server-Logs) | Betrieb | Art. 6 (1) f | Hoster (AV, Art. 28) | nach Vorgabe des Hosters |

Ergänzen: Name und Kontaktdaten des Verantwortlichen, ggf. Datenschutzbeauftragter, Beschreibung der technischen und organisatorischen Massnahmen (Verweis auf README), Drittlandübermittlungen (keine durch die Anwendung selbst; die Lizenzprüfung überträgt nur Lizenzschlüssel, Geräte-ID, Plattform-/Hostname an den Lizenzserver, keine Benutzerdaten).

## 4. Löschkonzept

```
Löschwunsch (Konto oder Administrator)
        │
        ├─ Admin-Konto?                                → nicht über diese Funktion löschbar
        ├─ bezahlte, noch laufende Buchung?            → blockiert (Laufzeit abwarten oder erstatten)
        ├─ Betreiber mit auszahlbarem Guthaben?        → blockiert (zuerst Auszahlung)
        ▼
 offene unbezahlte Anfragen/Buchungen stornieren, Nicht-Abrechnungs-Buchungen entfernen
        │
        ├─ keine Abrechnungsdaten (bezahlt/abgeschlossen/erstattet/Auszahlung)?
        │      → VOLLSTÄNDIGE LÖSCHUNG: Konto, Webseiten, Werbeplätze, Kampagnen, Banner-Dateien,
        │        Login-Versuche; Protokolleinträge werden vom Konto gelöst, IP entfernt
        │
        └─ Abrechnungsdaten vorhanden?
               → SPERRUNG: Konto Status „gelöscht“, E-Mail/Passwort/Auszahlungsdaten/Kennungen entfernt,
                 Nachrichten geleert, Webseiten/Werbeplätze deaktiviert, IP im Protokoll entfernt.
                 Name/Firma nur für die Belege bis Ende des Geschäftsjahres der letzten Buchung + Frist
                 ▼ (täglich per Housekeeping)
               → ANONYMISIERUNG: „Gelöschter Benutzer #ID“; Kampagnen-, Webseiten- und Platzdaten
                 neutralisiert, Banner-Dateien gelöscht. Finanzzahlen bleiben ohne Personenbezug erhalten
                 (der Vertragspartner benötigt sie für seine eigene Buchführung).
```

Backups: Gelöschte Daten verschwinden aus Sicherungen erst mit deren Rotation. Empfehlung: Backups höchstens 30–90 Tage aufbewahren. Bei einer Wiederherstellung müssen bereits erfolgte Löschungen erneut ausgeführt werden (Protokolleinträge `user.deleted` im Audit-Log).

## 5. Ablauf bei Betroffenenanfragen

1. Anfrage trifft per E-Mail beim Betreiber ein (Adresse in der Datenschutzerklärung) oder der Nutzer nutzt die Selbstbedienung im Konto.
2. Bei E-Mail-Anfragen Identität prüfen (z. B. Rückmeldung von der hinterlegten Konto-E-Mail-Adresse).
3. **Auskunft/Übertragbarkeit:** Admin → Benutzer → Verwalten → *Daten exportieren*, Datei sicher übermitteln.
4. **Löschung:** Admin → Benutzer → Verwalten → *Löschen / anonymisieren*. Bei Blockierung dem Betroffenen den Grund nennen (z. B. offene Buchung/Guthaben) und die Frist nachhalten.
5. **Berichtigung/Einschränkung/Widerspruch:** Konto bearbeiten bzw. sperren; Entscheidung dokumentieren.
6. Antwort **innerhalb eines Monats** (Art. 12 Abs. 3). Die Vorgänge stehen im Audit-Log.

## 6. Checkliste für den Betreiber vor dem Livegang

- [ ] *Einstellungen → Rechtliches* vollständig ausfüllen (Betreiber, Anschrift, Kontakt-E-Mail, Hosting-Anbieter, ggf. Register/UID).
- [ ] Datenschutzerklärung, Impressum und Nutzungsbedingungen **juristisch prüfen** lassen; Abschnitt „Hosting“ an den tatsächlichen Anbieter anpassen.
- [ ] **Auftragsverarbeitungsvertrag (Art. 28)** mit dem Hosting-Anbieter abschliessen; Standort der Server und Drittlandübermittlungen klären.
- [ ] HTTPS erzwingen (Cookie wird dann `Secure`, HSTS aktiv).
- [ ] Aufbewahrungsfrist in `config/config.php` (`privacy.retention_years`) mit der Steuerberatung abstimmen (CH: 10 Jahre; DE: i. d. R. 8 bzw. 10 Jahre).
- [ ] Cronjob `bin/cron.php` einrichten (täglich) – führt Fristen, IP-Bereinigung und Anonymisierung zuverlässig aus.
- [ ] Backup-Rotation festlegen (siehe Abschnitt 4) und Zugriff auf Backups/Server-Logs beschränken.
- [ ] Prüfen, ob ein **Datenschutzbeauftragter** zu benennen ist (z. B. § 38 BDSG) und ob eine Datenschutz-Folgenabschätzung nötig ist (bei dieser Art Verarbeitung in der Regel nicht).
- [ ] Prozess für **Datenpannen** festlegen (Meldung an die Aufsichtsbehörde binnen 72 Stunden, Art. 33; Benachrichtigung Betroffener, Art. 34).
- [ ] Administratoren auf Vertraulichkeit verpflichten; Admin-Konten mit starken, einmaligen Passwörtern schützen.
- [ ] Webseitenbetreiber weisen ihre eigenen Besucher in **ihrer** Datenschutzerklärung auf eingebundene Werbemittel hin (die Plattform speichert dabei keine IP-Adressen und setzt keine Cookies, der Abruf des Bildes von diesem Server ist technisch aber ein Datenzugriff).
- [ ] Verzeichnis der Verarbeitungstätigkeiten (Abschnitt 3) ausfüllen und aktuell halten.

## 7. Technische Hinweise

* **Konfiguration** (`config/config.php`): `privacy.retention_years` (Standard 10), `privacy.log_ip_days` (Standard 30).
* **Housekeeping:** täglich automatisch bei Seitenaufrufen angemeldeter Benutzer oder per `php bin/cron.php`.
* **Update bestehender Installationen:** Dateien ersetzen – die Schema-Migration (Spalten für Zustimmung/Löschung, Standardwerte der Rechtstexte) läuft beim ersten Aufruf automatisch und ist idempotent (`Installer::migrate()`, Schemaversion 2).
* **Ereignisse:** `Hooks::on('user.deleted', fn ($p) => …)` liefert `user_id` und `mode` (`deleted`|`retained`), z. B. um Löschungen an angebundene VylarisGroup-Systeme weiterzugeben. Verknüpfte externe Kennungen (`external_id`) werden bei der Löschung entfernt.
