<?php
$labels = ['advertiser_payment' => 'Zahlungseingang', 'platform_commission' => 'Provision', 'publisher_earning' => 'Erlös Betreiber', 'publisher_payout' => 'Auszahlung',
  'refund' => 'Erstattung', 'commission_reversal' => 'Storno Provision', 'earning_reversal' => 'Storno Erlös'];
partial('page_header', ['heading' => 'Finanzen & Journal', 'sub' => 'Unveränderliches Buchungsjournal aller Geldbewegungen.',
  'actions' => '<a class="btn-secondary" href="' . e(url('admin.finance', ['format' => 'csv', 'type' => input('type')])) . '"><i class="bx bx-download"></i> CSV-Export</a>']); ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php partial('kpi', ['label' => 'Umsatz brutto', 'value' => money($summary['gross']), 'icon' => 'bx-trending-up']); ?>
  <?php partial('kpi', ['label' => 'Provision', 'value' => money($summary['commission']), 'icon' => 'bx-pie-chart-alt-2']); ?>
  <?php partial('kpi', ['label' => 'Anteil Betreiber', 'value' => money($summary['publisher_share']), 'icon' => 'bx-group', 'hint' => 'davon ausgezahlt ' . money($summary['paid_out'])]); ?>
  <?php partial('kpi', ['label' => 'Offene Forderungen', 'value' => money($summary['receivables']), 'icon' => 'bx-time-five']); ?>
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <?php partial('chart', ['rows' => $monthly, 'key' => 'gross', 'label' => 'Umsatz (12 Monate)']); ?>
  <?php partial('chart', ['rows' => $monthly, 'key' => 'commission', 'label' => 'Provision (12 Monate)']); ?>
</div>
<div class="mt-8 mb-3 flex flex-wrap items-center justify-between gap-3">
  <h2 class="font-semibold text-slate-900">Journal</h2>
  <form method="get" action="index.php" class="flex gap-2"><input type="hidden" name="p" value="admin.finance">
    <select class="input" name="type"><option value="">Alle Buchungsarten</option><?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= input('type') === $t ? 'selected' : '' ?>><?= $labels[$t] ?></option><?php endforeach; ?></select>
    <button class="btn-secondary">Filtern</button></form>
</div>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>#</th><th>Zeitpunkt</th><th>Art</th><th>Beschreibung</th><th>Konto</th><th class="text-right">Betrag</th></tr></thead>
  <tbody><?php foreach ($ledger['rows'] as $l): ?>
    <tr><td class="text-slate-400"><?= (int) $l['id'] ?></td><td class="whitespace-nowrap"><?= fdate($l['created_at'], true) ?></td><td><?= e($labels[$l['type']] ?? $l['type']) ?></td>
      <td><?php if ($l['booking_id']): ?><a class="link" href="<?= url('booking', ['id' => $l['booking_id']]) ?>"><?= e($l['description']) ?></a><?php else: ?><?= e($l['description']) ?><?php endif; ?></td>
      <td><?= $l['user_name'] ? e($l['user_name']) : '<span class="text-slate-400">Plattform</span>' ?></td>
      <td class="text-right tabular-nums <?= $l['amount'] < 0 ? 'text-rose-600' : '' ?>"><?= e(money($l['amount'], $l['currency'])) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$ledger['rows']): ?><tr><td colspan="6" class="text-center text-slate-500">Noch keine Einträge.</td></tr><?php endif; ?></tbody>
</table></div>
<?php partial('pagination', ['result' => $ledger]); ?>
