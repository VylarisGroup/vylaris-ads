<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-semibold text-slate-900"><?= e($heading) ?></h1>
    <?php if (!empty($sub)): ?><p class="mt-1 text-sm text-slate-500"><?= e($sub) ?></p><?php endif; ?>
  </div>
  <?php if (!empty($actions)): ?><div class="flex flex-wrap gap-2"><?= $actions ?></div><?php endif; ?>
</div>
