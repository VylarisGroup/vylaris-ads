<?php $c = $campaign; ?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => $title, 'sub' => 'Nach dem Speichern prüft ein Administrator die Kampagne.']); ?>
<form method="post" action="<?= url('campaign.form', $c ? ['id' => $c['id']] : []) ?>" enctype="multipart/form-data" class="card space-y-5 p-6">
  <?= csrf_field() ?>
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) config('uploads.max_bytes') ?>">
  <div><label class="label" for="name">Kampagnenname</label><input class="input" id="name" name="name" value="<?= e(old('name', $c['name'] ?? '')) ?>" required maxlength="160"></div>
  <div><label class="label" for="target_url">Ziel-URL</label><input class="input" type="url" id="target_url" name="target_url" value="<?= e(old('target_url', $c['target_url'] ?? '')) ?>" required maxlength="500" placeholder="https://"></div>
  <div><label class="label" for="alt_text">Alternativtext <span class="text-slate-400">(Barrierefreiheit)</span></label><input class="input" id="alt_text" name="alt_text" value="<?= e(old('alt_text', $c['alt_text'] ?? '')) ?>" maxlength="200"></div>
  <div>
    <label class="label" for="banner">Banner <?= $c ? '<span class="text-slate-400">(leer lassen, um das bestehende zu behalten)</span>' : '' ?></label>
    <?php if ($c): ?><img src="<?= url('banner', ['id' => $c['id']]) ?>" alt="" class="mb-3 max-h-40 rounded border border-slate-200"><?php endif; ?>
    <input class="input file:mr-3 file:rounded file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-brand-700" type="file" id="banner" name="banner" accept="image/png,image/jpeg,image/gif,image/webp" <?= $c ? '' : 'required' ?>>
    <p class="mt-1 text-xs text-slate-500">PNG, JPG, GIF oder WebP, max. <?= round(config('uploads.max_bytes') / 1024) ?> KB. Die Bildgrösse muss exakt dem Format des gewünschten Werbeplatzes entsprechen, z. B.
      <?= e(implode(', ', array_keys(AD_FORMATS))) ?>.</p>
  </div>
  <div class="flex gap-2"><button class="btn-primary"><i class="bx bx-save"></i> Speichern &amp; zur Prüfung einreichen</button><a class="btn-ghost" href="<?= url('campaigns') ?>">Abbrechen</a></div>
</form>
</div>
