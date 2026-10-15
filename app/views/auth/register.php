<?php $role = old('role', $_GET['role'] ?? 'advertiser'); ?>
<div class="mx-auto max-w-xl">
  <div class="card p-8">
    <h1 class="text-2xl font-semibold text-slate-900">Konto erstellen</h1>
    <p class="mt-1 text-sm text-slate-500">Wählen Sie, wie Sie <?= e(setting('platform_name')) ?> nutzen möchten.</p>
    <form method="post" action="<?= url('register') ?>" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <fieldset class="grid min-w-0 gap-3 sm:grid-cols-3">
        <legend class="label">Kontoart</legend>
        <?php foreach (['advertiser' => ['bx-bullseye', 'Werbetreibender', 'Werbeplätze buchen und Projekte bewerben'], 'publisher' => ['bx-globe', 'Webseitenbetreiber', 'Werbeplätze anbieten und Geld verdienen'], 'both' => ['bx-transfer-alt', 'Beides', 'Werben und Werbeplätze anbieten']] as $r => [$icon, $label, $desc]): ?>
          <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
            <input type="radio" name="role" value="<?= $r ?>" class="mt-1 accent-brand-600" <?= $role === $r ? 'checked' : '' ?> required>
            <span><span class="flex items-center gap-1 font-medium text-slate-900"><i class="bx <?= $icon ?>"></i><?= $label ?></span><span class="block text-xs text-slate-500"><?= $desc ?></span></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="name">Name</label><input class="input" id="name" name="name" value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name"></div>
        <div><label class="label" for="company">Firma <span class="text-slate-400">(optional)</span></label><input class="input" id="company" name="company" value="<?= e(old('company')) ?>" maxlength="160" autocomplete="organization"></div>
      </div>
      <div><label class="label" for="email">E-Mail-Adresse</label><input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email"></div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="password">Passwort</label><input class="input" type="password" id="password" name="password" required minlength="10" autocomplete="new-password"><p class="mt-1 text-xs text-slate-500">Mindestens 10 Zeichen</p></div>
        <div><label class="label" for="password2">Passwort wiederholen</label><input class="input" type="password" id="password2" name="password2" required minlength="10" autocomplete="new-password"></div>
      </div>
      <label class="flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" class="mt-1 accent-brand-600" required><span>Ich akzeptiere die <a class="link" href="<?= url('terms') ?>" target="_blank" rel="noopener">Nutzungsbedingungen</a>, habe die <a class="link" href="<?= url('privacy') ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> zur Kenntnis genommen und bestätige, dass meine Angaben korrekt sind.</span></label>
      <button class="btn-primary w-full justify-center"><i class="bx bx-user-plus"></i> Konto erstellen</button>
    </form>
    <p class="mt-6 text-sm text-slate-500">Bereits registriert? <a class="link" href="<?= url('login') ?>">Anmelden</a></p>
  </div>
</div>
