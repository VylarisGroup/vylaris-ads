<?php
declare(strict_types=1);

/**
 * Herkunftshinweis „powered by VylarisAdvertise“ am Fuss jeder Seitenleiste.
 * Text und Link sind fest im Programm hinterlegt: Es gibt keine Einstellung, keine Konfiguration und keine Oberfläche dafür.
 * view() stellt den Hinweis bei jeder Seite mit Seitenleiste sicher (siehe Brand::enforce) – auch wenn eine Vorlage ihn nicht ausgibt.
 */
final class Brand
{
    private const URL = 'https://vylaris.ch';
    private const NAME = 'VylarisAdvertise';

    public static function html(): string
    {
        return '<div data-brand class="shrink-0 border-t border-slate-200 px-5 py-2.5 text-center text-xs text-slate-500">'
            . '<a href="' . self::URL . '" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-slate-700">'
            . '<i class="bx bx-broadcast text-base text-brand-600"></i><span>powered by <strong class="font-semibold text-brand-600">' . self::NAME . '</strong></span></a></div>';
    }

    /** Ausgabefilter: ersetzt veränderte oder fehlende Hinweise in der Seitenleiste durch die Originalfassung. */
    public static function enforce(string $page): string
    {
        $pos = strpos($page, '</aside>');
        if ($pos === false) return $page; // Seite ohne Seitenleiste
        $end = $pos + strlen('</aside>');
        $aside = (string) preg_replace('#<div data-brand\b.*?</div>#s', '', substr($page, 0, $pos));
        return $aside . self::html() . '</aside>' . substr($page, $end);
    }
}
