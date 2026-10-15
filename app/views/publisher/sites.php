<?php partial('page_header', ['heading' => 'Meine Webseiten', 'sub' => 'Webseiten und Werbeplätze werden vor der Veröffentlichung von einem Administrator geprüft. Änderungen lösen eine erneute Prüfung aus.',
  'actions' => '<a class="btn-primary" href="' . url('site.form') . '"><i class="bx bx-plus"></i> Webseite hinzufügen</a>']); ?>
<?php if (!$sites): partial('empty', ['icon' => 'bx-globe', 'text' => 'Noch keine Webseiten', 'hint' => 'Fügen Sie Ihre erste Webseite hinzu und legen Sie Werbeplätze an.', 'action' => [url('site.form'), 'Webseite hinzufügen']]); endif; ?>
<div class="space-y-6">
<?php foreach ($sites as $site): $slots = $slotsBySite[$site['id']] ?? []; ?>
  <section class="card">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-5">
      <div>
        <div class="flex items-center gap-2"><h2 class="text-lg font-semibold text-slate-900"><?= e($site['name']) ?></h2><?= badge($site['status']) ?></div>
        <a class="link text-sm" href="<?= e($site['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($site['url']) ?></a>
        <p class="text-sm text-slate-500"><?= e($site['category']) ?> · <?= number_format((int) $site['monthly_visitors'], 0, '.', "'") ?> Besucher/Monat</p>
        <?php if ($site['status'] === 'rejected' && $site['admin_note']): ?><p class="mt-2 rounded bg-rose-50 p-2 text-sm text-rose-700"><i class="bx bx-message-error"></i> <?= e($site['admin_note']) ?></p><?php endif; ?>
      </div>
      <div class="flex flex-wrap gap-2">
        <a class="btn-primary" href="<?= url('slot.form', ['site_id' => $site['id']]) ?>"><i class="bx bx-plus"></i> Werbeplatz</a>
        <a class="btn-secondary" href="<?= url('site.form', ['id' => $site['id']]) ?>"><i class="bx bx-edit"></i> Bearbeiten</a>
        <?php if (in_array($site['status'], ['approved', 'paused'], true)): ?>
          <form method="post" action="<?= url('site.toggle') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $site['id'] ?>"><button class="btn-secondary"><i class="bx <?= $site['status'] === 'approved' ? 'bx-pause' : 'bx-play' ?>"></i> <?= $site['status'] === 'approved' ? 'Pausieren' : 'Fortsetzen' ?></button></form>
        <?php endif; ?>
        <form method="post" action="<?= url('site.delete') ?>" data-confirm="Webseite inkl. aller Werbeplätze löschen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $site['id'] ?>"><button class="btn-danger-ghost" title="Löschen"><i class="bx bx-trash"></i></button></form>
      </div>
    </div>
    <?php if (!$slots): ?>
      <p class="p-5 text-sm text-slate-500">Noch keine Werbeplätze für diese Webseite.</p>
    <?php else: ?>
    <div class="overflow-x-auto"><table class="table">
      <thead><tr><th>Werbeplatz</th><th>Format</th><th class="text-right">Preis/Tag</th><th>Laufzeit</th><th>Verfügbarkeit</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($slots as $s): ?>
        <tr>
          <td><a class="link font-medium" href="<?= url('slot', ['id' => $s['id']]) ?>"><?= e($s['name']) ?></a><div class="text-xs text-slate-500"><?= e($s['placement']) ?></div>
            <?php if ($s['status'] === 'rejected' && $s['admin_note']): ?><div class="text-xs text-rose-600"><?= e($s['admin_note']) ?></div><?php endif; ?></td>
          <td><?= e($s['format']) ?></td>
          <td class="text-right tabular-nums"><?= e(money($s['price_per_day'])) ?></td>
          <td><?= (int) $s['min_days'] ?>–<?= (int) $s['max_days'] ?> Tage</td>
          <td class="whitespace-nowrap"><?= fdate($s['available_from']) ?> – <?= $s['available_until'] ? fdate($s['available_until']) : '∞' ?></td>
          <td><?= badge($s['status']) ?><div class="text-xs text-slate-500"><?= (int) $s['sold'] ?> verkauft</div></td>
          <td class="whitespace-nowrap text-right">
            <a class="btn-ghost" href="<?= url('slot.form', ['id' => $s['id']]) ?>" title="Bearbeiten"><i class="bx bx-edit"></i></a>
            <?php if (in_array($s['status'], ['approved', 'paused'], true)): ?>
              <form class="inline" method="post" action="<?= url('slot.toggle') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn-ghost" title="<?= $s['status'] === 'approved' ? 'Pausieren' : 'Fortsetzen' ?>"><i class="bx <?= $s['status'] === 'approved' ? 'bx-pause' : 'bx-play' ?>"></i></button></form>
            <?php endif; ?>
            <form class="inline" method="post" action="<?= url('slot.delete') ?>" data-confirm="Werbeplatz löschen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn-danger-ghost" title="Löschen"><i class="bx bx-trash"></i></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
