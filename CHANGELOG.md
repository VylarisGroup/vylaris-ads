# Änderungsprotokoll

Alle nennenswerten Änderungen an VylarisAdvertise. Neueste Version zuerst. Administratoren sehen neue Einträge nach einem Update einmalig beim nächsten Aufruf der Oberfläche.

Format pro Version: `## [X.Y.Z] - JJJJ-MM-TT`, darunter Abschnitte (`### Neu`, `### Geändert`, `### Behoben`, `### Sicherheit`) mit Stichpunkten.

## [1.6.0] - 2026-10-16
### Neu
- **Änderungsprotokoll:** Nach einem Update zeigt die Administration beim ersten Aufruf ein Fenster mit allen Neuerungen seit der zuletzt gesehenen Version. Jeder Administrator wird einzeln geführt.
- Seite „Änderungsprotokoll“ mit der gesamten Versionshistorie (erreichbar über die Versionsnummer in der Fusszeile und über die Update-Seite). Sie steht auch im Lesemodus ohne Lizenz zur Verfügung.
- Die Hinweise werden aus `CHANGELOG.md` gelesen, das mit jedem Update mitgeliefert wird.

## [1.5.0]
### Geändert
- Die Update-Quelle (GitHub-Repository) ist fest vorgegeben und kann von Benutzern nicht mehr angepasst werden.
- Der Lizenzserver ist fest vorgegeben und kann nicht mehr angepasst werden.
- Für Lizenzabfragen wird automatisch der hinterlegte öffentliche Schlüssel des Herstellers verwendet.

## [1.4.1]
### Geändert
- Standard-Repository für Updates: `VylarisGroup/vylaris-ads`.

## [1.4.0]
### Neu
- **Updates aus GitHub-Releases:** automatische Prüfung auf neue Versionen, Installation per Klick oder per `php bin/update.php`.
- Sichere Installation mit Prüfung des Pakets, Dateisicherung, Wartungsmodus, automatischer Wiederherstellung bei Fehlern und Rollback.

## [1.3.1]
### Geändert
- Öffentlicher Schlüssel für die Lizenzprüfung hinterlegt.

## [1.3.0]
### Neu
- **Lizenzsystem** nach TRS-License-API mit signierten Antworten, Heartbeat und Offline-Lizenz.
- Berechtigungs-Scopes steuern, welche Funktionen die Lizenz freischaltet. Ohne gültige Lizenz läuft die Anwendung im Lesemodus.

## [1.2.0]
### Neu
- Registrierung als Werbe-Sender (Werbetreibender), Empfänger (Webseitenbetreiber) oder beides.
### Geändert
- Alle Zahlungen laufen zentral über den Betreiber der Plattform.

## [1.1.0]
### Neu
- **DSGVO-Funktionen:** Einwilligungen, Datenauskunft und -export, Löschung, Aufbewahrungsfristen und Rechtstexte.

## [1.0.0]
### Neu
- Erste Version: Werbeplatz-Marktplatz mit Registrierung, Webseiten und Werbeplätzen, Kampagnen mit Banner-Upload, Buchungen mit Verfügbarkeitsprüfung, Freigaben durch den Administrator, manueller Zahlung mit Plattform-Provision, Dashboards und Protokoll.
