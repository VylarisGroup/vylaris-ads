<?php $me = current_user(); ?>
<a href="<?= url('home') ?>" class="link mb-4 inline-flex items-center gap-1 text-sm"><i class="bx bx-arrow-back"></i> Zum Marktplatz</a>
<?php if (!$bookable): ?>
  <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><i class="bx bx-info-circle"></i> Vorschau: Dieser Werbeplatz ist derzeit nicht öffentlich buchbar (Status Werbeplatz: <?= badge($slot['status']) ?>, Webseite: <?= badge($slot['site_status']) ?>).</div>
<?php endif; ?>
<div class="grid gap-6 lg:grid-cols-3">
  <div class="space-y-6 lg:col-span-2">
    <div class="card p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-sm text-slate-500"><?= e($slot['category']) ?></p>
          <h1 class="text-2xl font-semibold text-slate-900"><?= e($slot['name']) ?></h1>
          <p class="mt-1 text-slate-600">auf <a class="link" href="<?= e($slot['site_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($slot['site_name']) ?> <i class="bx bx-link-external"></i></a></p>
        </div>
        <span class="rounded-lg bg-brand-50 px-3 py-1 text-sm font-medium text-brand-700"><?= e(AD_FORMATS[$slot['format']] ?? $slot['format']) ?></span>
      </div>
      <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div><dt class="text-slate-500">Format</dt><dd class="font-medium"><?= (int) $slot['width'] ?>×<?= (int) $slot['height'] ?> px</dd></div>
        <div><dt class="text-slate-500">Laufzeit</dt><dd class="font-medium"><?= (int) $slot['min_days'] ?>–<?= (int) $slot['max_days'] ?> Tage</dd></div>
        <div><dt class="text-slate-500">Verfügbar ab</dt><dd class="font-medium"><?= fdate(max($slot['available_from'], today())) ?></dd></div>
        <div><dt class="text-slate-500">Verfügbar bis</dt><dd class="font-medium"><?= $slot['available_until'] ? fdate($slot['available_until']) : 'unbegrenzt' ?></dd></div>
      </dl>
      <?php if ($slot['placement'] !== ''): ?><p class="mt-4 text-sm"><span class="text-slate-500">Platzierung:</span> <?= e($slot['placement']) ?></p><?php endif; ?>
      <h2 class="mt-6 font-medium text-slate-900">Beschreibung</h2>
      <p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= e($slot['description']) ?></p>
    </div>
    <div class="card p-6">
      <h2 class="font-medium text-slate-900">Über die Webseite</h2>
      <p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= e($slot['site_description']) ?></p>
      <p class="mt-3 text-sm text-slate-500"><i class="bx bx-user"></i> ca. <?= number_format((int) $slot['monthly_visitors'], 0, '.', "'") ?> Besucher pro Monat (Angabe des Betreibers)</p>
    </div>
    <div class="card p-6">
      <h2 class="flex items-center gap-2 font-medium text-slate-900"><i class="bx bx-calendar-x"></i> Belegte Zeiträume</h2>
      <?php if (!$ranges): ?>
        <p class="mt-2 text-sm text-slate-500">Aktuell sind keine Zeiträume belegt.</p>
      <?php else: ?>
        <ul class="mt-3 flex flex-wrap gap-2">
          <?php foreach ($ranges as $r): ?><li class="rounded-md bg-rose-50 px-3 py-1 text-sm text-rose-700"><?= fdate($r['start_date']) ?> – <?= fdate($r['end_date']) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <aside class="lg:sticky lg:top-24 lg:self-start">
    <div class="card p-6">
      <p class="text-3xl font-semibold text-slate-900"><?= e(money($slot['price_per_day'])) ?> <span class="text-base font-normal text-slate-500">/ Tag</span></p>
      <p class="mt-1 text-xs text-slate-500">Festpreis · Abrechnung per Überweisung nach Annahme durch den Betreiber</p>
      <?php if ($bookable && can_advertise($me)): ?>
        <?php if (!$campaigns): ?>
          <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            Sie benötigen eine freigegebene Kampagne mit einem Banner im Format <strong><?= (int) $slot['width'] ?>×<?= (int) $slot['height'] ?></strong>.
            <a class="btn-primary mt-3 w-full justify-center" href="<?= url('campaign.form') ?>"><i class="bx bx-plus"></i> Kampagne anlegen</a>
          </div>
        <?php else: ?>
          <form method="post" action="<?= url('booking.request') ?>" class="mt-5 space-y-4" data-quote data-price="<?= (int) $slot['price_per_day'] ?>" data-min="<?= (int) $slot['min_days'] ?>" data-max="<?= (int) $slot['max_days'] ?>" data-currency="<?= e(setting('currency')) ?>">
            <?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= (int) $slot['id'] ?>">
            <div><label class="label" for="campaign_id">Kampagne</label>
              <select class="input" id="campaign_id" name="campaign_id" required>
                <?php foreach ($campaigns as $c): ?><option value="<?= (int) $c['id'] ?>" <?= old('campaign_id') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
              </select></div>
            <?php $minStart = max($slot['available_from'], today()); ?>
            <div class="grid grid-cols-2 gap-3">
              <div><label class="label" for="start_date">Start</label><input class="input" type="date" id="start_date" name="start_date" min="<?= e($minStart) ?>" <?= $slot['available_until'] ? 'max="' . e($slot['available_until']) . '"' : '' ?> value="<?= e(old('start_date')) ?>" required></div>
              <div><label class="label" for="end_date">Ende</label><input class="input" type="date" id="end_date" name="end_date" min="<?= e($minStart) ?>" <?= $slot['available_until'] ? 'max="' . e($slot['available_until']) . '"' : '' ?> value="<?= e(old('end_date')) ?>" required></div>
            </div>
            <div><label class="label" for="message">Nachricht an den Betreiber <span class="text-slate-400">(optional)</span></label><textarea class="input" id="message" name="message" rows="3" maxlength="1000"><?= e(old('message')) ?></textarea></div>
            <div class="rounded-lg bg-slate-50 p-3 text-sm" data-quote-output>
              <span class="text-slate-500">Zeitraum wählen, um den Preis zu berechnen.</span>
            </div>
            <button class="btn-primary w-full justify-center"><i class="bx bx-send"></i> Buchung anfragen</button>
            <p class="text-xs text-slate-500">Der verbindliche Preis wird serverseitig berechnet. Sie zahlen erst, nachdem der Betreiber angenommen hat.</p>
          </form>
        <?php endif; ?>
      <?php elseif (!$me): ?>
        <a class="btn-primary mt-5 w-full justify-center" href="<?= url('login') ?>"><i class="bx bx-log-in"></i> Anmelden zum Buchen</a>
        <a class="btn-secondary mt-2 w-full justify-center" href="<?= url('register', ['role' => 'advertiser']) ?>">Kostenlos registrieren</a>
      <?php elseif ($bookable): ?>
        <p class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Buchungen sind nur mit einem Konto als Werbetreibender möglich.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>
