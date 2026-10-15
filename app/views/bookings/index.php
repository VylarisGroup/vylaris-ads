<?php
$me = current_user();
$tabs = ['' => 'Alle', 'requested' => 'Angefragt', 'accepted' => 'Zahlung offen', 'paid' => 'Bezahlt', 'completed' => 'Abgeschlossen', 'declined' => 'Abgelehnt', 'cancelled' => 'Storniert', 'expired' => 'Abgelaufen', 'refunded' => 'Erstattet'];
$asFilter ??= '';
$qs = fn (array $p) => $p + ($asFilter !== '' ? ['as' => $asFilter] : []);
partial('page_header', ['heading' => 'Buchungen', 'sub' => $me['role'] === 'both' ? 'Ihre Buchungen als Werbetreibender und als Betreiber' : ($me['role'] === 'advertiser' ? 'Ihre Buchungsanfragen und gebuchten Werbeplätze' : 'Buchungen für Ihre Werbeplätze')]); ?>
<?php if ($me['role'] === 'both'): ?>
<div class="mb-3 inline-flex rounded-lg bg-slate-200 p-1 text-sm">
  <?php foreach (['' => 'Alle', 'advertiser' => 'Als Werbetreibender', 'publisher' => 'Als Betreiber'] as $k => $label): ?>
    <a href="<?= url('bookings', ($k !== '' ? ['as' => $k] : []) + ($status !== '' ? ['status' => $status] : [])) ?>" class="rounded-md px-3 py-1.5 <?= $asFilter === $k ? 'bg-white font-medium text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="mb-4 flex flex-wrap gap-1 text-sm">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="<?= url('bookings', $qs($k !== '' ? ['status' => $k] : [])) ?>" class="rounded-md px-3 py-1.5 <?= $status === $k || ($k === '' && !isset($tabs[$status])) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$result['rows']): partial('empty', ['icon' => 'bx-calendar', 'text' => 'Keine Buchungen gefunden.', 'hint' => $me['role'] === 'publisher' ? 'Sobald Werbetreibende Ihre Plätze anfragen, erscheinen sie hier.' : 'Buchen Sie Werbeplätze im Marktplatz oder warten Sie auf Anfragen für Ihre Plätze.']);
else: partial('booking_table', ['rows' => $result['rows'], 'stats' => $stats, 'as' => $me['role']]); partial('pagination', ['result' => $result]); endif; ?>
