<?php
/** @var array $rows @var array $stats @var string $as advertiser|publisher|admin */
$stats ??= [];
$mixed = $as === 'both'; // Konto mit beiden Rollen: Perspektive je Zeile
?>
<div class="card overflow-x-auto">
<table class="table">
  <thead><tr>
    <th>Referenz</th><th>Werbeplatz</th><th><?= $mixed ? 'Kampagne / Werbetreibender' : ($as === 'publisher' ? 'Werbetreibender' : ($as === 'admin' ? 'Parteien' : 'Kampagne')) ?></th>
    <th>Zeitraum</th><th class="text-right"><?= $mixed ? 'Betrag / Ihr Erlös' : ($as === 'publisher' ? 'Ihr Erlös' : 'Betrag') ?></th><th>Status</th><th class="text-right">Impr. / Klicks</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $b): $st = $stats[$b['id']] ?? null; $phase = Booking::phase($b);
    $as_ = $mixed ? ((int) $b['publisher_id'] === uid() ? 'publisher' : 'advertiser') : $as; ?>
    <tr>
      <td><a class="link font-medium" href="<?= url('booking', ['id' => $b['id']]) ?>"><?= e($b['reference']) ?></a><div class="text-xs text-slate-500"><?= fdate($b['created_at']) ?><?= $mixed ? ' · ' . ($as_ === 'publisher' ? 'als Betreiber' : 'als Werbetreibender') : '' ?></div></td>
      <td><div class="font-medium text-slate-800"><?= e($b['slot_name']) ?></div><div class="text-xs text-slate-500"><?= e($b['site_name']) ?> · <?= e($b['format']) ?></div></td>
      <td>
        <?php if ($as_ === 'publisher'): ?><?= e($b['adv_company'] ?: $b['adv_name']) ?><div class="text-xs text-slate-500"><?= e($b['campaign_name']) ?></div>
        <?php elseif ($as_ === 'admin'): ?><div class="text-xs"><i class="bx bx-bullseye"></i> <?= e($b['adv_name']) ?></div><div class="text-xs"><i class="bx bx-globe"></i> <?= e($b['pub_name']) ?></div>
        <?php else: ?><?= e($b['campaign_name']) ?><?php endif; ?>
      </td>
      <td class="whitespace-nowrap"><?= fdate($b['start_date']) ?> – <?= fdate($b['end_date']) ?><div class="text-xs text-slate-500"><?= (int) $b['days'] ?> Tage</div></td>
      <td class="text-right tabular-nums whitespace-nowrap"><?= e(money($as_ === 'publisher' ? $b['publisher_amount'] : $b['total_amount'], $b['currency'])) ?></td>
      <td><?= badge($phase) ?><?php if ($b['status'] === 'accepted' && $b['payment_status'] === 'reported'): ?><div class="mt-1"><?= badge('reported') ?></div><?php endif; ?></td>
      <td class="text-right tabular-nums text-slate-600"><?= $st ? number_format((int) $st['impressions'], 0, '.', "'") . ' / ' . number_format((int) $st['clicks'], 0, '.', "'") : '–' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
