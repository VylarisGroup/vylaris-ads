<div class="mx-auto max-w-md">
  <div class="card p-8">
    <h1 class="text-2xl font-semibold text-slate-900">E-Mail bestätigen</h1>
    <p class="mt-1 text-sm text-slate-500">Neue Konten sind erst nutzbar, nachdem die E-Mail-Adresse bestätigt wurde. Öffnen Sie dazu den Link in der Bestätigungs-Mail. Der Link ist <?= Verification::TTL_HOURS ?> Stunden gültig.</p>
    <form method="post" action="<?= url('verify.resend') ?>" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <div><label class="label" for="email">E-Mail-Adresse</label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" autofocus></div>
      <button class="btn-primary w-full justify-center"><i class="bx bx-envelope"></i> Neuen Bestätigungslink senden</button>
    </form>
    <p class="mt-6 text-sm text-slate-500">Schon bestätigt? <a class="link" href="<?= url('login') ?>">Anmelden</a></p>
    <?php if (!Mailer::configured()): ?><p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">Der E-Mail-Versand ist auf dieser Installation nicht eingerichtet. Bitte wenden Sie sich an den Administrator, damit er Ihre Adresse bestätigt.</p><?php endif; ?>
  </div>
</div>
