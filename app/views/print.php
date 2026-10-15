<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="assets/boxicons/css/boxicons.min.css">
<script src="assets/app.js?v=<?= APP_VERSION ?>" defer></script>
</head>
<body class="bg-slate-100 text-slate-800 print:bg-white">
<div class="mx-auto max-w-3xl px-4 py-6 print:p-0">
  <div class="mb-4 flex gap-2 print:hidden">
    <a href="<?= url('bookings') ?>" class="btn-secondary" data-back><i class="bx bx-arrow-back"></i> Zurück</a>
    <button type="button" class="btn-primary" data-print><i class="bx bx-printer"></i> Drucken / als PDF speichern</button>
  </div>
  <div class="rounded-lg bg-white p-10 shadow print:shadow-none print:p-0"><?php require $__template; ?></div>
</div>
</body>
</html>
