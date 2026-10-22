<?php
/** @var array $cfg @var array $stats @var list<array> $queue */
$me = current_user();
$statusBadge = ['pending' => 'bg-amber-100 text-amber-800', 'sending' => 'bg-sky-100 text-sky-800', 'sent' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-rose-100 text-rose-800'];
$statusLabel = ['pending' => 'wartet', 'sending' => 'wird gesendet', 'sent' => 'gesendet', 'failed' => 'fehlgeschlagen'];
?>
<div class="mx-auto max-w-3xl">
<?php partial('page_header', ['heading' => 'E-Mail-Versand (SMTP)', 'sub' => 'Für Bestätigungslinks bei der Registrierung und Benachrichtigungen. Ohne Einrichtung können neue Nutzer ihre Adresse nicht selbst bestätigen.']); ?>

<div class="mb-6 flex items-start gap-3 rounded-lg border p-4 text-sm <?= Mailer::configured() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900' ?>">
  <i class="bx <?= Mailer::configured() ? 'bx-check-circle' : 'bx-error' ?> text-xl"></i>
  <p><?php if (Mailer::configured()): ?>Der Versand ist eingerichtet. <strong>Senden Sie eine Test-Mail</strong>, um die Zugangsdaten zu prüfen.<?php else: ?><strong>Noch nicht eingerichtet.</strong> Bis dahin müssen Sie neue Konten unter <a class="underline" href="<?= url('admin.users') ?>">Benutzer</a> manuell bestätigen.<?php endif; ?></p>
</div>

<form method="post" action="<?= url('admin.mail') ?>" class="card space-y-5 p-6" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <h2 class="font-semibold text-slate-900">Server</h2>
  <div class="grid gap-4 sm:grid-cols-3">
    <div class="sm:col-span-2"><label class="label" for="host">SMTP-Server</label><input class="input" id="host" name="host" value="<?= e(old('host', $cfg['host'])) ?>" required maxlength="190" placeholder="smtp.beispiel.ch"></div>
    <div><label class="label" for="port">Port</label><input class="input" type="number" id="port" name="port" min="1" max="65535" value="<?= e(old('port', (string) $cfg['port'])) ?>" required></div>
  </div>
  <div><label class="label" for="secure">Verschlüsselung</label>
    <select class="input" id="secure" name="secure"><?php foreach (Mailer::SECURE_MODES as $k => $v): ?><option value="<?= $k ?>" <?= old('secure', $cfg['secure']) === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <p class="mt-1 text-xs text-slate-500">Das Zertifikat des Servers wird immer geprüft. Üblich: STARTTLS mit Port 587 oder SSL/TLS mit Port 465.</p></div>
  <div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label" for="user">Benutzername <span class="text-slate-400">(optional)</span></label><input class="input" id="user" name="user" value="<?= e(old('user', $cfg['user'])) ?>" maxlength="190" autocomplete="off"></div>
    <div><label class="label" for="pass">Passwort</label><input class="input" type="password" id="pass" name="pass" autocomplete="new-password" placeholder="<?= $cfg['has_pass'] ? '•••••••• (unverändert lassen)' : '' ?>">
      <p class="mt-1 text-xs text-slate-500">Wird verschlüsselt gespeichert und nie angezeigt.</p></div>
  </div>
  <h2 class="pt-2 font-semibold text-slate-900">Absender</h2>
  <div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label" for="from">Absenderadresse</label><input class="input" type="email" id="from" name="from" value="<?= e(old('from', $cfg['from'])) ?>" required maxlength="190" placeholder="noreply@beispiel.ch"></div>
    <div><label class="label" for="from_name">Absendername <span class="text-slate-400">(optional)</span></label><input class="input" id="from_name" name="from_name" value="<?= e(old('from_name', $cfg['from_name'])) ?>" maxlength="80" placeholder="<?= e(setting('platform_name')) ?>"></div>
  </div>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" class="accent-brand-600" <?= (old('host', $cfg['host']) === '' ? true : $cfg['enabled']) ? 'checked' : '' ?>> E-Mail-Versand aktiv</label>
  <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><i class="bx bx-info-circle"></i> Die Absenderadresse sollte zur Domain des Servers gehören (SPF/DKIM beim Anbieter einrichten), sonst landen Mails leicht im Spam.</p>
  <button class="btn-primary"><i class="bx bx-save"></i> Speichern</button>
</form>

<form method="post" action="<?= url('admin.mail') ?>" class="card mt-6 space-y-3 p-6">
  <?= csrf_field() ?><input type="hidden" name="action" value="test">
  <h2 class="font-semibold text-slate-900">Test-Mail</h2>
  <div class="flex flex-wrap items-end gap-3">
    <div class="min-w-56 flex-1"><label class="label" for="to">Empfänger</label><input class="input" type="email" id="to" name="to" value="<?= e(old('to', $me['email'])) ?>" required maxlength="190"></div>
    <button class="btn-secondary" <?= Mailer::configured() ? '' : 'disabled' ?>><i class="bx bx-send"></i> Test-Mail senden</button>
  </div>
</form>

<div class="card mt-6 p-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-semibold text-slate-900">Warteschlange</h2>
    <div class="flex flex-wrap gap-2">
      <form method="post" action="<?= url('admin.mail') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="flush"><button class="btn-ghost"><i class="bx bx-refresh"></i> Jetzt zustellen</button></form>
      <?php if ($stats['failed'] > 0): ?><form method="post" action="<?= url('admin.mail') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><button class="btn-ghost"><i class="bx bx-revision"></i> Fehlgeschlagene wiederholen</button></form><?php endif; ?>
    </div>
  </div>
  <p class="mt-2 text-sm text-slate-600"><?= (int) $stats['pending'] ?> wartend · <?= (int) $stats['failed'] ?> fehlgeschlagen · <?= (int) $stats['sent_24h'] ?> in 24 Stunden gesendet</p>
  <?php if (!$queue): ?><p class="mt-3 text-sm text-slate-500">Noch keine Nachrichten.</p><?php else: ?>
  <div class="mt-3 overflow-x-auto"><table class="table">
    <thead><tr><th>Zeit</th><th>Empfänger</th><th>Betreff</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($queue as $m): ?>
      <tr class="align-top"><td class="whitespace-nowrap text-xs"><?= fdate($m['created_at'], true) ?></td><td class="text-xs"><?= e($m['to_email']) ?></td>
        <td class="text-xs"><?= e($m['subject']) ?></td>
        <td><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusBadge[$m['status']] ?>"><?= $statusLabel[$m['status']] ?></span><?php if ($m['last_error'] && $m['status'] !== 'sent'): ?><p class="mt-1 max-w-xs break-words text-xs text-rose-700"><?= e($m['last_error']) ?> (Versuch <?= (int) $m['attempts'] ?>/<?= Mailer::MAX_ATTEMPTS ?>)</p><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <p class="mt-3 text-xs text-slate-500">Zugestellte Nachrichten werden nach 30 Tagen aus der Liste gelöscht; der Text wird schon beim Versand entfernt. Wiederholungen laufen automatisch (<code>bin/cron.php</code> oder bei Admin-Aufrufen).</p>
</div>
</div>
