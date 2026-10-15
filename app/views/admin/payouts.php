<?php partial('page_header', ['heading' => 'Auszahlungen', 'sub' => 'Guthaben aus abgeschlossenen Buchungen an Webseitenbetreiber auszahlen (manuell, z. B. per Überweisung) und hier verbuchen.']); ?>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>Webseitenbetreiber</th><th>Auszahlungsdaten</th><th class="text-right">Ausstehend</th><th class="text-right">Verdient</th><th class="text-right">Ausgezahlt</th><th class="text-right">Auszahlbar</th><th class="w-80">Auszahlung erfassen</th></tr></thead>
  <tbody>
  <?php foreach ($publishers as $p): ?>
    <tr class="align-top">
      <td><p class="font-medium"><?= e($p['company'] ?: $p['name']) ?></p><p class="text-xs text-slate-500"><?= e($p['email']) ?></p></td>
      <td class="max-w-56 whitespace-pre-line text-xs text-slate-600"><?= $p['payout_details'] ? e($p['payout_details']) : '<span class="text-amber-600">nicht hinterlegt</span>' ?></td>
      <td class="text-right tabular-nums text-slate-500"><?= e(money($p['pending'])) ?></td>
      <td class="text-right tabular-nums"><?= e(money($p['earned'])) ?></td>
      <td class="text-right tabular-nums"><?= e(money($p['paid_out'])) ?></td>
      <td class="text-right tabular-nums font-semibold <?= $p['available'] > 0 ? 'text-emerald-700' : 'text-slate-400' ?>"><?= e(money($p['available'])) ?></td>
      <td>
        <?php if ($p['available'] > 0): ?>
        <form method="post" action="<?= url('admin.payouts') ?>" class="grid grid-cols-2 gap-2" data-confirm="Auszahlung verbuchen?"><?= csrf_field() ?>
          <input type="hidden" name="publisher_id" value="<?= (int) $p['id'] ?>">
          <input class="input" name="amount" inputmode="decimal" value="<?= money_input($p['available']) ?>" required aria-label="Betrag">
          <input class="input" type="date" name="paid_on" value="<?= today() ?>" max="<?= today() ?>" required aria-label="Datum">
          <select class="input" name="method" aria-label="Art"><option value="bank_transfer">Überweisung</option><option value="cash">Bar</option><option value="other">Andere</option></select>
          <input class="input" name="note" maxlength="500" placeholder="Notiz" aria-label="Notiz">
          <button class="btn-primary col-span-2 justify-center"><i class="bx bx-transfer"></i> Auszahlung verbuchen</button>
        </form>
        <?php else: ?><span class="text-xs text-slate-400">kein Guthaben</span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$publishers): ?><tr><td colspan="7" class="text-center text-slate-500">Noch keine Webseitenbetreiber.</td></tr><?php endif; ?>
  </tbody>
</table></div>

<h2 class="mt-8 mb-3 font-semibold text-slate-900">Verlauf</h2>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>#</th><th>Datum</th><th>Empfänger</th><th>Art</th><th>Notiz</th><th class="text-right">Betrag</th></tr></thead>
  <tbody><?php foreach ($history['rows'] as $h): ?>
    <tr><td><?= (int) $h['id'] ?></td><td><?= fdate($h['paid_on']) ?></td><td><?= e($h['name']) ?><div class="text-xs text-slate-500"><?= e($h['email']) ?></div></td><td><?= e(['bank_transfer' => 'Überweisung', 'cash' => 'Bar', 'other' => 'Andere'][$h['method']] ?? $h['method']) ?></td><td><?= e($h['note']) ?></td><td class="text-right tabular-nums"><?= e(money($h['amount'], $h['currency'])) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$history['rows']): ?><tr><td colspan="6" class="text-center text-slate-500">Noch keine Auszahlungen.</td></tr><?php endif; ?></tbody>
</table></div>
<?php partial('pagination', ['result' => $history]); ?>
