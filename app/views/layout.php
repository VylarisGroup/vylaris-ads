<?php
/** @var string $__template */
$me = current_user();
$platform = setting('platform_name', 'VylarisAdvertise');
$current = $_GET['p'] ?? 'home';
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') !== '' ? $title . ' · ' . $platform : $platform) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="assets/boxicons/css/boxicons.min.css">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<script src="assets/app.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<div class="min-h-screen lg:flex">
<?php if ($me): ?>
<aside class="hidden shrink-0 flex-col border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 xl:w-72" aria-label="Hauptnavigation">
  <a href="<?= url('home') ?>" class="flex h-16 shrink-0 items-center gap-2 border-b border-slate-200 px-5 font-semibold text-slate-900">
    <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-white"><i class="bx bx-broadcast text-xl"></i></span>
    <span class="truncate"><?= e($platform) ?></span>
  </a>
  <nav class="flex-1 space-y-1 overflow-y-auto p-3" data-nav><?= Nav::render($me, (string) $current) ?></nav>
  <div class="shrink-0 border-t border-slate-200 p-3">
    <a href="<?= url('account') ?>" class="flex items-center gap-3 rounded-md px-3 py-2 hover:bg-slate-100 <?= $current === 'account' ? 'bg-brand-50' : '' ?>">
      <i class="bx bx-user-circle text-2xl text-slate-500"></i>
      <span class="min-w-0 flex-1 leading-tight"><span class="block truncate text-sm font-medium text-slate-800"><?= e($me['name']) ?></span><span class="block text-xs text-slate-500"><?= e(ROLE_LABELS[$me['role']]) ?> · Mein Konto</span></span>
    </a>
    <form method="post" action="<?= url('logout') ?>" class="mt-1"><?= csrf_field() ?>
      <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-600 hover:bg-slate-100"><i class="bx bx-log-out text-lg"></i> Abmelden</button>
    </form>
  </div>
</aside>
<?php endif; ?>
<div class="flex min-h-screen min-w-0 flex-1 flex-col">
<header class="sticky top-0 z-30 border-b border-slate-200 bg-white <?= $me ? 'lg:hidden' : '' ?>">
  <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
    <a href="<?= url('home') ?>" class="flex shrink-0 items-center gap-2 font-semibold text-slate-900">
      <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-white"><i class="bx bx-broadcast text-xl"></i></span>
      <span class="hidden sm:inline"><?= e($platform) ?></span>
    </a>
    <div class="ml-auto flex items-center gap-2">
      <?php if ($me): ?>
        <details class="relative" data-menu>
          <summary class="btn-ghost cursor-pointer list-none"><i class="bx bx-menu text-2xl"></i><span class="sr-only">Menü</span></summary>
          <div class="absolute right-0 mt-2 max-h-[80vh] w-72 max-w-[calc(100vw-2rem)] space-y-1 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg" data-nav>
            <?= Nav::render($me, (string) $current) ?>
            <div class="mt-2 border-t border-slate-200 pt-2">
              <a href="<?= url('account') ?>" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><i class="bx bx-user-circle text-lg"></i><?= e($me['name']) ?> · Mein Konto</a>
              <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><i class="bx bx-log-out text-lg"></i> Abmelden</button></form>
            </div>
          </div>
        </details>
      <?php else: ?>
        <a href="<?= url('home') ?>" class="btn-ghost hidden sm:inline-flex">Marktplatz</a>
        <a href="<?= url('login') ?>" class="btn-ghost">Anmelden</a>
        <a href="<?= url('register') ?>" class="btn-primary">Registrieren</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
  <?php if ($me && $me['role'] === 'admin' && !licence()->isValid() && $current !== 'admin.licence'): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert"><i class="bx bx-error-circle text-lg"></i>
      <span>Keine gültige Lizenz – die Anwendung läuft im Lesemodus (<?= e(TrsLicence::describe(licence() instanceof TrsLicence ? licence()->evaluate()['code'] : '')) ?>). <a class="underline" href="<?= url('admin.licence') ?>">Lizenz verwalten</a>.</span></div>
  <?php endif; ?>
  <?php if ($me && $me['role'] === 'admin' && ($upd = Updater::available()) && $current !== 'admin.update'): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status"><i class="bx bx-up-arrow-circle text-lg"></i>
      <span>Version <?= e($upd['version']) ?> ist verfügbar (installiert: <?= APP_VERSION ?>). <a class="underline" href="<?= url('admin.update') ?>">Details und Update</a>.</span></div>
  <?php endif; ?>
  <?php if ($me && $me['role'] === 'admin' && !Legal::complete() && $current !== 'admin.settings'): ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert"><i class="bx bx-error text-lg"></i>
      <span>Die rechtlichen Angaben (Impressum, Datenschutz) sind unvollständig. <a class="underline" href="<?= url('admin.settings') ?>#rechtliches">Jetzt unter Einstellungen ergänzen</a>.</span></div>
  <?php endif; ?>
  <?php if ($me && $me['role'] === 'admin' && $current !== 'admin.changelog' && ($cl = Changelog::pending((int) $me['id']))): ?>
    <dialog data-changelog class="w-[calc(100%-2rem)] max-w-lg rounded-lg border border-slate-200 bg-white p-0 shadow-xl backdrop:bg-slate-900/50" aria-labelledby="cl-title">
      <form method="post" action="<?= url('admin.changelog') ?>" class="flex max-h-[85vh] flex-col"><?= csrf_field() ?>
        <div class="flex items-center gap-2 border-b border-slate-200 px-5 py-4">
          <i class="bx bx-gift text-2xl text-brand-600"></i>
          <h2 id="cl-title" class="text-lg font-semibold text-slate-900">Neu in Version <?= e(APP_VERSION) ?></h2>
        </div>
        <div class="divide-y divide-slate-100 overflow-y-auto px-5 py-4"><?= Changelog::html($cl, Changelog::seenVersion((int) $me['id'])) ?></div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-5 py-3">
          <a class="text-sm text-slate-600 underline" href="<?= url('admin.changelog') ?>">Gesamtes Änderungsprotokoll</a>
          <button class="btn-primary" data-changelog-close><i class="bx bx-check"></i> Verstanden</button>
        </div>
      </form>
    </dialog>
    <noscript><div class="mb-4 flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status"><i class="bx bx-gift text-lg"></i>
      <span>Die Anwendung wurde auf Version <?= e(APP_VERSION) ?> aktualisiert. <a class="underline" href="<?= url('admin.changelog') ?>">Änderungen ansehen</a>.</span></div></noscript>
  <?php endif; ?>
  <?php foreach (take_flashes() as $f): ?>
    <?php $fc = ['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'error' => 'border-rose-200 bg-rose-50 text-rose-800', 'info' => 'border-sky-200 bg-sky-50 text-sky-800'][$f['type']] ?? 'border-slate-200 bg-white';
          $fi = ['success' => 'bx-check-circle', 'error' => 'bx-error-circle', 'info' => 'bx-info-circle'][$f['type']] ?? 'bx-info-circle'; ?>
    <div class="mb-4 flex items-start gap-2 rounded-lg border px-4 py-3 text-sm <?= $fc ?>" role="alert"><i class="bx <?= $fi ?> text-lg"></i><span><?= e($f['msg']) ?></span></div>
  <?php endforeach; ?>
  <?php require $__template; ?>
</main>

<footer class="border-t border-slate-200 bg-white">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 py-6 text-xs text-slate-500 flex flex-wrap gap-x-6 gap-y-2 justify-between">
    <span>&copy; <?= date('Y') ?> <?= e($platform) ?> · Werbeplätze direkt von Webseitenbetreibern</span>
    <nav class="flex flex-wrap gap-x-4" aria-label="Rechtliches">
      <a class="hover:text-slate-800 hover:underline" href="<?= url('privacy') ?>">Datenschutz</a>
      <a class="hover:text-slate-800 hover:underline" href="<?= url('imprint') ?>">Impressum</a>
      <a class="hover:text-slate-800 hover:underline" href="<?= url('terms') ?>">Nutzungsbedingungen</a>
    </nav>
    <span>Ein Projekt der VylarisGroup · <?php if ($me && $me['role'] === 'admin'): ?><a class="hover:underline" href="<?= url('admin.changelog') ?>" title="Änderungsprotokoll">v<?= APP_VERSION ?></a><?php else: ?>v<?= APP_VERSION ?><?php endif; ?></span>
  </div>
</footer>
</div>
</div>
</body>
</html>
