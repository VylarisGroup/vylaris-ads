<?php /** @var string $active advertiser|publisher */ ?>
<div class="mb-6 inline-flex rounded-lg bg-slate-200 p-1 text-sm" role="navigation" aria-label="Ansicht wählen">
  <a href="<?= url('dashboard', ['view' => 'advertiser']) ?>" class="rounded-md px-3 py-1.5 <?= $active === 'advertiser' ? 'bg-white font-medium text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>"><i class="bx bx-bullseye"></i> Als Werbetreibender</a>
  <a href="<?= url('dashboard', ['view' => 'publisher']) ?>" class="rounded-md px-3 py-1.5 <?= $active === 'publisher' ? 'bg-white font-medium text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>"><i class="bx bx-globe"></i> Als Betreiber</a>
</div>
