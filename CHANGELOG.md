# Änderungsprotokoll

Alle nennenswerten Änderungen an VylarisAdvertise. Neueste Version zuerst. Administratoren sehen neue Einträge nach einem Update einmalig beim nächsten Aufruf der Oberfläche.

Format pro Version: `## [X.Y.Z] - JJJJ-MM-TT`, darunter Abschnitte (`### Neu`, `### Geändert`, `### Behoben`, `### Sicherheit`) mit Stichpunkten.

## [1.8.0] - 2026-10-22
### Aktion erforderlich
- **Bitte richten Sie den E-Mail-Versand jetzt im Admin-UI ein:** Administration → System → **E-Mail**. Tragen Sie SMTP-Server, Port, Verschlüsselung, Zugangsdaten und Absender ein und senden Sie eine Test-Mail. Solange das fehlt, erhalten neue Nutzer keinen Bestätigungslink und müssen von Ihnen unter **Benutzer** manuell bestätigt werden.
### Neu
- **SMTP-Mailversand:** Einrichtung komplett im Admin-UI (STARTTLS oder SSL/TLS, Serverzertifikat wird immer geprüft). Das Passwort wird verschlüsselt gespeichert. Mails laufen über eine Warteschlange mit automatischen Wiederholungen (bis zu 5 Versuche); Test-Mail, Status und manuelles Zustellen auf derselben Seite.
- **E-Mail-Bestätigung:** Neue Nutzer müssen ihre Adresse über einen Link bestätigen (48 Stunden gültig, einmal verwendbar), bevor sie sich anmelden können. Bestätigungslink kann erneut angefordert werden.
- **Benachrichtigungen** zu Buchungsanfragen, Entscheidungen und Freigaben; im Konto abschaltbar.
- Administratoren sehen unbestätigte Konten in der Benutzerliste und können die Adresse manuell bestätigen oder den Link erneut senden.
### Geändert
- Bestehende Konten gelten automatisch als bestätigt. Die Registrierung meldet nicht mehr automatisch an.
- Eine geänderte E-Mail-Adresse gilt erst nach Bestätigung per Link an die neue Adresse.
- Zugestellte Mails verlieren ihren Text sofort und werden nach 30 Tagen aus der Liste gelöscht.

## [1.7.0] - 2026-10-16
### Neu
- **Seitenleiste mit ausklappbaren Menüs:** Die Navigation steht jetzt links und ist nach Aufgaben gruppiert (Werbung, Webseiten bzw. Prüfung, Finanzen, Verwaltung, System). Die Gruppe der aktuellen Seite ist offen, der Zustand der übrigen wird im Browser gemerkt. Auf dem Handy öffnet sich dasselbe Menü über die Menü-Schaltfläche.
- **Assistenten für alle Anlege-Vorgänge:** Schritt für Schritt mit Fortschrittsanzeige, Prüfung pro Schritt und Zusammenfassung vor dem Absenden.
  - **Webseite einrichten:** Webseite und erster Werbeplatz in einem Durchgang (oder nur ein weiterer Werbeplatz für eine bestehende Webseite). Es wird beides oder nichts gespeichert.
  - **Kampagne erstellen:** mit Banner-Vorschau, Grössenanzeige und Hinweis, zu welchem Format das Banner passt.
  - **Werbeplatz buchen:** Platz, passende Kampagne, Zeitraum mit Live-Preis, Zusammenfassung.
  - **Einrichtungsassistent** für Administratoren: Plattform, Provision, Zahlungsinformationen und Betreiberangaben.
  - **Registrierung:** optional Schritt für Schritt (Link „Schritt für Schritt registrieren“).
- Die klassischen Formulare bleiben unverändert erreichbar; jeder Assistent verlinkt darauf. Beide Wege verwenden dieselbe Prüfung und Speicherlogik auf dem Server.
### Geändert
- Neue Menüeinträge für Administratoren: Protokoll, Updates und Änderungsprotokoll sind jetzt direkt in der Seitenleiste erreichbar.

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
