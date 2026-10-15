<?php
/** Einfaches Balkendiagramm als SVG (keine externen Bibliotheken, CSP-konform).
 *  @var array $rows  [['ym' => '2026-05', key => cents], ...]
 *  @var string $key  */
$max = max(1, ...array_map(fn ($r) => (int) $r[$key], $rows));
$months = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mär', '04' => 'Apr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Dez'];
$n = count($rows); $w = 100 / max(1, $n);
?>
<div class="card p-5">
  <div class="flex items-baseline justify-between">
    <h3 class="font-medium text-slate-900"><?= e($label) ?></h3>
    <span class="text-sm text-slate-500">Summe <?= e(money(array_sum(array_column($rows, $key)))) ?></span>
  </div>
  <svg viewBox="0 0 100 40" class="mt-4 h-44 w-full" preserveAspectRatio="none" role="img" aria-label="<?= e($label) ?>">
    <?php foreach ($rows as $i => $r): $h = 36 * ((int) $r[$key]) / $max; ?>
      <rect x="<?= round($i * $w + $w * 0.18, 2) ?>" y="<?= round(38 - $h, 2) ?>" width="<?= round($w * 0.64, 2) ?>" height="<?= round(max($h, 0.4), 2) ?>" rx="0.8" class="fill-brand-500"><title><?= e($months[substr($r['ym'], 5)] . ' ' . substr($r['ym'], 0, 4) . ': ' . money($r[$key])) ?></title></rect>
    <?php endforeach; ?>
    <line x1="0" y1="38.2" x2="100" y2="38.2" class="stroke-slate-200" stroke-width="0.3"/>
  </svg>
  <div class="mt-1 flex text-center text-[11px] text-slate-500"><?php foreach ($rows as $r): ?><span class="flex-1"><?= e($months[substr($r['ym'], 5)]) ?></span><?php endforeach; ?></div>
</div>
