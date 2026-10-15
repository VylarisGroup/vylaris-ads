<?php $s = $slot;
$curFormat = old('format', $s ? (isset(AD_FORMATS[$s['format']]) ? $s['format'] : 'custom') : '300x250'); ?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => $title, 'sub' => 'Webseite: ' . $site['name']]); ?>
<form method="post" action="<?= url('slot.form', $s ? ['id' => $s['id']] : ['site_id' => $site['id']]) ?>" class="card space-y-5 p-6" data-format-form>
  <?= csrf_field() ?>
  <div><label class="label" for="name">Bezeichnung</label><input class="input" id="name" name="name" value="<?= e(old('name', $s['name'] ?? '')) ?>" required maxlength="160" placeholder="z. B. Sidebar rechts, Startseite"></div>
  <div class="grid gap-4 sm:grid-cols-3">
    <div class="sm:col-span-3"><label class="label" for="format">Format</label>
      <select class="input" id="format" name="format" required>
        <?php foreach (AD_FORMATS as $k => $v): ?><option value="<?= $k ?>" <?= $curFormat === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        <option value="custom" <?= $curFormat === 'custom' ? 'selected' : '' ?>>Eigenes Format …</option>
      </select></div>
    <div data-custom-size><label class="label" for="width">Breite (px)</label><input class="input" type="number" min="10" max="2000" id="width" name="width" value="<?= e(old('width', (string) ($s['width'] ?? ''))) ?>"></div>
    <div data-custom-size><label class="label" for="height">Höhe (px)</label><input class="input" type="number" min="10" max="2000" id="height" name="height" value="<?= e(old('height', (string) ($s['height'] ?? ''))) ?>"></div>
  </div>
  <div><label class="label" for="placement">Platzierung <span class="text-slate-400">(optional)</span></label><input class="input" id="placement" name="placement" value="<?= e(old('placement', $s['placement'] ?? '')) ?>" maxlength="160" placeholder="z. B. Header aller Artikelseiten"></div>
  <div><label class="label" for="description">Beschreibung</label><textarea class="input" id="description" name="description" rows="4" required maxlength="3000"><?= e(old('description', $s['description'] ?? '')) ?></textarea></div>
  <div class="grid gap-4 sm:grid-cols-3">
    <div><label class="label" for="price_per_day">Preis pro Tag (<?= e(setting('currency')) ?>)</label><input class="input" inputmode="decimal" id="price_per_day" name="price_per_day" value="<?= e(old('price_per_day', $s ? money_input($s['price_per_day']) : '')) ?>" required placeholder="10.00"></div>
    <div><label class="label" for="min_days">Mindestlaufzeit (Tage)</label><input class="input" type="number" min="1" max="365" id="min_days" name="min_days" value="<?= e(old('min_days', (string) ($s['min_days'] ?? 7))) ?>" required></div>
    <div><label class="label" for="max_days">Höchstlaufzeit (Tage)</label><input class="input" type="number" min="1" max="365" id="max_days" name="max_days" value="<?= e(old('max_days', (string) ($s['max_days'] ?? 90))) ?>" required></div>
  </div>
  <div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label" for="available_from">Verfügbar ab</label><input class="input" type="date" id="available_from" name="available_from" value="<?= e(old('available_from', $s['available_from'] ?? today())) ?>" required></div>
    <div><label class="label" for="available_until">Verfügbar bis <span class="text-slate-400">(optional)</span></label><input class="input" type="date" id="available_until" name="available_until" value="<?= e(old('available_until', (string) ($s['available_until'] ?? ''))) ?>"></div>
  </div>
  <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><i class="bx bx-info-circle"></i> Von jedem Buchungsbetrag behält die Plattform derzeit <?= e(percent_bp(setting('commission_bp'))) ?> Provision ein. Preisänderungen gelten nur für neue Buchungen.</p>
  <div class="flex gap-2"><button class="btn-primary"><i class="bx bx-save"></i> Speichern</button><a class="btn-ghost" href="<?= url('sites') ?>">Abbrechen</a></div>
</form>
</div>
