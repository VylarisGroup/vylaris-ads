<?php partial('page_header', ['heading' => 'Protokoll', 'sub' => 'Nachvollziehbarkeit aller relevanten Aktionen' . (input('entity') ? ' · gefiltert: ' . input('entity') . (input_int('entity_id') ? ' #' . input_int('entity_id') : '') : ''),
  'actions' => input('entity') ? '<a class="btn-secondary" href="' . url('admin.audit') . '">Filter entfernen</a>' : '']); ?>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>Zeitpunkt</th><th>Benutzer</th><th>Aktion</th><th>Objekt</th><th>Details</th><th>IP</th></tr></thead>
  <tbody><?php foreach ($result['rows'] as $a): ?>
    <tr class="align-top"><td class="whitespace-nowrap"><?= fdate($a['created_at'], true) ?></td><td><?= $a['user_name'] ? e($a['user_name']) : '<span class="text-slate-400">System</span>' ?></td>
      <td class="font-mono text-xs"><?= e($a['action']) ?></td>
      <td><?php if ($a['entity'] === 'booking' && $a['entity_id']): ?><a class="link" href="<?= url('booking', ['id' => $a['entity_id']]) ?>">booking #<?= (int) $a['entity_id'] ?></a><?php else: ?><?= e($a['entity']) ?><?= $a['entity_id'] ? ' #' . (int) $a['entity_id'] : '' ?><?php endif; ?></td>
      <td class="max-w-md break-all font-mono text-xs text-slate-500"><?= e((string) $a['details']) ?></td><td class="text-xs text-slate-500"><?= e($a['ip']) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php partial('pagination', ['result' => $result]); ?>
