<?php /** @var bool $hasBookings @var bool $hasOld */ ?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => $title, 'sub' => 'Die wichtigsten Angaben für den Betrieb – jederzeit wiederholbar.',
  'actions' => '<a class="btn-ghost" href="' . e(url('admin.settings')) . '"><i class="bx bx-list-ul"></i> Alle Einstellungen</a>']); ?>
<form method="post" action="<?= url('wizard.setup') ?>" class="card p-6" data-wizard data-start="<?= $hasOld ? 'last' : '0' ?>">
  <?= csrf_field() ?>
  <div class="mb-6" data-wizard-steps></div>

  <fieldset class="min-w-0 space-y-5" data-step="Plattform">
    <legend class="text-lg font-semibold text-slate-900">Ihre Plattform</legend>
    <p class="-mt-3 text-sm text-slate-500">Name, Währung und Beleg-Präfix erscheinen in der Oberfläche und auf Rechnungen.</p>
    <div><label class="label" for="platform_name">Plattformname</label><input class="input" id="platform_name" name="platform_name" data-sum="Plattformname" value="<?= e(old('platform_name', setting('platform_name'))) ?>" required minlength="2" maxlength="60"></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="currency">Währung</label><input class="input uppercase" id="currency" name="currency" data-sum="Währung" value="<?= e(old('currency', setting('currency'))) ?>" required minlength="3" maxlength="3" <?= $hasBookings ? 'readonly' : '' ?>>
        <p class="mt-1 text-xs text-slate-500"><?= $hasBookings ? 'Gesperrt, da bereits Buchungen existieren.' : 'ISO-Code, z. B. CHF oder EUR.' ?></p></div>
      <div><label class="label" for="invoice_prefix">Belegpräfix</label><input class="input uppercase" id="invoice_prefix" name="invoice_prefix" data-sum="Belegpräfix" value="<?= e(old('invoice_prefix', setting('invoice_prefix'))) ?>" required minlength="2" maxlength="8"></div>
    </div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Provision">
    <legend class="text-lg font-semibold text-slate-900">Provision und Auszahlung</legend>
    <p class="-mt-3 text-sm text-slate-500">Die Provision wird bei jeder Buchung festgeschrieben und gilt nur für neue Anfragen.</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="commission">Provision (%)</label><input class="input" inputmode="decimal" id="commission" name="commission" data-sum="Provision (%)" value="<?= e(old('commission', rtrim(rtrim(number_format((int) setting('commission_bp') / 100, 2, '.', ''), '0'), '.'))) ?>" required></div>
      <div><label class="label" for="min_payout">Mindestbetrag für Teilauszahlungen</label><input class="input" inputmode="decimal" id="min_payout" name="min_payout" data-sum="Mindestauszahlung" value="<?= e(old('min_payout', money_input(setting('min_payout')))) ?>" required></div>
    </div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Zahlung">
    <legend class="text-lg font-semibold text-slate-900">Zahlungsinformationen</legend>
    <p class="-mt-3 text-sm text-slate-500">Diese Angaben sehen Werbetreibende nach Annahme einer Buchung – zusammen mit Betrag und Referenz.</p>
    <div><label class="label" for="bank_details">Bankverbindung</label><textarea class="input font-mono text-sm" id="bank_details" name="bank_details" data-sum="Bankverbindung" rows="5" required minlength="5" maxlength="1000"><?= e(old('bank_details', setting('bank_details'))) ?></textarea></div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Rechtliches">
    <legend class="text-lg font-semibold text-slate-900">Betreiberangaben</legend>
    <p class="-mt-3 text-sm text-slate-500">Füllen Impressum und Datenschutzerklärung. Weitere Felder und eigene Texte unter Einstellungen → Rechtliches.</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="legal_name">Betreiber (Firma / Name)</label><input class="input" id="legal_name" name="legal_name" data-sum="Betreiber" value="<?= e(old('legal_name', setting('legal_name'))) ?>" maxlength="160"></div>
      <div><label class="label" for="legal_email">Kontakt-E-Mail</label><input class="input" type="email" id="legal_email" name="legal_email" data-sum="Kontakt-E-Mail" value="<?= e(old('legal_email', setting('legal_email'))) ?>" maxlength="190"></div>
      <div class="sm:col-span-2"><label class="label" for="legal_address">Anschrift</label><textarea class="input" id="legal_address" name="legal_address" data-sum="Anschrift" rows="3" maxlength="500"><?= e(old('legal_address', setting('legal_address'))) ?></textarea></div>
      <div><label class="label" for="legal_phone">Telefon <span class="text-slate-400">(optional)</span></label><input class="input" id="legal_phone" name="legal_phone" data-sum="Telefon" value="<?= e(old('legal_phone', setting('legal_phone'))) ?>" maxlength="40"></div>
      <div><label class="label" for="legal_hoster">Hosting-Anbieter</label><input class="input" id="legal_hoster" name="legal_hoster" data-sum="Hosting-Anbieter" value="<?= e(old('legal_hoster', setting('legal_hoster'))) ?>" maxlength="300" placeholder="Name, Sitz"></div>
    </div>
  </fieldset>

  <fieldset class="hidden min-w-0 space-y-4" data-step="Prüfen" data-summary>
    <legend class="text-lg font-semibold text-slate-900">Alles korrekt?</legend>
    <p class="-mt-2 text-sm text-slate-500">Die Angaben werden gespeichert und sind sofort aktiv.</p>
    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200 px-4" data-summary-list></dl>
  </fieldset>

  <?php partial('wizard_foot', ['cancel' => url('dashboard'), 'submit' => 'Speichern', 'icon' => 'bx-save']); ?>
</form>
</div>
