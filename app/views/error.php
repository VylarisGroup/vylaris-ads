<div class="mx-auto max-w-lg py-16 text-center">
  <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-slate-100 text-slate-500"><i class="bx bx-error text-3xl"></i></div>
  <p class="mt-4 text-sm font-semibold text-brand-600"><?= (int) $code ?></p>
  <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($title) ?></h1>
  <?php if ($message !== ''): ?><p class="mt-3 text-slate-600"><?= e($message) ?></p><?php endif; ?>
  <a href="<?= url(current_user() ? 'dashboard' : 'home') ?>" class="btn-primary mt-6">Zur Startseite</a>
</div>
