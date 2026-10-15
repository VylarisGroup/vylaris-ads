<?php
if (!empty($switch)) partial('role_switch', ['active' => 'publisher']);
partial('page_header', ['heading' => 'Hallo ' . current_user()['name'], 'sub' => 'Ihre Werbeplätze, Anfragen und Einnahmen',
  'actions' => '<a class="btn-primary" href="' . url('site.form') . '"><i class="bx bx-plus"></i> Webseite hinzufügen</a>']);
?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php partial('kpi', ['label' => 'Auszahlbar', 'value' => money($balance['available']), 'icon' => 'bx-wallet', 'hint' => 'aus abgeschlossenen Buchungen']); ?>
  <?php partial('kpi', ['label' => 'Ausstehende Erlöse', 'value' => money($balance['pending']), 'icon' => 'bx-hourglass', 'hint' => 'bezahlt, Laufzeit noch nicht beendet']); ?>
  <?php partial('kpi', ['label' => 'Werbeplätze', 'value' => (int) $counts['slots_live'] . ' / ' . (int) $counts['slots'], 'icon' => 'bx-layout', 'hint' => 'freigegeben / gesamt auf ' . (int) $counts['sites'] . ' Webseite(n)']); ?>
  <?php partial('kpi', ['label' => 'Impressionen / Klicks', 'value' => number_format((int) $traffic['impressions'], 0, '.', "'") . ' / ' . number_format((int) $traffic['clicks'], 0, '.', "'"), 'icon' => 'bx-bar-chart-alt-2', 'hint' => (int) $kpi['live'] . ' Buchung(en) laufen gerade']); ?>
</div>

<?php if ((int) $counts['sites'] === 0): ?>
  <div class="mt-6"><?php partial('empty', ['icon' => 'bx-globe', 'text' => 'Fügen Sie Ihre erste Webseite hinzu', 'hint' => 'Danach legen Sie Werbeplätze mit Format, Preis und Verfügbarkeit an.', 'action' => [url('site.form'), 'Webseite hinzufügen']]); ?></div>
<?php endif; ?>

<h2 class="mt-8 mb-3 flex items-center gap-2 font-semibold text-slate-900"><i class="bx bx-bell text-brand-600"></i> Offene Anfragen <span class="rounded-full bg-brand-100 px-2 text-xs text-brand-700"><?= (int) $kpi['requested'] ?></span></h2>
<?php if (!$requests): ?>
  <p class="card p-5 text-sm text-slate-500">Keine offenen Buchungsanfragen.</p>
<?php else: ?>
<div class="space-y-3">
  <?php foreach ($requests as $b): ?>
  <div class="card flex flex-wrap items-center justify-between gap-4 p-4">
    <div>
      <p class="font-medium text-slate-900"><?= e($b['adv_company'] ?: $b['adv_name']) ?> möchte <?= e($b['slot_name']) ?> buchen</p>
      <p class="text-sm text-slate-500"><?= fdate($b['start_date']) ?> – <?= fdate($b['end_date']) ?> (<?= (int) $b['days'] ?> Tage) · Ihr Erlös <?= e(money($b['publisher_amount'], $b['currency'])) ?></p>
    </div>
    <a class="btn-primary" href="<?= url('booking', ['id' => $b['id']]) ?>">Prüfen &amp; entscheiden</a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
  <div class="lg:col-span-2">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-slate-900">Laufende &amp; geplante Kampagnen</h2><a class="link text-sm" href="<?= url('bookings') ?>">Alle Buchungen</a></div>
    <?php if ($running): partial('booking_table', ['rows' => $running, 'stats' => $stats, 'as' => 'publisher']); else: ?>
      <p class="card p-5 text-sm text-slate-500">Derzeit keine bezahlten Buchungen.</p>
    <?php endif; ?>
  </div>
  <div><?php partial('chart', ['rows' => $monthly, 'key' => 'publisher', 'label' => 'Erlöse (6 Monate)']); ?></div>
</div>
