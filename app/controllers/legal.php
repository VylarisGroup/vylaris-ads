<?php
declare(strict_types=1);

/** Öffentliche Rechtstexte: Datenschutzerklärung, Impressum, Nutzungsbedingungen. */
function legal_show(string $page): void
{
    view('legal/page', ['title' => Legal::PAGES[$page], 'page' => $page, 'body' => Legal::html($page)]);
}

function legal_privacy(): void { legal_show('privacy'); }
function legal_imprint(): void { legal_show('imprint'); }
function legal_terms(): void { legal_show('terms'); }
