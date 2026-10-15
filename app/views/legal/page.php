<?php /** @var string $title @var string $page @var string $body */ ?>
<div class="mx-auto max-w-3xl">
  <div class="mb-4 flex flex-wrap gap-2 text-sm">
    <?php foreach (Legal::PAGES as $key => $label): ?>
      <a href="<?= url($key) ?>" class="rounded-md px-3 py-1.5 <?= $key === $page ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-200' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <article class="card p-6 sm:p-8">
    <h1 class="text-2xl font-semibold text-slate-900"><?= e($title) ?></h1>
    <?= $body /* von Legal erzeugt bzw. escaped gerendert */ ?>
  </article>
</div>
