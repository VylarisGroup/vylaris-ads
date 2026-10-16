<?php
declare(strict_types=1);

/**
 * Änderungsprotokoll (CHANGELOG.md im Projektstamm, Format wie „Keep a Changelog“):
 *
 *   ## [1.6.0] - 2026-10-16
 *   ### Neu
 *   - Text mit **fett** und `code`
 *
 * Pro Administrator wird die zuletzt gesehene Version gespeichert; nach einem Update zeigt die Oberfläche
 * beim nächsten Aufruf einmalig alle Einträge seit dieser Version an.
 */
final class Changelog
{
    public const MAX_ENTRIES = 200;

    public static function file(): string
    {
        return APP_ROOT . '/CHANGELOG.md';
    }

    /** @return list<array{version: string, date: string, sections: array<string, list<string>>}> neueste Version zuerst */
    public static function entries(): array
    {
        $f = self::file();
        if (!is_file($f) || filesize($f) > 512 * 1024) return [];
        return self::parse((string) file_get_contents($f));
    }

    /** @return list<array{version: string, date: string, sections: array<string, list<string>>}> */
    public static function parse(string $md): array
    {
        $out = [];
        $cur = null;
        $sec = 'Änderungen';
        foreach (preg_split('/\R/u', $md) ?: [] as $line) {
            if (preg_match('/^##\s+\[?v?(\d+\.\d+\.\d+)\]?(?:\s*[-–—]\s*(\d{4}-\d{2}-\d{2}))?\s*$/u', $line, $m)) {
                if ($cur) $out[] = $cur;
                if (count($out) >= self::MAX_ENTRIES) { $cur = null; break; }
                $cur = ['version' => $m[1], 'date' => $m[2] ?? '', 'sections' => []];
                $sec = 'Änderungen';
            } elseif ($cur && preg_match('/^###\s+(.{1,60})\s*$/u', $line, $m)) {
                $sec = trim($m[1]);
            } elseif ($cur && preg_match('/^\s*[-*]\s+(.+)$/u', $line, $m)) { // auch eingerückte Unterpunkte werden als eigene Stichpunkte geführt
                $cur['sections'][$sec][] = trim($m[1]);
            } elseif ($cur && preg_match('/^\s{2,}(\S.*)$/u', $line, $m) && !empty($cur['sections'][$sec])) {
                $cur['sections'][$sec][count($cur['sections'][$sec]) - 1] .= ' ' . trim($m[1]); // Fortsetzungszeile
            }
        }
        if ($cur) $out[] = $cur;
        usort($out, fn ($a, $b) => version_compare($b['version'], $a['version']));
        return $out;
    }

    /** Einträge im Bereich (from, to] – also alles, was nach „from“ bis einschliesslich „to“ erschienen ist. */
    public static function between(?string $from, string $to): array
    {
        return array_values(array_filter(
            self::entries(),
            fn ($e) => version_compare($e['version'], $to, '<=') && ($from === null || version_compare($e['version'], $from, '>'))
        ));
    }

    // ----- „Gesehen“-Status pro Administrator ------------------------------------------------------------------

    private static function key(int $userId): string
    {
        return 'changelog_seen_u' . $userId;
    }

    public static function seenVersion(int $userId): ?string
    {
        $v = setting(self::key($userId), '');
        return preg_match('/^\d+\.\d+\.\d+$/', $v) ? $v : null;
    }

    public static function markSeen(int $userId): void
    {
        set_setting(self::key($userId), APP_VERSION);
    }

    /**
     * Noch nicht gesehene Einträge dieses Administrators. Ohne gespeicherten Stand (neuer Administrator,
     * erste Version mit Änderungsprotokoll) wird nur die installierte Version gezeigt.
     */
    public static function pending(int $userId, int $limit = 10): array
    {
        $seen = self::seenVersion($userId);
        if ($seen !== null && version_compare($seen, APP_VERSION, '>=')) return [];
        $list = $seen === null
            ? array_values(array_filter(self::entries(), fn ($e) => $e['version'] === APP_VERSION))
            : self::between($seen, APP_VERSION);
        return array_slice($list, 0, $limit);
    }

    // ----- Darstellung ------------------------------------------------------------------------------------------

    /** Maskiert HTML und erlaubt nur **fett** und `code`. */
    public static function inline(string $text): string
    {
        $h = e($text);
        $h = preg_replace('/`([^`]{1,200})`/u', '<code>$1</code>', $h) ?? $h;
        return preg_replace('/\*\*([^*]{1,200})\*\*/u', '<strong>$1</strong>', $h) ?? $h;
    }

    /** HTML-Darstellung einer Eintragsliste (alle Texte maskiert). */
    public static function html(array $entries, ?string $highlightAfter = null): string
    {
        $o = '';
        foreach ($entries as $e) {
            $new = $highlightAfter !== null && version_compare($e['version'], $highlightAfter, '>');
            $o .= '<section class="py-4 first:pt-0 last:pb-0"><h3 class="flex flex-wrap items-baseline gap-2 text-base font-semibold text-slate-900">Version ' . e($e['version'])
                . ($e['date'] !== '' ? ' <span class="text-xs font-normal text-slate-500">' . e(date('d.m.Y', (int) strtotime($e['date']))) . '</span>' : '')
                . ($new ? ' <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700">Neu</span>' : '') . '</h3>';
            foreach ($e['sections'] as $name => $items) {
                $o .= '<h4 class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">' . e($name) . '</h4><ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-slate-700">';
                foreach ($items as $i) $o .= '<li>' . self::inline($i) . '</li>';
                $o .= '</ul>';
            }
            $o .= '</section>';
        }
        return $o;
    }

    /** Markdown-Text eines Eintrags (für GitHub-Release-Hinweise). */
    public static function markdownFor(string $version, ?string $md = null): string
    {
        foreach (self::parse($md ?? (string) @file_get_contents(self::file())) as $e) {
            if ($e['version'] !== $version) continue;
            $o = '';
            foreach ($e['sections'] as $name => $items) {
                $o .= "### $name\n";
                foreach ($items as $i) $o .= "- $i\n";
                $o .= "\n";
            }
            return rtrim($o) . "\n";
        }
        return '';
    }
}
