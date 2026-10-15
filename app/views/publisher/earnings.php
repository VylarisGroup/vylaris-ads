<?php
$typeLabels = ['publisher_earning' => 'Erlös', 'publisher_payout' => 'Auszahlung', 'earning_reversal' => 'Storno'];
partial('page_header', ['heading' => 'Einnahmen & Abrechnung', 'sub' => 'Erlöse werden nach Ende der Laufzeit auszahlbar. Auszahlungen erfolgen manuell durch die Plattform.']); ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php partial('kpi', ['label' => 'Auszahlbar', 'value' => money($balance['available']), 'icon' => 'bx-wallet', 'hint' => 'Mindestbetrag Teilauszahlung ' . money(setting('min_payout'))]); ?>
  <?php partial('kpi', ['label' => 'Ausstehend', 'value' => money($balance['pending']), 'icon' => 'bx-hourglass', 'hint' => 'laufende/geplante bezahlte Buchungen']); ?>
  <?php partial('kpi', ['label' => 'Verdient (gesamt)', 'value' => money($balance['earned']), 'icon' => 'bx-trending-up', 'hint' => 'abgeschlossene Buchungen']); ?>
  <?php partial('kpi', ['label' => 'Ausgezahlt', 'value' => money($balance['paid_out']), 'icon' => 'bx-transfer', 'hint' => count($payouts) . ' Auszahlung(en)']); ?>
</div>
<div class="mt-8 grid gap-6 lg:grid-cols-3">
  <div class="lg:col-span-2">
    <h2 class="mb-3 font-semibold text-slate-900">Kontoauszug</h2>
    <?php if (!$ledger['rows']): ?><p class="card p-5 text-sm text-slate-500">Noch keine Buchungen im Journal.</p><?php else: ?>
    <div class="card overflow-x-auto"><table class="table">
      <thead><tr><th>Datum</th><th>Art</th><th>Beschreibung</th><th class="text-right">Betrag</th></tr></thead>
      <tbody><?php foreach ($ledger['rows'] as $l): ?>
        <tr><td class="whitespace-nowrap"><?= fdate($l['created_at'], true) ?></td><td><?= e($typeLabels[$l['type']] ?? $l['type']) ?></td>
          <td><?php if ($l['booking_id']): ?><a class="link" href="<?= url('booking', ['id' => $l['booking_id']]) ?>"><?= e($l['description']) ?></a><?php else: ?><?= e($l['description']) ?><?php endif; ?></td>
          <td class="text-right tabular-nums <?= $l['amount'] < 0 ? 'text-rose-600' : 'text-emerald-700' ?>"><?= e(money($l['amount'], $l['currency'])) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php partial('pagination', ['result' => $ledger]); endif; ?>
  </div>
  <div class="space-y-6">
    <?php partial('chart', ['rows' => $monthly, 'key' => 'publisher', 'label' => 'Erlöse nach Zahlungseingang']); ?>
    <div class="card p-5">
      <h3 class="font-medium text-slate-900">Auszahlungen</h3>
      <?php if (!$payouts): ?><p class="mt-2 text-sm text-slate-500">Noch keine Auszahlungen.</p><?php else: ?>
      <ul class="mt-3 divide-y divide-slate-100 text-sm">
        <?php foreach ($payouts as $p): ?><li class="flex justify-between py-2"><span><?= fdate($p['paid_on']) ?><span class="block text-xs text-slate-500"><?= e($p['note']) ?></span></span><span class="tabular-nums font-medium"><?= e(money($p['amount'], $p['currency'])) ?></span></li><?php endforeach; ?>
      </ul><?php endif; ?>
      <p class="mt-3 text-xs text-slate-500"><?php if (trim((string) current_user()['payout_details']) === ''): ?><i class="bx bx-error text-amber-600"></i> Bitte hinterlegen Sie Ihre <a class="link" href="<?= url('account') ?>">Auszahlungsdaten</a>.<?php else: ?>Auszahlung an die in <a class="link" href="<?= url('account') ?>">Mein Konto</a> hinterlegte Verbindung.<?php endif; ?></p>
    </div>
  </div>
</div>
