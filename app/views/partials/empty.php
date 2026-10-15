<div class="card px-6 py-12 text-center">
  <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400"><i class="bx <?= e($icon ?? 'bx-folder-open') ?> text-2xl"></i></div>
  <p class="mt-3 font-medium text-slate-800"><?= e($text) ?></p>
  <?php if (!empty($hint)): ?><p class="mt-1 text-sm text-slate-500"><?= e($hint) ?></p><?php endif; ?>
  <?php if (!empty($action)): ?><a href="<?= e($action[0]) ?>" class="btn-primary mt-5"><i class="bx bx-plus"></i> <?= e($action[1]) ?></a><?php endif; ?>
</div>
