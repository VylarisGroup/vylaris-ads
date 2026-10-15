<?php $me = current_user(); ?>
<?php if (!$me): ?>
<section class="mb-8 overflow-hidden rounded-2xl bg-slate-900 px-6 py-10 text-white sm:px-10">
  <div class="max-w-2xl">
    <p class="text-sm font-medium text-brand-300">Werbemarktplatz</p>
    <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Werbeplätze direkt bei Webseitenbetreibern buchen.</h1>
    <p class="mt-3 text-slate-300">Feste Preise, klare Laufzeiten, keine Auktionen. Betreiber verdienen an ihrer Reichweite, Werbetreibende erreichen genau ihr Publikum.</p>
    <div class="mt-6 flex flex-wrap gap-3">
      <a href="<?= url('register', ['role' => 'advertiser']) ?>" class="btn-primary"><i class="bx bx-bullseye"></i> Werbung schalten</a>
      <a href="<?= url('register', ['role' => 'publisher']) ?>" class="btn bg-white/10 text-white hover:bg-white/20"><i class="bx bx-dollar-circle"></i> Werbeplätze anbieten</a>
    </div>
  </div>
</section>
<?php else: partial('page_header', ['heading' => 'Werbeplätze entdecken', 'sub' => 'Alle freigegebenen Werbeplätze mit Festpreis pro Tag.']); endif; ?>

<form method="get" action="index.php" class="card mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
  <input type="hidden" name="p" value="home">
  <div class="lg:col-span-2"><label class="label" for="q">Suche</label><input class="input" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="Webseite, Thema, Werbeplatz …"></div>
  <div><label class="label" for="format">Format</label>
    <select class="input" id="format" name="format"><option value="">Alle Formate</option>
      <?php foreach (AD_FORMATS as $k => $v): ?><option value="<?= $k ?>" <?= input('format') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
  <div><label class="label" for="category">Kategorie</label>
    <select class="input" id="category" name="category"><option value="">Alle Kategorien</option>
      <?php foreach (SITE_CATEGORIES as $c): ?><option <?= input('category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
  <div><label class="label" for="max_price">Max. Preis/Tag</label><input class="input" id="max_price" name="max_price" inputmode="decimal" value="<?= e(input('max_price')) ?>" placeholder="z. B. 20"></div>
  <div><label class="label" for="sort">Sortierung</label>
    <select class="input" id="sort" name="sort">
      <?php foreach (['' => 'Neueste', 'price_asc' => 'Preis aufsteigend', 'price_desc' => 'Preis absteigend', 'visitors' => 'Meiste Besucher'] as $k => $v): ?><option value="<?= $k ?>" <?= input('sort') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
    <button class="btn-primary"><i class="bx bx-search"></i> Filtern</button>
    <a class="btn-ghost" href="<?= url('home') ?>">Zurücksetzen</a>
  </div>
</form>

<?php if (!$result['rows']): partial('empty', ['icon' => 'bx-search-alt', 'text' => 'Keine passenden Werbeplätze gefunden.', 'hint' => 'Passen Sie die Filter an oder schauen Sie später wieder vorbei.']); else: ?>
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
  <?php foreach ($result['rows'] as $s): ?>
  <a href="<?= url('slot', ['id' => $s['id']]) ?>" class="card group flex flex-col p-5 transition hover:border-brand-300 hover:shadow-md">
    <div class="grid h-28 place-items-center rounded-lg bg-slate-50">
      <svg viewBox="0 0 <?= (int) $s['width'] ?> <?= (int) $s['height'] ?>" class="max-h-20 max-w-[80%]" aria-hidden="true"><rect width="<?= (int) $s['width'] ?>" height="<?= (int) $s['height'] ?>" class="fill-brand-100 stroke-brand-400" stroke-width="<?= max(1, (int) ($s['width'] / 120)) ?>" stroke-dasharray="<?= max(3, (int) ($s['width'] / 40)) ?>"/></svg>
    </div>
    <div class="mt-4 flex items-start justify-between gap-2">
      <div>
        <h2 class="font-semibold text-slate-900 group-hover:text-brand-700"><?= e($s['name']) ?></h2>
        <p class="text-sm text-slate-500"><?= e($s['site_name']) ?> · <?= e($s['category']) ?></p>
      </div>
      <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"><?= e($s['format']) ?></span>
    </div>
    <p class="mt-2 line-clamp-2 text-sm text-slate-600"><?= e($s['description']) ?></p>
    <div class="mt-auto flex items-end justify-between pt-4">
      <span class="text-xs text-slate-500"><i class="bx bx-user"></i> <?= number_format((int) $s['monthly_visitors'], 0, '.', "'") ?> Besucher/Monat</span>
      <span class="text-right"><span class="block text-lg font-semibold text-slate-900"><?= e(money($s['price_per_day'])) ?></span><span class="text-xs text-slate-500">pro Tag</span></span>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php partial('pagination', ['result' => $result]); endif; ?>
