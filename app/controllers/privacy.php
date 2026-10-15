<?php
declare(strict_types=1);

/** Betroffenenrechte im Konto: Datenexport (Art. 15/20 DSGVO) und Kontolöschung (Art. 17 DSGVO). */

function privacy_require_password(array $user): void
{
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
        throw new UserError('Das Passwort ist falsch.');
    }
}

function privacy_export(): void
{
    $user = current_user();
    privacy_require_password($user);
    Privacy::sendExport((int) $user['id']);
}

function privacy_delete(): void
{
    $user = current_user();
    privacy_require_password($user);
    if (empty($_POST['confirm'])) {
        throw new UserError('Bitte bestätigen Sie die Löschung Ihres Kontos.');
    }
    $mode = Privacy::deleteAccount((int) $user['id'], (int) $user['id']);
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', $mode === 'deleted'
        ? 'Ihr Konto und alle zugehörigen Daten wurden gelöscht.'
        : 'Ihr Konto wurde gelöscht. Abrechnungsdaten werden nur noch zur Erfüllung gesetzlicher Aufbewahrungspflichten gesperrt aufbewahrt und danach anonymisiert.');
    redirect('home');
}
