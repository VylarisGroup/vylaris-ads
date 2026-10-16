<?php
/** @var ?array $site @var bool $hasOld */
$new = !$site;
$curFormat = old('slot_format', '300x250');
$start = $hasOld ? 'last' : '0';
?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', [
  'heading' => $title,
  'sub' => $new ? 'In wenigen Schritten zur ersten buchbaren Anzeigenfläche.' : 'Neuer Werbeplatz für ' . $site['name'] . '.',
  'actions' => '<a class="btn-ghost" href="' . e($new ? url('site.form') : url('slot.form', ['site_id' => $site['id']])) . '"><i class="bx bx-list-ul"></i> Klassisches Formular</a>',
]); ?>
<form method="post" action="<?= url('wizard.site') ?>" class="card p-6" data-wizard data-start="<?= $start ?>" data-format-form>
  <?= csrf_field() ?>
  <?php if (!$new): ?><input type="hidden" name="site_id" value="<?= (int) $site['id'] ?>"><?php endif; ?>
  <div class="mb-6" data-wizard-steps></div>

  <?php if ($new): ?>
  <fieldset class="min-w-0 space-y-5" data-step="Webseite">
    <legend class="text-lg font-semibold text-slate-900">Ihre Webseite</legend>
    <p class="-mt-3 text-sm text-slate-500">Wo sollen die Anzeigen erscheinen? Ein Administrator prüft die Angaben vor der Freigabe.</p>
    <div><label class="label" for="site_name">Name der Webseite</label><input class="input" id="site_name" name="site_name" data-sum="Webseite" value="<?= e(old('site_name')) ?>" required minlength="2" maxlength="160" placeholder="z. B. Tech Blog"></div>
    <div><label class="label" for="site_url">Adresse (URL)</label><input class="input" type="url" id="site_url" name="site_url" data-sum="Adresse" value="<?= e(old('site_url')) ?>" required maxlength="255" placeholder="https://"></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="site_category">Kategorie</label>
        <select class="input" id="site_category" name="site_category" data-sum="Kategorie" required><option value="">Bitte wählen</option>
        <?php foreach (SITE_CATEGORIES as $c): ?><option <?= old('site_category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div><label class="label" for="site_monthly_visitors">Besucher pro Monat <span class="text-slate-400">(ca.)</span></label><input class="input" type="number" min="0" id="site_monthly_visitors" name="site_monthly_visitors" data-sum="Besucher pro Monat" value="<?= e(old('site_monthly_visitors')) ?>"></div>
    </div>
    <div><label class="label" for="site_description">Beschreibung &amp; Zielgruppe</label><textarea class="input" id="site_description" name="site_description" data-sum="Beschreibung" rows="4" required minlength="20" maxlength="3000"><?= e(old('site_description')) ?></textarea>
      <p class="mt-1 text-xs text-slate-500">Mindestens 20 Zeichen – worum geht es und wer liest mit?</p></div>
  </fieldset>
  <?php endif; ?>

  <fieldset class="min-w-0 space-y-5" data-step="Werbeplatz">
    <legend class="text-lg font-semibold text-slate-900">Der Werbeplatz</legend>
    <p class="-mt-3 text-sm text-slate-500">Welche Fläche bieten Sie an? Das Format bestimmt, welche Banner dort passen.</p>
    <div><label class="label" for="slot_name">Bezeichnung</label><input class="input" id="slot_name" name="slot_name" data-sum="Werbeplatz" value="<?= e(old('slot_name')) ?>" required minlength="2" maxlength="160" placeholder="z. B. Sidebar rechts, Startseite"></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2"><label class="label" for="slot_format">Format</label>
        <select class="input" id="slot_format" name="slot_format" data-format-select data-sum="Format" required>
          <?php foreach (AD_FORMATS as $k => $v): ?><option value="<?= $k ?>" <?= $curFormat === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
          <option value="custom" <?= $curFormat === 'custom' ? 'selected' : '' ?>>Eigenes Format …</option>
        </select></div>
      <div data-custom-size><label class="label" for="slot_width">Breite (px)</label><input class="input" type="number" min="10" max="2000" id="slot_width" name="slot_width" data-sum="Breite (px)" value="<?= e(old('slot_width')) ?>"></div>
      <div data-custom-size><label class="label" for="slot_height">Höhe (px)</label><input class="input" type="number" min="10" max="2000" id="slot_height" name="slot_height" data-sum="Höhe (px)" value="<?= e(old('slot_height')) ?>"></div>
    </div>
    <div><label class="label" for="slot_placement">Platzierung <span class="text-slate-400">(optional)</span></label><input class="input" id="slot_placement" name="slot_placement" data-sum="Platzierung" value="<?= e(old('slot_placement')) ?>" maxlength="160" placeholder="z. B. Header aller Artikelseiten"></div>
    <div><label class="label" for="slot_description">Beschreibung</label><textarea class="input" id="slot_description" name="slot_description" data-sum="Beschreibung Werbeplatz" rows="3" required minlength="10" maxlength="3000"><?= e(old('slot_description')) ?></textarea>
      <p class="mt-1 text-xs text-slate-500">Mindestens 10 Zeichen – Sichtbarkeit, Position, Besonderheiten.</p></div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Preis &amp; Laufzeit">
    <legend class="text-lg font-semibold text-slate-900">Preis und Verfügbarkeit</legend>
    <p class="-mt-3 text-sm text-slate-500">Festpreis pro Tag in <?= e(setting('currency')) ?>. Von jedem Buchungsbetrag behält die Plattform <?= e(percent_bp(setting('commission_bp'))) ?> Provision ein.</p>
    <div class="grid gap-4 sm:grid-cols-3">
      <div><label class="label" for="slot_price_per_day">Preis pro Tag (<?= e(setting('currency')) ?>)</label><input class="input" inputmode="decimal" id="slot_price_per_day" name="slot_price_per_day" data-sum="Preis pro Tag" value="<?= e(old('slot_price_per_day')) ?>" required placeholder="10.00"></div>
      <div><label class="label" for="slot_min_days">Mindestlaufzeit (Tage)</label><input class="input" type="number" min="1" max="365" id="slot_min_days" name="slot_min_days" data-sum="Mindestlaufzeit (Tage)" value="<?= e(old('slot_min_days', '7')) ?>" required></div>
      <div><label class="label" for="slot_max_days">Höchstlaufzeit (Tage)</label><input class="input" type="number" min="1" max="365" id="slot_max_days" name="slot_max_days" data-sum="Höchstlaufzeit (Tage)" value="<?= e(old('slot_max_days', '90')) ?>" required></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="slot_available_from">Verfügbar ab</label><input class="input" type="date" id="slot_available_from" name="slot_available_from" data-sum="Verfügbar ab" value="<?= e(old('slot_available_from', today())) ?>" required></div>
      <div><label class="label" for="slot_available_until">Verfügbar bis <span class="text-slate-400">(optional)</span></label><input class="input" type="date" id="slot_available_until" name="slot_available_until" data-sum="Verfügbar bis" value="<?= e(old('slot_available_until')) ?>"></div>
    </div>
  </fieldset>

  <fieldset class="hidden min-w-0 space-y-4" data-step="Prüfen" data-summary>
    <legend class="text-lg font-semibold text-slate-900">Alles korrekt?</legend>
    <p class="-mt-2 text-sm text-slate-500">Prüfen Sie Ihre Angaben. Mit „Anlegen“ <?= $new ? 'werden Webseite und Werbeplatz' : 'wird der Werbeplatz' ?> zur Prüfung eingereicht.</p>
    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200 px-4" data-summary-list></dl>
  </fieldset>

  <?php partial('wizard_foot', ['cancel' => url('sites'), 'submit' => 'Anlegen', 'icon' => 'bx-check']); ?>
</form>
</div>
