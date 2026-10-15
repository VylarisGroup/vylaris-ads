<?php
/** @var string $__template */
$me = current_user();
$platform = setting('platform_name', 'VylarisAdvertise');
$current = $_GET['p'] ?? 'home';
$nav = [['home', 'Marktplatz', 'bx-store-alt']];
if ($me) {
    $nav[] = ['dashboard', 'Dashboard', 'bx-grid-alt'];
    if ($me['role'] !== 'admin') {
        if (can_advertise($me)) {
            $nav[] = ['campaigns', 'Kampagnen', 'bx-image-alt'];
        }
        if (can_publish($me)) {
            $nav[] = ['sites', 'Webseiten', 'bx-globe'];
        }
        $nav[] = ['bookings', 'Buchungen', 'bx-calendar-check'];
        if (can_publish($me)) {
            $nav[] = ['earnings', 'Einnahmen', 'bx-wallet'];
        }
    } else {
        $nav[] = ['admin.reviews', 'Freigaben', 'bx-check-shield'];
        $nav[] = ['admin.bookings', 'Buchungen', 'bx-calendar-check'];
        $nav[] = ['admin.payouts', 'Auszahlungen', 'bx-transfer'];
        $nav[] = ['admin.finance', 'Finanzen', 'bx-line-chart'];
        $nav[] = ['admin.users', 'Benutzer', 'bx-group'];
        $nav[] = ['admin.licence', 'Lizenz', 'bx-key'];
        $nav[] = ['admin.settings', 'Einstellungen', 'bx-cog'];
    }
}
$isActive = fn (string $p) => $current === $p || ($p !== 'home' && str_starts_with((string) $current, rtrim($p, 's')));
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
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex flex-col">
<header class="bg-white border-b border-slate-200 sticky top-0 z-30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 h-16 flex items-center gap-6">
    <a href="<?= url('home') ?>" class="flex items-center gap-2 font-semibold text-slate-900 shrink-0">
      <span class="grid place-items-center h-9 w-9 rounded-lg bg-brand-600 text-white"><i class="bx bx-broadcast text-xl"></i></span>
      <span class="hidden sm:inline xl:hidden 2xl:inline"><?= e($platform) ?></span>
    </a>
    <nav class="hidden xl:flex items-center gap-1 text-sm">
      <?php foreach ($nav as [$p, $label, $icon]): ?>
        <a href="<?= url($p) ?>" class="flex items-center gap-1.5 rounded-md px-2 2xl:px-3 py-2 <?= $isActive($p) ? 'bg-brand-50 text-brand-700 font-medium' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
          <i class="bx <?= $icon ?> text-lg"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="ml-auto flex items-center gap-2">
      <?php if ($me): ?>
        <a href="<?= url('account') ?>" title="<?= e($me['name']) ?>" class="hidden sm:flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">
          <i class="bx bx-user-circle text-xl"></i>
          <span class="hidden 2xl:block text-left leading-tight"><span class="block font-medium text-slate-800"><?= e($me['name']) ?></span><span class="block text-xs text-slate-500"><?= e(ROLE_LABELS[$me['role']]) ?></span></span>
        </a>
        <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?>
          <button class="btn-ghost" title="Abmelden"><i class="bx bx-log-out text-xl"></i><span class="sr-only">Abmelden</span></button>
        </form>
      <?php else: ?>
        <a href="<?= url('login') ?>" class="btn-ghost">Anmelden</a>
        <a href="<?= url('register') ?>" class="btn-primary">Registrieren</a>
      <?php endif; ?>
      <details class="xl:hidden relative">
        <summary class="btn-ghost list-none cursor-pointer"><i class="bx bx-menu text-2xl"></i><span class="sr-only">Menü</span></summary>
        <div class="absolute right-0 mt-2 w-60 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
          <?php foreach ($nav as [$p, $label, $icon]): ?>
            <a href="<?= url($p) ?>" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm <?= $isActive($p) ? 'bg-brand-50 text-brand-700' : 'text-slate-700 hover:bg-slate-100' ?>"><i class="bx <?= $icon ?> text-lg"></i><?= e($label) ?></a>
          <?php endforeach; ?>
          <?php if ($me): ?><a href="<?= url('account') ?>" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><i class="bx bx-user-circle text-lg"></i>Mein Konto</a><?php endif; ?>
        </div>
      </details>
    </div>
  </div>
</header>

<main class="flex-1 mx-auto w-full max-w-7xl px-4 sm:px-6 py-8">
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
    <span>Ein Projekt der VylarisGroup · v<?= APP_VERSION ?></span>
  </div>
</footer>
</body>
</html>
