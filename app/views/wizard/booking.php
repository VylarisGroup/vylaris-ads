<?php
/** @var list<array> $slots @var list<array> $campaigns @var int $pendingCampaigns @var int $preselect @var bool $hasOld */
$sel = (int) old('slot_id', (string) $preselect);
$minToday = today();
?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => $title, 'sub' => 'Platz wählen, Kampagne zuordnen, Zeitraum festlegen – der Betreiber prüft Ihre Anfrage.',
  'actions' => '<a class="btn-ghost" href="' . e(url('home')) . '"><i class="bx bx-store-alt"></i> Marktplatz durchsuchen</a>']); ?>
<?php if (!$slots): ?>
  <?php partial('empty', ['icon' => 'bx-store-alt', 'text' => 'Derzeit keine buchbaren Werbeplätze', 'hint' => 'Sobald Betreiber Werbeplätze freigegeben haben, erscheinen sie hier.']); ?>
<?php else: ?>
<form method="post" action="<?= url('booking.request') ?>" class="card p-6" data-wizard data-wizard-booking data-start="<?= $hasOld ? 'last' : ($sel ? '1' : '0') ?>"
      data-quote data-price="0" data-min="1" data-max="365" data-currency="<?= e(setting('currency')) ?>">
  <?= csrf_field() ?>
  <div class="mb-6" data-wizard-steps></div>

  <fieldset class="min-w-0 space-y-5" data-step="Werbeplatz">
    <legend class="text-lg font-semibold text-slate-900">Welcher Werbeplatz?</legend>
    <p class="-mt-3 text-sm text-slate-500">Alle derzeit buchbaren Plätze. Tipp: Auf dem Marktplatz können Sie vorab filtern.</p>
    <div>
      <label class="label" for="slot_id">Werbeplatz</label>
      <select class="input" id="slot_id" name="slot_id" data-slot-select data-sum="Werbeplatz" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($slots as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $sel === (int) $s['id'] ? 'selected' : '' ?>
            data-w="<?= (int) $s['width'] ?>" data-h="<?= (int) $s['height'] ?>" data-price="<?= (int) $s['price_per_day'] ?>"
            data-min="<?= (int) $s['min_days'] ?>" data-max="<?= (int) $s['max_days'] ?>"
            data-from="<?= e(max($s['available_from'], $minToday)) ?>" data-until="<?= e((string) $s['available_until']) ?>"
            data-site="<?= e($s['site_name']) ?>" data-cat="<?= e($s['category']) ?>" data-pricetext="<?= e(money($s['price_per_day'])) ?>">
            <?= e($s['site_name'] . ' – ' . $s['name'] . ' (' . $s['width'] . '×' . $s['height'] . ', ' . money($s['price_per_day']) . '/Tag)') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="hidden rounded-lg bg-slate-50 p-4 text-sm" data-slot-info>
      <p class="font-medium text-slate-900" data-info-title></p>
      <p class="mt-1 text-slate-600" data-info-detail></p>
      <p class="mt-2"><a class="link" href="#" target="_blank" rel="noopener" data-info-link>Details ansehen <i class="bx bx-link-external"></i></a></p>
    </div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Kampagne">
    <legend class="text-lg font-semibold text-slate-900">Welche Kampagne?</legend>
    <p class="-mt-3 text-sm text-slate-500">Es werden nur freigegebene Kampagnen angeboten, deren Banner genau zur Grösse des Werbeplatzes passt.</p>
    <div>
      <label class="label" for="campaign_id">Kampagne</label>
      <select class="input" id="campaign_id" name="campaign_id" data-campaign-select data-sum="Kampagne" required>
        <option value="">Bitte wählen</option>
        <?php foreach ($campaigns as $c): ?>
          <option value="<?= (int) $c['id'] ?>" data-w="<?= (int) $c['banner_width'] ?>" data-h="<?= (int) $c['banner_height'] ?>" <?= (int) old('campaign_id', '0') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name'] . ' (' . $c['banner_width'] . '×' . $c['banner_height'] . ')') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="hidden rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-no-campaign>
      <p data-no-campaign-text>Keine passende Kampagne vorhanden.</p>
      <?php if ($pendingCampaigns > 0): ?><p class="mt-1">Sie haben <?= $pendingCampaigns ?> Kampagne(n) in Prüfung – diese sind nach der Freigabe buchbar.</p><?php endif; ?>
      <a class="btn-primary mt-3 inline-flex" href="<?= url('wizard.campaign') ?>"><i class="bx bx-magic-wand"></i> Kampagne mit Assistent erstellen</a>
    </div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Zeitraum">
    <legend class="text-lg font-semibold text-slate-900">Wann soll die Anzeige laufen?</legend>
    <p class="-mt-3 text-sm text-slate-500" data-duration-hint>Wählen Sie Start und Ende (beide Tage inklusive).</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="start_date">Start</label><input class="input" type="date" id="start_date" name="start_date" data-sum="Start" value="<?= e(old('start_date')) ?>" min="<?= e($minToday) ?>" required></div>
      <div><label class="label" for="end_date">Ende</label><input class="input" type="date" id="end_date" name="end_date" data-sum="Ende" value="<?= e(old('end_date')) ?>" min="<?= e($minToday) ?>" required></div>
    </div>
    <div class="rounded-lg bg-slate-50 p-3 text-sm" data-quote-output><span class="text-slate-500">Zeitraum wählen, um den Preis zu berechnen.</span></div>
    <div><label class="label" for="message">Nachricht an den Betreiber <span class="text-slate-400">(optional)</span></label><textarea class="input" id="message" name="message" data-sum="Nachricht" rows="3" maxlength="1000"><?= e(old('message')) ?></textarea></div>
  </fieldset>

  <fieldset class="hidden min-w-0 space-y-4" data-step="Prüfen" data-summary>
    <legend class="text-lg font-semibold text-slate-900">Alles korrekt?</legend>
    <p class="-mt-2 text-sm text-slate-500">Mit „Anfrage senden“ geht Ihre Anfrage an den Webseitenbetreiber. Bezahlt wird erst nach dessen Annahme.</p>
    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200 px-4" data-summary-list></dl>
    <div class="rounded-lg bg-slate-50 p-3 text-sm" data-quote-output></div>
    <p class="text-xs text-slate-500">Der verbindliche Preis wird serverseitig berechnet.</p>
  </fieldset>

  <?php partial('wizard_foot', ['cancel' => url('bookings'), 'submit' => 'Anfrage senden', 'icon' => 'bx-send']); ?>
</form>
<?php endif; ?>
</div>
