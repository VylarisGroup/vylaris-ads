<div class="card p-5">
  <div class="flex items-center justify-between">
    <p class="text-sm text-slate-500"><?= e($label) ?></p>
    <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="bx <?= e($icon) ?> text-xl"></i></span>
  </div>
  <p class="mt-2 text-2xl font-semibold text-slate-900 tabular-nums"><?= e($value) ?></p>
  <?php if (!empty($hint)): ?><p class="mt-1 text-xs text-slate-500"><?= e($hint) ?></p><?php endif; ?>
</div>
