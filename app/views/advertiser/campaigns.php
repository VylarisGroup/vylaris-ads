<?php partial('page_header', ['heading' => 'Meine Kampagnen', 'sub' => 'Werbemittel mit Banner und Ziel-URL. Jede Kampagne wird vor dem ersten Einsatz geprüft.',
  'actions' => '<a class="btn-primary" href="' . url('campaign.form') . '"><i class="bx bx-plus"></i> Neue Kampagne</a>']); ?>
<?php if (!$campaigns): partial('empty', ['icon' => 'bx-image-add', 'text' => 'Noch keine Kampagnen', 'hint' => 'Legen Sie eine Kampagne mit Banner an, um Werbeplätze zu buchen.', 'action' => [url('campaign.form'), 'Kampagne anlegen']]); else: ?>
<div class="grid gap-5 md:grid-cols-2">
  <?php foreach ($campaigns as $c): $locked = $c['bookings_open'] > 0; ?>
  <div class="card flex flex-col p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0"><h2 class="truncate font-semibold text-slate-900"><?= e($c['name']) ?></h2>
        <a class="link block truncate text-sm" href="<?= e($c['target_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($c['target_url']) ?></a></div>
      <?= badge($c['status']) ?>
    </div>
    <div class="mt-4 grid min-h-24 place-items-center overflow-hidden rounded-lg bg-[repeating-conic-gradient(#f1f5f9_0_25%,#fff_0_50%)] bg-[length:16px_16px] p-3">
      <img src="<?= url('banner', ['id' => $c['id']]) ?>" alt="<?= e($c['alt_text']) ?>" class="max-h-48 max-w-full" loading="lazy">
    </div>
    <p class="mt-2 text-xs text-slate-500">Banner <?= (int) $c['banner_width'] ?>×<?= (int) $c['banner_height'] ?> px · <?= round($c['banner_size'] / 1024) ?> KB · <?= (int) $c['bookings_total'] ?> Buchung(en) · <?= e(money($c['spent'])) ?> investiert</p>
    <?php if ($c['status'] === 'rejected' && $c['admin_note']): ?><p class="mt-2 rounded bg-rose-50 p-2 text-sm text-rose-700"><i class="bx bx-message-error"></i> <?= e($c['admin_note']) ?></p><?php endif; ?>
    <div class="mt-auto flex flex-wrap gap-2 pt-4">
      <?php if ($c['status'] === 'approved'): ?><a class="btn-primary" href="<?= url('home', ['format' => $c['banner_width'] . 'x' . $c['banner_height']]) ?>"><i class="bx bx-search"></i> Passende Plätze</a><?php endif; ?>
      <?php if (!$locked): ?><a class="btn-secondary" href="<?= url('campaign.form', ['id' => $c['id']]) ?>"><i class="bx bx-edit"></i> Bearbeiten</a><?php endif; ?>
      <?php if ((int) $c['bookings_total'] === 0): ?>
        <form method="post" action="<?= url('campaign.delete') ?>" data-confirm="Kampagne wirklich löschen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn-danger-ghost"><i class="bx bx-trash"></i> Löschen</button></form>
      <?php endif; ?>
      <?php if ($locked): ?><span class="self-center text-xs text-slate-500"><i class="bx bx-lock-alt"></i> gesperrt wegen offener Buchungen</span><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
