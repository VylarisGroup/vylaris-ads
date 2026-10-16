<?php /** @var bool $hasOld */ ?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => $title, 'sub' => 'Banner hochladen und Ziel festlegen – danach prüft ein Administrator die Kampagne.',
  'actions' => '<a class="btn-ghost" href="' . e(url('campaign.form')) . '"><i class="bx bx-list-ul"></i> Klassisches Formular</a>']); ?>
<form method="post" action="<?= url('campaign.form') ?>" enctype="multipart/form-data" class="card p-6" data-wizard data-start="<?= $hasOld ? '1' : '0' ?>"
      data-banner-max="<?= (int) config('uploads.max_bytes') ?>" data-banner-formats="<?= e(json_encode(array_keys(AD_FORMATS))) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) config('uploads.max_bytes') ?>">
  <div class="mb-6" data-wizard-steps></div>

  <fieldset class="min-w-0 space-y-5" data-step="Kampagne">
    <legend class="text-lg font-semibold text-slate-900">Worum geht es?</legend>
    <p class="-mt-3 text-sm text-slate-500">Name und Ziel Ihrer Anzeige.</p>
    <div><label class="label" for="name">Kampagnenname</label><input class="input" id="name" name="name" data-sum="Kampagne" value="<?= e(old('name')) ?>" required minlength="2" maxlength="160" placeholder="z. B. Sommeraktion 2026"></div>
    <div><label class="label" for="target_url">Ziel-URL</label><input class="input" type="url" id="target_url" name="target_url" data-sum="Ziel-URL" value="<?= e(old('target_url')) ?>" required maxlength="500" placeholder="https://"><p class="mt-1 text-xs text-slate-500">Hierhin gelangen Besucher beim Klick auf das Banner.</p></div>
    <div><label class="label" for="alt_text">Alternativtext <span class="text-slate-400">(Barrierefreiheit, optional)</span></label><input class="input" id="alt_text" name="alt_text" data-sum="Alternativtext" value="<?= e(old('alt_text')) ?>" maxlength="200"></div>
  </fieldset>

  <fieldset class="min-w-0 space-y-5" data-step="Banner">
    <legend class="text-lg font-semibold text-slate-900">Ihr Banner</legend>
    <p class="-mt-3 text-sm text-slate-500">PNG, JPG, GIF oder WebP, max. <?= round(config('uploads.max_bytes') / 1024) ?> KB. Die Bildgrösse muss exakt zum Werbeplatz passen.</p>
    <div>
      <label class="label" for="banner">Bilddatei</label>
      <input class="input file:mr-3 file:rounded file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-brand-700" type="file" id="banner" name="banner" data-sum="Banner" data-banner-input accept="image/png,image/jpeg,image/gif,image/webp" required>
    </div>
    <div class="hidden rounded-lg border border-slate-200 p-4" data-banner-preview>
      <img src="data:," alt="Vorschau des Banners" class="max-h-48 max-w-full rounded border border-slate-200">
      <p class="mt-3 text-sm font-medium text-slate-800" data-banner-size></p>
      <p class="mt-1 text-sm" data-banner-fit></p>
    </div>
    <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600"><i class="bx bx-info-circle"></i> Standardformate: <?= e(implode(', ', array_keys(AD_FORMATS))) ?>. Ein Banner passt nur zu Werbeplätzen mit genau derselben Grösse.</p>
  </fieldset>

  <fieldset class="hidden min-w-0 space-y-4" data-step="Prüfen" data-summary>
    <legend class="text-lg font-semibold text-slate-900">Alles korrekt?</legend>
    <p class="-mt-2 text-sm text-slate-500">Mit „Einreichen“ wird die Kampagne gespeichert und zur Freigabe an einen Administrator gesendet. Danach können Sie passende Werbeplätze buchen.</p>
    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200 px-4" data-summary-list></dl>
  </fieldset>

  <?php partial('wizard_foot', ['cancel' => url('campaigns'), 'submit' => 'Einreichen', 'icon' => 'bx-send']); ?>
</form>
</div>
