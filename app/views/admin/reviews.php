<?php partial('page_header', ['heading' => 'Freigaben', 'sub' => 'Webseiten, Werbeplätze und Kampagnen prüfen. Erst freigegebene Einträge sind im Marktplatz sichtbar bzw. buchbar.']); ?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <div class="flex gap-1 rounded-lg bg-slate-200 p-1 text-sm">
    <?php foreach (REVIEW_TYPES as $t => $label): ?>
      <a href="<?= url('admin.reviews', ['type' => $t, 'status' => $status]) ?>" class="rounded-md px-3 py-1.5 <?= $type === $t ? 'bg-white font-medium shadow-sm' : 'text-slate-600' ?>"><?= $label ?><?php if ($counts[$t] > 0): ?> <span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white"><?= (int) $counts[$t] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </div>
  <div class="flex flex-wrap gap-1 text-sm">
    <?php foreach (['pending' => 'In Prüfung', 'approved' => 'Freigegeben', 'rejected' => 'Abgelehnt', 'paused' => 'Pausiert', 'all' => 'Alle'] as $s => $label): if ($type === 'campaigns' && $s === 'paused') continue; ?>
      <a href="<?= url('admin.reviews', ['type' => $type, 'status' => $s]) ?>" class="rounded-md px-3 py-1.5 <?= $status === $s ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-200' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$result['rows']): partial('empty', ['icon' => 'bx-check-shield', 'text' => 'Nichts zu prüfen.', 'hint' => 'Alle Einträge in dieser Ansicht sind bearbeitet.']); endif; ?>
<div class="space-y-4">
<?php foreach ($result['rows'] as $r): ?>
  <div class="card grid gap-4 p-5 lg:grid-cols-3">
    <div class="lg:col-span-2">
      <div class="flex flex-wrap items-center gap-2">
        <h2 class="font-semibold text-slate-900">#<?= (int) $r['id'] ?> <?= e($r['name']) ?></h2><?= badge($r['status']) ?>
      </div>
      <?php if ($type === 'sites'): ?>
        <a class="link text-sm" href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($r['url']) ?> <i class="bx bx-link-external"></i></a>
        <p class="text-sm text-slate-500"><?= e($r['category']) ?> · <?= number_format((int) $r['monthly_visitors'], 0, '.', "'") ?> Besucher/Monat · <?= e($r['owner_name']) ?> (<?= e($r['owner_email']) ?>)</p>
        <p class="mt-2 whitespace-pre-line text-sm text-slate-700"><?= e($r['description']) ?></p>
      <?php elseif ($type === 'slots'): ?>
        <p class="text-sm text-slate-500">auf <?= e($r['site_name']) ?> <?= badge($r['site_status']) ?> · <?= e($r['owner_name']) ?></p>
        <p class="mt-1 text-sm"><strong><?= e($r['format']) ?></strong> · <?= e(money($r['price_per_day'])) ?>/Tag · <?= (int) $r['min_days'] ?>–<?= (int) $r['max_days'] ?> Tage · ab <?= fdate($r['available_from']) ?><?= $r['available_until'] ? ' bis ' . fdate($r['available_until']) : '' ?></p>
        <?php if ($r['placement']): ?><p class="text-sm text-slate-500">Platzierung: <?= e($r['placement']) ?></p><?php endif; ?>
        <p class="mt-2 whitespace-pre-line text-sm text-slate-700"><?= e($r['description']) ?></p>
        <a class="link mt-1 inline-block text-sm" href="<?= url('slot', ['id' => $r['id']]) ?>">Vorschau</a>
      <?php else: ?>
        <p class="text-sm text-slate-500"><?= e($r['owner_name']) ?> (<?= e($r['owner_email']) ?>) · Banner <?= (int) $r['banner_width'] ?>×<?= (int) $r['banner_height'] ?> · <?= e($r['banner_mime']) ?></p>
        <p class="text-sm">Ziel: <a class="link break-all" href="<?= e($r['target_url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($r['target_url']) ?></a></p>
        <?php if ($r['alt_text']): ?><p class="text-sm text-slate-500">Alt-Text: <?= e($r['alt_text']) ?></p><?php endif; ?>
        <div class="mt-3 inline-block rounded-lg bg-slate-50 p-3"><img src="<?= url('banner', ['id' => $r['id']]) ?>" alt="" class="max-h-60 max-w-full"></div>
      <?php endif; ?>
      <?php if ($r['admin_note']): ?><p class="mt-2 text-xs text-slate-500">Letzte Notiz: <?= e($r['admin_note']) ?></p><?php endif; ?>
      <p class="mt-1 text-xs text-slate-400">Zuletzt geändert <?= fdate($r['updated_at'], true) ?></p>
    </div>
    <div class="space-y-2">
      <?php if ($r['status'] !== 'approved'): ?>
      <form method="post" action="<?= url('admin.review') ?>"><?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="approved">
        <button class="btn-primary w-full justify-center"><i class="bx bx-check"></i> Freigeben</button></form>
      <?php endif; ?>
      <?php if ($r['status'] !== 'rejected'): ?>
      <form method="post" action="<?= url('admin.review') ?>" class="space-y-2"><?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= $type ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="rejected">
        <textarea class="input" name="note" rows="2" maxlength="500" required minlength="3" placeholder="Begründung für den Nutzer"></textarea>
        <button class="btn-secondary w-full justify-center"><i class="bx bx-x"></i> <?= $r['status'] === 'approved' ? 'Freigabe entziehen' : 'Ablehnen' ?></button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php partial('pagination', ['result' => $result]); ?>
