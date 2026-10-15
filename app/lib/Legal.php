<?php
declare(strict_types=1);

/**
 * Rechtstexte: Datenschutzerklärung, Impressum, Nutzungsbedingungen.
 *
 * Die Standardtexte sind Vorlagen, die zu den tatsächlichen Verarbeitungen dieser Anwendung passen und mit den
 * Betreiberangaben (Einstellungen → Rechtliches) gefüllt werden. Sie ersetzen keine Rechtsberatung.
 * Betreiber können jeden Text vollständig durch einen eigenen Text ersetzen (Markdown-light, siehe render()).
 */
final class Legal
{
    public const PAGES = ['privacy' => 'Datenschutzerklärung', 'imprint' => 'Impressum', 'terms' => 'Nutzungsbedingungen'];

    /** @return array{name:string,address:string,email:string,phone:string,register:string,representative:string,hoster:string} */
    public static function operator(): array
    {
        $o = [];
        foreach (['name', 'address', 'email', 'phone', 'register', 'representative', 'hoster'] as $k) {
            $o[$k] = trim(setting('legal_' . $k));
        }
        return $o;
    }

    /** Pflichtangaben für Impressum/Datenschutz vorhanden? */
    public static function complete(): bool
    {
        $o = self::operator();
        return $o['name'] !== '' && $o['address'] !== '' && filter_var($o['email'], FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function version(): string
    {
        return setting('legal_version', '2026-10-14');
    }

    public static function privacyContact(): string
    {
        $o = self::operator();
        return $o['email'] !== '' ? $o['email'] : '[E-Mail-Adresse des Betreibers]';
    }

    /** HTML einer Seite: eigener Text, falls hinterlegt, sonst Standardtext. */
    public static function html(string $page): string
    {
        $custom = trim(setting('legal_' . $page . '_custom'));
        if ($custom !== '') {
            return self::render($custom);
        }
        return match ($page) {
            'privacy' => self::privacy(),
            'imprint' => self::imprint(),
            'terms'   => self::terms(),
            default   => '',
        };
    }

    /**
     * Markdown-light, vollständig escaped: "## Überschrift", Absätze durch Leerzeile, Zeilen mit "- " als Liste.
     */
    public static function render(string $text): string
    {
        $out = '';
        foreach (preg_split('/\R{2,}/', str_replace("\r", '', trim($text))) as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $lines = explode("\n", $block);
            if (str_starts_with($block, '## ')) {
                $out .= self::h(substr(array_shift($lines), 3));
                $block = trim(implode("\n", $lines));
                if ($block === '') {
                    continue;
                }
                $lines = explode("\n", $block);
            }
            if (count(array_filter($lines, fn ($l) => str_starts_with($l, '- '))) === count($lines)) {
                $out .= self::ul(array_map(fn ($l) => e(substr($l, 2)), $lines));
            } else {
                $out .= self::p(nl2br(e($block), false));
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ Bausteine

    private static function h(string $t): string
    {
        return '<h2 class="mt-8 text-lg font-semibold text-slate-900">' . e($t) . '</h2>';
    }

    private static function p(string $html): string
    {
        return '<p class="mt-2 text-sm leading-6 text-slate-700">' . $html . '</p>';
    }

    /** @param list<string> $items bereits escapte HTML-Fragmente */
    private static function ul(array $items): string
    {
        return '<ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-700"><li>' . implode('</li><li>', $items) . '</li></ul>';
    }

    private static function operatorBlock(): string
    {
        $o = self::operator();
        $name = $o['name'] !== '' ? e($o['name']) : '<em>[Name des Betreibers – unter Einstellungen → Rechtliches hinterlegen]</em>';
        $addr = $o['address'] !== '' ? nl2br(e($o['address']), false) : '<em>[Anschrift]</em>';
        $mail = $o['email'] !== '' ? '<a class="link" href="mailto:' . e($o['email']) . '">' . e($o['email']) . '</a>' : '<em>[E-Mail]</em>';
        $html = '<address class="mt-2 text-sm not-italic leading-6 text-slate-700"><strong>' . $name . '</strong><br>' . $addr . '<br>E-Mail: ' . $mail;
        if ($o['phone'] !== '') {
            $html .= '<br>Telefon: ' . e($o['phone']);
        }
        return $html . '</address>';
    }

    // ------------------------------------------------------------------ Datenschutzerklärung

    private static function privacy(): string
    {
        $o = self::operator();
        $platform = e(setting('platform_name', 'VylarisAdvertise'));
        $years = (int) config('privacy.retention_years', 10);
        $logDays = (int) config('privacy.log_ip_days', 30);
        $cookie = e((string) config('session.name', 'vyad_sess'));
        $hoster = $o['hoster'] !== '' ? e($o['hoster']) : '<em>[Hosting-Anbieter – unter Einstellungen → Rechtliches hinterlegen]</em>';
        $contact = e(self::privacyContact());

        $h = '<p class="text-sm text-slate-500">Stand: ' . e(self::version()) . '</p>';

        $h .= self::h('1. Verantwortlicher');
        $h .= self::p('Verantwortlich für die Verarbeitung personenbezogener Daten auf dieser Plattform ist:') . self::operatorBlock();
        $h .= self::p('Anfragen zum Datenschutz richten Sie bitte an ' . $contact . '.');

        $h .= self::h('2. Grundsätze');
        $h .= self::p('' . $platform . ' ist ein Marktplatz, auf dem Webseitenbetreiber Werbeplätze anbieten und Werbetreibende diese buchen. Wir verarbeiten personenbezogene Daten nur, soweit dies für Betrieb, Vertragsabwicklung und Sicherheit der Plattform erforderlich ist. '
            . 'Wir setzen keine Analyse- oder Werbe-Tracker ein, betreiben kein Profiling und geben Daten nicht zu Werbezwecken an Dritte weiter. Alle Schriften, Symbole und Stylesheets werden von unserem eigenen Server geladen; es werden keine externen Ressourcen eingebunden.');

        $h .= self::h('3. Hosting und technische Zugriffsdaten');
        $h .= self::p('Die Plattform wird bei ' . $hoster . ' gehostet. Beim Aufruf übermittelt Ihr Browser technisch notwendige Daten an den Server (IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse, Browserkennung). '
            . 'Der Hosting-Anbieter kann diese Daten in Server-Protokollen speichern. Mit dem Anbieter besteht ein Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO. '
            . 'Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (sicherer und stabiler Betrieb).');

        $h .= self::h('4. Cookies');
        $h .= self::p('Wir verwenden ausschliesslich ein technisch notwendiges Sitzungs-Cookie („' . $cookie . '“). Es hält Ihre Anmeldung aufrecht und schützt Formulare vor Fremdzugriff (CSRF). Es wird beim Schliessen des Browsers gelöscht, spätestens nach einer Inaktivität von '
            . (int) config('session.idle_minutes', 60) . ' Minuten verliert die Sitzung ihre Gültigkeit. Da das Cookie für die ausdrücklich gewünschte Nutzung unbedingt erforderlich ist, ist keine Einwilligung nötig (§ 25 Abs. 2 Nr. 2 TDDDG; Art. 6 Abs. 1 lit. b und f DSGVO). Es gibt keine Analyse-, Marketing- oder Drittanbieter-Cookies.');

        $h .= self::h('5. Konto und Registrierung');
        $h .= self::p('Bei der Registrierung verarbeiten wir Name, optional Firma, E-Mail-Adresse, Kontoart (Werbetreibender, Webseitenbetreiber oder beides) und Ihr Passwort (ausschliesslich als kryptografischer Hash, nie im Klartext). '
            . 'Wir speichern zudem den Zeitpunkt und die Version Ihrer Zustimmung zu den Nutzungsbedingungen sowie den Zeitpunkt Ihres letzten Logins. Webseitenbetreiber können Auszahlungsdaten (z. B. Kontoinhaber, IBAN) hinterlegen; diese sind nur für Administratoren sichtbar. '
            . 'Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (Nutzungsvertrag). Die Daten werden bis zur Löschung Ihres Kontos gespeichert, vorbehaltlich der Aufbewahrungspflichten in Abschnitt 11.');

        $h .= self::h('6. Webseiten, Werbeplätze, Kampagnen und Buchungen');
        $h .= self::p('Wir verarbeiten die von Ihnen eingestellten Inhalte: Angaben zu Webseiten und Werbeplätzen (Name, Adresse, Beschreibung, Preis, Verfügbarkeit), Kampagnen (Name, Ziel-Adresse, Banner-Bilddatei) sowie Buchungen mit Laufzeit, Nachrichten und Statusverlauf. '
            . 'Angaben zu freigegebenen Webseiten und Werbeplätzen sind im Marktplatz öffentlich sichtbar; bitte tragen Sie dort keine personenbezogenen Daten Dritter ein. Banner und Kampagnen werden vor der Freigabe von Administratoren geprüft. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO.');
        $h .= self::p('<strong>Offenlegung gegenüber Vertragspartnern:</strong> Bei einer Buchung sehen sich Werbetreibender und Webseitenbetreiber gegenseitig mit Name bzw. Firma und E-Mail-Adresse, damit der Vertrag abgewickelt und Rückfragen geklärt werden können (Art. 6 Abs. 1 lit. b DSGVO). Die Auszahlungsdaten sehen sie nicht.');

        $h .= self::h('7. Zahlungsabwicklung und Abrechnung');
        $h .= self::p('Zahlungen werden manuell per Überweisung abgewickelt. Wir erfassen Zahlungseingänge, Plattformprovision, Erlösanteile, Auszahlungen und Erstattungen sowie fortlaufend nummerierte Belege (Rechnung an den Werbetreibenden, Gutschrift an den Webseitenbetreiber) mit Name bzw. Firma und E-Mail-Adresse. '
            . 'Rechtsgrundlagen sind Art. 6 Abs. 1 lit. b DSGVO (Vertragsabwicklung) und lit. c DSGVO (handels- und steuerrechtliche Aufzeichnungs- und Aufbewahrungspflichten). Zahlungsdienstleister werden nicht eingebunden.');

        $h .= self::h('8. Anzeige der Werbemittel auf Webseiten Dritter');
        $h .= self::p('Wird ein gebuchtes Banner über den von uns bereitgestellten Code auf der Webseite eines Betreibers angezeigt, ruft der Browser der Besucher das Bild und bei einem Klick die Weiterleitung von unserem Server ab. Dabei werden technisch bedingt IP-Adresse und Browserkennung an unseren Server übermittelt. '
            . '<strong>Die Anwendung speichert weder IP-Adressen noch Browserkennungen noch setzt sie Cookies oder Kennungen.</strong> Gezählt werden ausschliesslich anonyme Tageszahlen (Anzeigen und Klicks je Buchung); die Browserkennung wird nur flüchtig zur Ausfilterung von Suchmaschinen-Robotern ausgewertet. '
            . 'Rechtsgrundlage ist Art. 6 Abs. 1 lit. b und f DSGVO (Nachweis der vereinbarten Leistung). Der Webseitenbetreiber ist für die Information seiner Besucher auf seiner eigenen Webseite selbst verantwortlich.');

        $h .= self::h('9. Sicherheit und Protokollierung');
        $h .= self::p('Zum Schutz vor Missbrauch speichern wir fehlgeschlagene Anmeldeversuche (IP-Adresse, E-Mail-Adresse, Zeitpunkt) für höchstens 24 Stunden. Wichtige Vorgänge (Anmeldung, Freigaben, Buchungen, Zahlungen, Einstellungen) werden mit Benutzer, Zeitpunkt und IP-Adresse protokolliert; '
            . 'die IP-Adresse wird nach ' . $logDays . ' Tagen gelöscht, der übrige Protokolleintrag bleibt zur Nachvollziehbarkeit der Abrechnung erhalten. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO sowie Art. 32 DSGVO (Sicherheit der Verarbeitung).');

        $h .= self::h('10. Empfänger');
        $h .= self::ul([
            'Administratoren der Plattform (Prüfung, Freigabe, Zahlungs- und Kontenverwaltung),',
            'der jeweilige Vertragspartner einer Buchung (siehe Abschnitt 6),',
            'der Hosting-Anbieter als Auftragsverarbeiter (Abschnitt 3),',
            'Steuerberatung, Behörden und Gerichte, soweit wir gesetzlich dazu verpflichtet sind.',
        ]);
        $h .= self::p('Die Plattform selbst übermittelt keine personenbezogenen Daten in Drittländer.');

        $h .= self::h('11. Speicherdauer');
        $h .= self::ul([
            'Kontodaten: bis zur Löschung Ihres Kontos.',
            'Webseiten, Werbeplätze, Kampagnen ohne Buchung und Banner: bis zur Löschung durch Sie bzw. mit Ihrem Konto.',
            'Buchungen, Zahlungen, Belege, Auszahlungen und das Finanzjournal: ' . $years . ' Jahre nach Ablauf des Geschäftsjahres (gesetzliche Aufbewahrungspflicht). '
                . 'Nach einer Kontolöschung werden diese Daten gesperrt, nicht mehr für andere Zwecke genutzt und nach Fristablauf anonymisiert.',
            'Fehlgeschlagene Anmeldeversuche: höchstens 24 Stunden.',
            'IP-Adressen im Protokoll: ' . $logDays . ' Tage.',
            'Sicherungskopien: werden in regelmässigem Turnus überschrieben; gelöschte Daten verschwinden dadurch spätestens mit Ablauf der Rotation.',
        ]);

        $h .= self::h('12. Ihre Rechte');
        $h .= self::p('Sie haben gegenüber uns folgende Rechte: Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21). '
            . 'Eine erteilte Einwilligung können Sie jederzeit mit Wirkung für die Zukunft widerrufen.');
        $h .= self::p('<strong>Selbstbedienung:</strong> Unter „Mein Konto“ können Sie Ihr Profil berichtigen, alle Ihre Daten als JSON-Datei exportieren (Auskunft und Übertragbarkeit) und Ihr Konto löschen. '
            . 'Gesetzlich aufzubewahrende Abrechnungsdaten werden dabei nicht sofort gelöscht, sondern gesperrt und nach Fristablauf anonymisiert (Art. 17 Abs. 3 lit. b DSGVO). Offene bezahlte Buchungen oder Guthaben müssen zuvor abgeschlossen bzw. ausgezahlt sein. '
            . 'Für alle weiteren Anliegen schreiben Sie an ' . $contact . '.');
        $h .= self::p('Sie haben das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren, insbesondere in dem Mitgliedstaat Ihres gewöhnlichen Aufenthalts, Ihres Arbeitsplatzes oder des Orts des mutmasslichen Verstosses.');

        $h .= self::h('13. Pflicht zur Bereitstellung, automatisierte Entscheidungen');
        $h .= self::p('Name, E-Mail-Adresse und Passwort sind für die Kontoeröffnung erforderlich; ohne sie kann der Vertrag nicht geschlossen werden. Es findet keine ausschliesslich automatisierte Entscheidungsfindung einschliesslich Profiling im Sinne von Art. 22 DSGVO statt.');

        $h .= self::h('14. Änderungen');
        $h .= self::p('Wir passen diese Erklärung an, wenn sich die Plattform oder die Rechtslage ändert. Es gilt die jeweils hier veröffentlichte Fassung.');
        return $h;
    }

    // ------------------------------------------------------------------ Impressum

    private static function imprint(): string
    {
        $o = self::operator();
        $h = self::h('Angaben zum Anbieter') . self::operatorBlock();
        if ($o['representative'] !== '') {
            $h .= self::p('Vertretungsberechtigt: ' . e($o['representative']));
        }
        if ($o['register'] !== '') {
            $h .= self::p('Register- / Steuerangaben: ' . nl2br(e($o['register']), false));
        }
        $h .= self::h('Inhalte Dritter');
        $h .= self::p('Webseiten, Werbeplätze, Kampagnen und Banner werden von Nutzern der Plattform eingestellt. Für diese Inhalte sind die jeweiligen Nutzer verantwortlich. '
            . 'Bei Hinweisen auf rechtswidrige Inhalte schreiben Sie bitte an ' . e(self::privacyContact()) . '; wir prüfen den Hinweis und entfernen Inhalte, soweit erforderlich.');
        return $h;
    }

    // ------------------------------------------------------------------ Nutzungsbedingungen

    private static function terms(): string
    {
        $p = e(setting('platform_name', 'VylarisAdvertise'));
        $op = self::operator()['name'] !== '' ? e(self::operator()['name']) : 'der Betreiber';
        $pct = rtrim(rtrim(number_format((int) setting('commission_bp', '1500') / 100, 2, '.', ''), '0'), '.');
        $h = '<p class="text-sm text-slate-500">Stand: ' . e(self::version()) . '</p>';
        $h .= self::h('1. Geltungsbereich und Leistung');
        $h .= self::p('Diese Bedingungen gelten für die Nutzung von ' . $p . ' (Betreiber: ' . $op . '). Die Plattform vermittelt Buchungen von Werbeplätzen zwischen Webseitenbetreibern (Anbietern) und Werbetreibenden. '
            . 'Der Vertrag über die Werbeleistung kommt zwischen dem Werbetreibenden und dem Webseitenbetreiber zustande; der Betreiber der Plattform ist Vermittler und wickelt Zahlungen ab.');
        $h .= self::h('2. Konto');
        $h .= self::p('Die Registrierung ist nur mit wahrheitsgemässen Angaben zulässig. Zugangsdaten sind geheim zu halten. Ein Konto kann zugleich als Werbetreibender und als Webseitenbetreiber genutzt werden; eigene Werbeplätze können nicht selbst gebucht werden. Der Betreiber kann Konten sperren, wenn Missbrauch, Verstösse gegen diese Bedingungen oder Rechtsverstösse vorliegen.');
        $h .= self::h('3. Freigabe');
        $h .= self::p('Webseiten, Werbeplätze und Kampagnen werden vor der Veröffentlichung bzw. Buchbarkeit durch Administratoren geprüft. Ein Anspruch auf Freigabe besteht nicht. Nach wesentlichen Änderungen ist eine erneute Freigabe erforderlich.');
        $h .= self::h('4. Buchung');
        $h .= self::p('Werbetreibende senden eine Buchungsanfrage mit Laufzeit; der Preis ergibt sich aus dem zum Anfragezeitpunkt gültigen Tagespreis des Werbeplatzes und wird festgeschrieben. '
            . 'Der Webseitenbetreiber kann die Anfrage annehmen oder ablehnen. Ein Zeitraum ist erst nach Annahme verbindlich belegt. Unbezahlte Buchungen können bis zur Zahlungsbestätigung storniert werden.');
        $h .= self::h('5. Zahlung und Provision');
        $h .= self::p('Sämtliche Zahlungen werden zentral über den Betreiber der Plattform abgewickelt; direkte Zahlungen zwischen Werbetreibenden und Webseitenbetreibern sind ausgeschlossen. Der Werbetreibende zahlt den Buchungsbetrag per Überweisung mit der angegebenen Referenz an den Betreiber. Die Buchung gilt mit der Bestätigung des Zahlungseingangs durch den Betreiber als bezahlt. '
            . 'Der Betreiber behält eine Plattformprovision von derzeit ' . e($pct) . ' % des Buchungsbetrags ein und zahlt den Erlösanteil nach Ende der Laufzeit an den Webseitenbetreiber aus. Eine Änderung der Provision gilt nur für neue Buchungsanfragen.');
        $h .= self::h('6. Inhalte und Pflichten');
        $h .= self::p('Banner und Ziel-Adressen müssen rechtmässig sein und dürfen keine Rechte Dritter verletzen, keine irreführende, jugendgefährdende oder anderweitig unzulässige Werbung enthalten und nicht auf Schadsoftware verweisen. '
            . 'Webseitenbetreiber binden gebuchte Banner für die vereinbarte Laufzeit unverändert und gut sichtbar ein. Bei Verstössen kann der Betreiber Buchungen beenden und erstatten.');
        $h .= self::h('7. Erstattung');
        $h .= self::p('Bezahlte Buchungen können vom Betreiber erstattet werden, insbesondere wenn die Leistung nicht erbracht wird. Bereits abgeschlossene Buchungen werden grundsätzlich nicht erstattet.');
        $h .= self::h('8. Haftung');
        $h .= self::p('Der Betreiber haftet unbeschränkt für Vorsatz, grobe Fahrlässigkeit sowie bei Verletzung von Leben, Körper und Gesundheit. Bei leichter Fahrlässigkeit haftet er nur für die Verletzung wesentlicher Vertragspflichten und begrenzt auf den vorhersehbaren, vertragstypischen Schaden. '
            . 'Eine bestimmte Reichweite oder Verfügbarkeit der Plattform wird nicht zugesichert.');
        $h .= self::h('9. Datenschutz');
        $h .= self::p('Informationen zur Verarbeitung personenbezogener Daten finden Sie in der <a class="link" href="' . e(url('privacy')) . '">Datenschutzerklärung</a>.');
        $h .= self::h('10. Änderungen, Schlussbestimmungen');
        $h .= self::p('Der Betreiber kann diese Bedingungen mit Wirkung für die Zukunft ändern; über wesentliche Änderungen werden Nutzer informiert. Es gilt das am Sitz des Betreibers anwendbare Recht, soweit dem keine zwingenden Verbraucherschutzvorschriften entgegenstehen. Sollten einzelne Bestimmungen unwirksam sein, bleibt der Rest wirksam.');
        return $h;
    }
}
