<div class="mx-auto max-w-md">
  <div class="card p-8">
    <h1 class="text-2xl font-semibold text-slate-900">Anmelden</h1>
    <p class="mt-1 text-sm text-slate-500">Willkommen zurück bei <?= e(setting('platform_name')) ?>.</p>
    <form method="post" action="<?= url('login') ?>" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <div><label class="label" for="email">E-Mail-Adresse</label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" autofocus></div>
      <div><label class="label" for="password">Passwort</label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn-primary w-full justify-center"><i class="bx bx-log-in"></i> Anmelden</button>
    </form>
    <p class="mt-6 text-sm text-slate-500">Noch kein Konto? <a class="link" href="<?= url('register') ?>">Jetzt registrieren</a></p>
    <p class="mt-2 text-xs text-slate-400">Passwort vergessen? Bitte wenden Sie sich an den Administrator der Plattform.</p>
  </div>
</div>
