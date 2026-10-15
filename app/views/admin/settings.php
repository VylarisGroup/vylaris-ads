<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => 'Einstellungen', 'sub' => 'Plattform, Provision und Zahlungsinformationen']); ?>
<form method="post" action="<?= url('admin.settings') ?>" class="card space-y-5 p-6">
  <?= csrf_field() ?>
  <div><label class="label" for="platform_name">Plattformname</label><input class="input" id="platform_name" name="platform_name" value="<?= e(old('platform_name', setting('platform_name'))) ?>" required maxlength="60"></div>
  <div class="grid gap-4 sm:grid-cols-3">
    <div><label class="label" for="commission">Provision (%)</label><input class="input" inputmode="decimal" id="commission" name="commission" value="<?= e(old('commission', rtrim(rtrim(number_format((int) setting('commission_bp') / 100, 2, '.', ''), '0'), '.'))) ?>" required></div>
    <div><label class="label" for="currency">Währung</label><input class="input uppercase" id="currency" name="currency" value="<?= e(old('currency', setting('currency'))) ?>" required maxlength="3" <?= $hasBookings ? 'readonly' : '' ?>>
      <?php if ($hasBookings): ?><p class="mt-1 text-xs text-slate-500">Gesperrt, da Buchungen existieren.</p><?php endif; ?></div>
    <div><label class="label" for="invoice_prefix">Belegpräfix</label><input class="input uppercase" id="invoice_prefix" name="invoice_prefix" value="<?= e(old('invoice_prefix', setting('invoice_prefix'))) ?>" required maxlength="8"></div>
  </div>
  <div><label class="label" for="min_payout">Mindestbetrag für Teilauszahlungen</label><input class="input" inputmode="decimal" id="min_payout" name="min_payout" value="<?= e(old('min_payout', money_input(setting('min_payout')))) ?>" required></div>
  <div><label class="label" for="bank_details">Zahlungsinformationen für Werbetreibende</label><textarea class="input font-mono text-sm" id="bank_details" name="bank_details" rows="5" required maxlength="1000"><?= e(old('bank_details', setting('bank_details'))) ?></textarea>
    <p class="mt-1 text-xs text-slate-500">Wird nach Annahme einer Buchung zusammen mit Betrag und Referenz angezeigt.</p></div>
  <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><i class="bx bx-info-circle"></i> Provision und Preis werden bei jeder Buchungsanfrage festgeschrieben. Änderungen wirken sich nur auf neue Anfragen aus.</p>
  <button class="btn-primary"><i class="bx bx-save"></i> Speichern</button>
</form>
<form method="post" action="<?= url('admin.legal') ?>" id="rechtliches" class="card mt-6 space-y-4 p-6">
  <?= csrf_field() ?>
  <div>
    <h2 class="font-semibold text-slate-900">Rechtliches &amp; Datenschutz</h2>
    <p class="mt-1 text-xs text-slate-500">Diese Angaben füllen Impressum, Datenschutzerklärung und Nutzungsbedingungen. Die Standardtexte sind Vorlagen und ersetzen keine Rechtsberatung – bitte prüfen Sie sie vor dem Livebetrieb.
      Vorschau: <a class="link" href="<?= url('privacy') ?>" target="_blank">Datenschutz</a> · <a class="link" href="<?= url('imprint') ?>" target="_blank">Impressum</a> · <a class="link" href="<?= url('terms') ?>" target="_blank">Nutzungsbedingungen</a></p>
  </div>
  <div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label" for="legal_name">Betreiber (Firma / Name)</label><input class="input" id="legal_name" name="legal_name" value="<?= e(setting('legal_name')) ?>" maxlength="160"></div>
    <div><label class="label" for="legal_email">Kontakt-E-Mail (auch für Datenschutzanfragen)</label><input class="input" type="email" id="legal_email" name="legal_email" value="<?= e(setting('legal_email')) ?>" maxlength="190"></div>
    <div class="sm:col-span-2"><label class="label" for="legal_address">Anschrift</label><textarea class="input" id="legal_address" name="legal_address" rows="3" maxlength="500"><?= e(setting('legal_address')) ?></textarea></div>
    <div><label class="label" for="legal_phone">Telefon <span class="text-slate-400">(optional)</span></label><input class="input" id="legal_phone" name="legal_phone" value="<?= e(setting('legal_phone')) ?>" maxlength="40"></div>
    <div><label class="label" for="legal_representative">Vertretungsberechtigt <span class="text-slate-400">(optional)</span></label><input class="input" id="legal_representative" name="legal_representative" value="<?= e(setting('legal_representative')) ?>" maxlength="190"></div>
    <div class="sm:col-span-2"><label class="label" for="legal_register">Handelsregister / UID / USt-IdNr. <span class="text-slate-400">(optional)</span></label><textarea class="input" id="legal_register" name="legal_register" rows="2" maxlength="500"><?= e(setting('legal_register')) ?></textarea></div>
    <div class="sm:col-span-2"><label class="label" for="legal_hoster">Hosting-Anbieter (Name, Sitz)</label><input class="input" id="legal_hoster" name="legal_hoster" value="<?= e(setting('legal_hoster')) ?>" maxlength="300" placeholder="z. B. Beispiel Hosting AG, Zürich">
      <p class="mt-1 text-xs text-slate-500">Mit dem Anbieter muss ein Auftragsverarbeitungsvertrag (Art. 28 DSGVO) bestehen.</p></div>
  </div>
  <details class="rounded-lg border border-slate-200 p-3">
    <summary class="cursor-pointer text-sm font-medium text-slate-700">Eigene Texte statt der Standardvorlagen (optional)</summary>
    <p class="mt-2 text-xs text-slate-500">Leer lassen = Standardtext. Format: <code>## Überschrift</code>, Absätze durch Leerzeile, Listen mit <code>- </code>. HTML wird nicht ausgeführt.</p>
    <?php foreach (Legal::PAGES as $page => $label): ?>
      <div class="mt-3"><label class="label" for="legal_<?= $page ?>_custom"><?= e($label) ?></label><textarea class="input font-mono text-xs" id="legal_<?= $page ?>_custom" name="legal_<?= $page ?>_custom" rows="6" maxlength="30000"><?= e(setting('legal_' . $page . '_custom')) ?></textarea></div>
    <?php endforeach; ?>
  </details>
  <p class="text-xs text-slate-500">Aktuelle Fassung: <?= e(Legal::version()) ?> · Aufbewahrung Abrechnungsdaten: <?= Privacy::retentionYears() ?> Jahre · IP-Adressen im Protokoll: <?= (int) config('privacy.log_ip_days', 30) ?> Tage (Änderung in <code>config/config.php</code>).</p>
  <button class="btn-primary"><i class="bx bx-save"></i> Rechtliches speichern</button>
</form>
<div class="card mt-6 p-6 text-sm text-slate-600">
  <h2 class="font-semibold text-slate-900">System</h2>
  <dl class="mt-3 space-y-1">
    <div class="flex justify-between"><dt>Version</dt><dd><?= APP_VERSION ?> · <a class="link" href="<?= url('admin.update') ?>">Updates</a></dd></div>
    <div class="flex justify-between"><dt>PHP</dt><dd><?= PHP_VERSION ?></dd></div>
    <div class="flex justify-between"><dt>Datenbank</dt><dd><?= e((string) val('SELECT VERSION()')) ?></dd></div>
    <div class="flex justify-between gap-4"><dt>Lizenz</dt><dd class="text-right"><a class="link" href="<?= url('admin.licence') ?>"><?= e(licence()->status()) ?></a></dd></div>
    <div class="flex justify-between"><dt>Letzte Statuspflege</dt><dd><?= setting('housekeeping_at') ? date('d.m.Y H:i', (int) setting('housekeeping_at')) : '–' ?></dd></div>
  </dl>
</div>
</div>
