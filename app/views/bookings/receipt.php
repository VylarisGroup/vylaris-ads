<?php
$platform = setting('platform_name');
$isCredit = $type === 'credit';
$to = $isCredit ? [$b['pub_company'], $b['pub_name'], $b['pub_email']] : [$b['adv_company'], $b['adv_name'], $b['adv_email']];
?>
<div class="flex items-start justify-between">
  <div class="flex items-center gap-2 text-lg font-semibold"><i class="bx bx-broadcast text-2xl text-brand-600"></i><?= e($platform) ?></div>
  <div class="text-right text-sm text-slate-600">
    <p class="text-xl font-semibold text-slate-900"><?= $isCredit ? 'Gutschrift' : 'Rechnung' ?></p>
    <p>Nr. <?= e($b['invoice_no']) ?><?= $isCredit ? '-G' : '' ?></p>
    <p>Datum: <?= fdate($b['paid_at']) ?></p>
    <p>Buchung: <?= e($b['reference']) ?></p>
  </div>
</div>
<div class="mt-10 text-sm">
  <p class="text-xs uppercase tracking-wide text-slate-500"><?= $isCredit ? 'Webseitenbetreiber' : 'Rechnungsempfänger' ?></p>
  <?php foreach (array_filter($to) as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
</div>
<table class="mt-8 w-full text-sm">
  <thead><tr class="border-b border-slate-300 text-left"><th class="py-2">Leistung</th><th class="py-2 text-right">Menge</th><th class="py-2 text-right">Einzelpreis</th><th class="py-2 text-right">Betrag</th></tr></thead>
  <tbody>
    <tr class="border-b border-slate-100 align-top">
      <td class="py-3">Werbeplatz „<?= e($b['slot_name']) ?>“ auf <?= e($b['site_name']) ?> (<?= e($b['format']) ?>)<br>
        <span class="text-slate-500">Kampagne „<?= e($b['campaign_name']) ?>“, <?= fdate($b['start_date']) ?> – <?= fdate($b['end_date']) ?></span></td>
      <td class="py-3 text-right"><?= (int) $b['days'] ?> Tage</td>
      <td class="py-3 text-right"><?= e(money($b['price_per_day'], $b['currency'])) ?></td>
      <td class="py-3 text-right"><?= e(money($b['total_amount'], $b['currency'])) ?></td>
    </tr>
    <?php if ($isCredit): ?>
    <tr class="border-b border-slate-100"><td class="py-3" colspan="3">abzüglich Plattformprovision <?= e(percent_bp($b['commission_bp'])) ?></td><td class="py-3 text-right">− <?= e(money($b['commission_amount'], $b['currency'])) ?></td></tr>
    <?php endif; ?>
  </tbody>
  <tfoot><tr><td class="pt-4 font-semibold" colspan="3"><?= $isCredit ? 'Gutschriftbetrag' : 'Gesamtbetrag' ?></td><td class="pt-4 text-right text-lg font-semibold"><?= e(money($isCredit ? $b['publisher_amount'] : $b['total_amount'], $b['currency'])) ?></td></tr></tfoot>
</table>
<div class="mt-8 text-sm text-slate-600">
  <?php if ($b['status'] === 'refunded'): ?><p class="font-semibold text-rose-700">Diese Buchung wurde erstattet. Beleg storniert.</p><?php endif; ?>
  <?php if (!$isCredit): foreach ($payments as $p): if ($p['amount'] > 0): ?><p>Bezahlt am <?= fdate($p['received_on']) ?> – vielen Dank.</p><?php endif; endforeach; else: ?>
    <p>Der Betrag wird Ihrem Guthaben nach Ende der Laufzeit (<?= fdate($b['end_date']) ?>) zur Auszahlung gutgeschrieben.</p>
  <?php endif; ?>
  <p class="mt-6 text-xs text-slate-400">Dieser Beleg wurde elektronisch erstellt. Allfällige Mehrwertsteuer ist gemäss den Angaben des Plattformbetreibers zu berücksichtigen.</p>
</div>
