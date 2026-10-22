<?php
declare(strict_types=1);

/**
 * Navigationsbaum der Seitenleiste (je nach Rolle). Einträge sind Links oder ausklappbare Gruppen.
 *
 *   ['link',  Seite, Beschriftung, Icon, [weitere Seiten, bei denen der Eintrag aktiv ist], Assistent?]
 *   ['group', Schlüssel, Beschriftung, Icon, [Einträge]]
 */
final class Nav
{
    /** @return list<array<int, mixed>> */
    public static function tree(?array $me): array
    {
        $nav = [['link', 'home', 'Marktplatz', 'bx-store-alt', ['slot']]];
        if (!$me) return $nav;
        $nav[] = ['link', 'dashboard', 'Dashboard', 'bx-grid-alt', []];

        if ($me['role'] !== 'admin') {
            if (can_advertise($me)) {
                $nav[] = ['group', 'advertise', 'Werbung', 'bx-bullseye', [
                    ['link', 'campaigns', 'Kampagnen', 'bx-image-alt', ['campaign.form']],
                    ['link', 'wizard.campaign', 'Kampagne erstellen', 'bx-magic-wand', [], true],
                    ['link', 'wizard.booking', 'Werbeplatz buchen', 'bx-cart-add', [], true],
                ]];
            }
            if (can_publish($me)) {
                $nav[] = ['group', 'publish', 'Webseiten', 'bx-globe', [
                    ['link', 'sites', 'Meine Webseiten', 'bx-layout', ['site.form', 'slot.form']],
                    ['link', 'wizard.site', 'Webseite einrichten', 'bx-magic-wand', [], true],
                    ['link', 'earnings', 'Einnahmen', 'bx-wallet', []],
                ]];
            }
            $nav[] = ['link', 'bookings', 'Buchungen', 'bx-calendar-check', ['booking']];
            return $nav;
        }

        $nav[] = ['group', 'review', 'Prüfung', 'bx-check-shield', [
            ['link', 'admin.reviews', 'Freigaben', 'bx-check-shield', []],
            ['link', 'admin.bookings', 'Buchungen', 'bx-calendar-check', ['booking']],
        ]];
        $nav[] = ['group', 'finance', 'Finanzen', 'bx-line-chart', [
            ['link', 'admin.finance', 'Übersicht', 'bx-line-chart', []],
            ['link', 'admin.payouts', 'Auszahlungen', 'bx-transfer', []],
        ]];
        $nav[] = ['group', 'manage', 'Verwaltung', 'bx-cog', [
            ['link', 'admin.users', 'Benutzer', 'bx-group', []],
            ['link', 'admin.settings', 'Einstellungen', 'bx-cog', []],
            ['link', 'wizard.setup', 'Einrichtungsassistent', 'bx-magic-wand', [], true],
            ['link', 'admin.audit', 'Protokoll', 'bx-history', []],
        ]];
        $nav[] = ['group', 'system', 'System', 'bx-server', [
            ['link', 'admin.mail', 'E-Mail', 'bx-envelope', []],
            ['link', 'admin.licence', 'Lizenz', 'bx-key', []],
            ['link', 'admin.update', 'Updates', 'bx-up-arrow-circle', []],
            ['link', 'admin.changelog', 'Änderungsprotokoll', 'bx-gift', []],
        ]];
        return $nav;
    }

    public static function isActive(array $item, string $current): bool
    {
        if ($item[0] === 'group') {
            foreach ($item[4] as $child) if (self::isActive($child, $current)) return true;
            return false;
        }
        return $item[1] === $current || in_array($current, $item[4], true);
    }

    /** HTML der Navigation (alle Texte maskiert). */
    public static function render(?array $me, string $current): string
    {
        $link = function (array $i) use ($current): string {
            $on = self::isActive($i, $current);
            return '<a href="' . e(url($i[1])) . '"' . ($on ? ' aria-current="page"' : '') . (!empty($i[5]) ? ' title="Schritt-für-Schritt-Assistent"' : '') . ' class="flex items-center gap-2 rounded-md px-3 py-2 text-sm '
                . ($on ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900') . '">'
                . '<i class="bx ' . e($i[3]) . ' text-lg"></i><span class="min-w-0 flex-1 truncate">' . e($i[2]) . '</span>'
                . '</a>';
        };
        $o = '';
        foreach (self::tree($me) as $item) {
            if ($item[0] === 'link') {
                $o .= $link($item);
                continue;
            }
            $on = self::isActive($item, $current);
            $o .= '<details class="group" data-nav-group="' . e($item[1]) . '"' . ($on ? ' open data-active' : '') . '>'
                . '<summary class="flex cursor-pointer list-none items-center gap-2 rounded-md px-3 py-2 text-sm font-medium '
                . ($on ? 'text-brand-700' : 'text-slate-700') . ' hover:bg-slate-100">'
                . '<i class="bx ' . e($item[3]) . ' text-lg"></i><span class="flex-1">' . e($item[2]) . '</span>'
                . '<i class="bx bx-chevron-down text-lg text-slate-400 transition-transform group-open:rotate-180"></i></summary>'
                . '<div class="ml-5 mt-0.5 space-y-0.5 border-l border-slate-200 pl-2">';
            foreach ($item[4] as $child) $o .= $link($child);
            $o .= '</div></details>';
        }
        return $o;
    }
}
