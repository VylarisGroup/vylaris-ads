<?php partial('page_header', ['heading' => 'Mein Konto', 'sub' => ROLE_LABELS[$user['role']] . ' · Mitglied seit ' . fdate($user['created_at'])]); ?>
<div class="grid gap-6 lg:grid-cols-2">
  <form method="post" action="<?= url('account') ?>" class="card space-y-4 p-6">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2 class="font-semibold text-slate-900">Profil</h2>
    <div><label class="label" for="name">Name</label><input class="input" id="name" name="name" value="<?= e(old('name', $user['name'])) ?>" required maxlength="120"></div>
    <div><label class="label" for="company">Firma</label><input class="input" id="company" name="company" value="<?= e(old('company', (string) $user['company'])) ?>" maxlength="160"></div>
    <div><label class="label" for="email">E-Mail-Adresse</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email', $user['email'])) ?>" required maxlength="190"></div>
    <?php if (can_publish($user)): ?>
    <div><label class="label" for="payout_details">Auszahlungsdaten</label><textarea class="input" id="payout_details" name="payout_details" rows="3" maxlength="500" placeholder="Kontoinhaber, IBAN, Bank"><?= e(old('payout_details', (string) $user['payout_details'])) ?></textarea><p class="mt-1 text-xs text-slate-500">Nur für Administratoren sichtbar, wird für Auszahlungen verwendet.</p></div>
    <?php endif; ?>
    <div><label class="label" for="cp1">Aktuelles Passwort <span class="text-slate-400">(nur bei Änderung der E-Mail)</span></label><input class="input" type="password" id="cp1" name="current_password" autocomplete="current-password"></div>
    <button class="btn-primary"><i class="bx bx-save"></i> Speichern</button>
  </form>
  <form method="post" action="<?= url('account') ?>" class="card space-y-4 p-6">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2 class="font-semibold text-slate-900">Passwort ändern</h2>
    <div><label class="label" for="cp2">Aktuelles Passwort</label><input class="input" type="password" id="cp2" name="current_password" required autocomplete="current-password"></div>
    <div><label class="label" for="pw">Neues Passwort</label><input class="input" type="password" id="pw" name="password" required minlength="10" autocomplete="new-password"></div>
    <div><label class="label" for="pw2">Neues Passwort wiederholen</label><input class="input" type="password" id="pw2" name="password2" required minlength="10" autocomplete="new-password"></div>
    <button class="btn-primary"><i class="bx bx-lock-alt"></i> Passwort ändern</button>
    <p class="text-xs text-slate-500">Nach der Änderung werden alle anderen Sitzungen abgemeldet.</p>
  </form>
</div>

<?php if (in_array($user['role'], ['advertiser', 'publisher'], true)): $add = $user['role'] === 'advertiser' ? 'publisher' : 'advertiser'; ?>
<div class="card mt-6 flex flex-wrap items-center justify-between gap-4 p-6">
  <div><h2 class="font-semibold text-slate-900"><?= $add === 'publisher' ? 'Auch Werbeplätze anbieten?' : 'Auch Werbung schalten?' ?></h2>
    <p class="mt-1 text-sm text-slate-600">Ein Konto kann beides: <?= $add === 'publisher' ? 'Eigene Webseiten und Werbeplätze anbieten und Einnahmen erzielen.' : 'Kampagnen anlegen und Werbeplätze anderer Betreiber buchen.' ?> Alle Zahlungen laufen weiterhin zentral über <?= e(setting('platform_name')) ?>.</p></div>
  <form method="post" action="<?= url('account') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add_role"><input type="hidden" name="role" value="<?= $add ?>">
    <button class="btn-secondary"><i class="bx bx-plus"></i> <?= $add === 'publisher' ? 'Als Betreiber aktivieren' : 'Als Werbetreibender aktivieren' ?></button></form>
</div>
<?php endif; ?>

<section class="mt-6 grid gap-6 lg:grid-cols-2" id="datenschutz">
  <div class="card space-y-4 p-6">
    <h2 class="flex items-center gap-2 font-semibold text-slate-900"><i class="bx bx-shield-quarter text-xl text-brand-600"></i> Meine Daten (Datenschutz)</h2>
    <p class="text-sm text-slate-600">Sie haben das Recht auf Auskunft und Datenübertragbarkeit (Art. 15 und 20 DSGVO). Der Export enthält Ihr Profil, Webseiten, Werbeplätze, Kampagnen, Buchungen, Zahlungen, Auszahlungen, Abrechnungsjournal und Ihr Protokoll als JSON-Datei.</p>
    <?php if ($user['consent_at']): ?><p class="text-xs text-slate-500">Nutzungsbedingungen akzeptiert am <?= fdate($user['consent_at'], true) ?> (Fassung <?= e((string) $user['consent_version']) ?>).</p><?php endif; ?>
    <form method="post" action="<?= url('account.export') ?>" class="space-y-3"><?= csrf_field() ?>
      <div><label class="label" for="cp3">Passwort zur Bestätigung</label><input class="input" type="password" id="cp3" name="current_password" required autocomplete="current-password"></div>
      <button class="btn-secondary"><i class="bx bx-download"></i> Daten exportieren (JSON)</button>
    </form>
    <p class="text-xs text-slate-500">Weitere Informationen: <a class="link" href="<?= url('privacy') ?>">Datenschutzerklärung</a>.</p>
  </div>
  <div class="card space-y-4 p-6">
    <h2 class="flex items-center gap-2 font-semibold text-rose-700"><i class="bx bx-trash text-xl"></i> Konto löschen</h2>
    <p class="text-sm text-slate-600">Ihr Konto, Ihre Webseiten, Werbeplätze und Kampagnen werden gelöscht. Offene, unbezahlte Anfragen werden storniert.
      Daten zu bezahlten Buchungen und Auszahlungen müssen wir gesetzlich <?= Privacy::retentionYears() ?> Jahre aufbewahren: Sie werden gesperrt, nicht mehr genutzt und nach Fristablauf anonymisiert. Dies kann nicht rückgängig gemacht werden.</p>
    <?php if ($blockers): ?>
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><p class="font-medium">Derzeit nicht möglich:</p><ul class="mt-1 list-disc space-y-1 pl-5"><?php foreach ($blockers as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul></div>
    <?php else: ?>
    <form method="post" action="<?= url('account.delete') ?>" class="space-y-3" data-confirm="Konto jetzt endgültig löschen?"><?= csrf_field() ?>
      <div><label class="label" for="cp4">Passwort zur Bestätigung</label><input class="input" type="password" id="cp4" name="current_password" required autocomplete="current-password"></div>
      <label class="flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" name="confirm" value="1" class="mt-1 accent-rose-600" required> Ich möchte mein Konto unwiderruflich löschen.</label>
      <button class="btn-danger"><i class="bx bx-trash"></i> Konto löschen</button>
    </form>
    <?php endif; ?>
  </div>
</section>
