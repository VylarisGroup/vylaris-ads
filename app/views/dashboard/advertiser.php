<?php
if (!empty($switch)) partial('role_switch', ['active' => 'advertiser']);
$ctr = $traffic['impressions'] > 0 ? round($traffic['clicks'] / $traffic['impressions'] * 100, 2) : 0;
partial('page_header', ['heading' => 'Hallo ' . current_user()['name'], 'sub' => 'Übersicht Ihrer Kampagnen und Buchungen',
  'actions' => '<a class="btn-secondary" href="' . url('campaign.form') . '"><i class="bx bx-plus"></i> Kampagne</a><a class="btn-primary" href="' . url('home') . '"><i class="bx bx-store-alt"></i> Werbeplätze finden</a>']);
?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php partial('kpi', ['label' => 'Investiert', 'value' => money($kpi['spent']), 'icon' => 'bx-wallet', 'hint' => 'bezahlte Buchungen']); ?>
  <?php partial('kpi', ['label' => 'Offene Zahlungen', 'value' => money($kpi['due']), 'icon' => 'bx-time-five', 'hint' => (int) $kpi['accepted'] . ' angenommene Buchung(en)']); ?>
  <?php partial('kpi', ['label' => 'Impressionen', 'value' => number_format((int) $traffic['impressions'], 0, '.', "'"), 'icon' => 'bx-show', 'hint' => (int) $kpi['live'] . ' Buchung(en) laufen gerade']); ?>
  <?php partial('kpi', ['label' => 'Klicks', 'value' => number_format((int) $traffic['clicks'], 0, '.', "'"), 'icon' => 'bx-pointer', 'hint' => 'CTR ' . $ctr . ' %']); ?>
</div>

<?php if ((int) $campaigns['total'] === 0): ?>
  <div class="mt-6"><?php partial('empty', ['icon' => 'bx-image-add', 'text' => 'Starten Sie mit Ihrer ersten Kampagne', 'hint' => 'Laden Sie ein Banner hoch und hinterlegen Sie die Ziel-URL. Nach der Freigabe können Sie Werbeplätze buchen.', 'action' => [url('campaign.form'), 'Kampagne anlegen']]); ?></div>
<?php elseif ((int) $campaigns['pending'] > 0): ?>
  <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><i class="bx bx-time"></i> <?= (int) $campaigns['pending'] ?> Kampagne(n) warten auf Freigabe durch einen Administrator.</div>
<?php endif; ?>

<?php if ($todo): ?>
<h2 class="mt-8 mb-3 flex items-center gap-2 font-semibold text-slate-900"><i class="bx bx-credit-card text-brand-600"></i> Zahlung ausstehend</h2>
<div class="grid gap-3 md:grid-cols-2">
  <?php foreach ($todo as $b): ?>
  <a href="<?= url('booking', ['id' => $b['id']]) ?>" class="card flex items-center justify-between p-4 hover:border-brand-300">
    <div><p class="font-medium text-slate-900"><?= e($b['slot_name']) ?> · <?= e($b['site_name']) ?></p>
      <p class="text-sm text-slate-500"><?= fdate($b['start_date']) ?> – <?= fdate($b['end_date']) ?> · Referenz <?= e($b['reference']) ?></p></div>
    <div class="text-right"><p class="font-semibold"><?= e(money($b['total_amount'], $b['currency'])) ?></p><?= badge($b['payment_status'] === 'reported' ? 'reported' : 'unpaid') ?></div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
  <div class="lg:col-span-2">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-slate-900">Letzte Buchungen</h2><a class="link text-sm" href="<?= url('bookings') ?>">Alle anzeigen</a></div>
    <?php if ($recent): partial('booking_table', ['rows' => $recent, 'stats' => $stats, 'as' => 'advertiser']); else: ?>
      <?php partial('empty', ['icon' => 'bx-calendar', 'text' => 'Noch keine Buchungen', 'hint' => 'Finden Sie passende Werbeplätze im Marktplatz.']); ?>
    <?php endif; ?>
  </div>
  <div><?php partial('chart', ['rows' => $monthly, 'key' => 'gross', 'label' => 'Ausgaben (6 Monate)']); ?></div>
</div>
