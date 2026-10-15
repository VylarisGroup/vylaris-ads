<?php $s = $site; ?>
<div class="mx-auto max-w-2xl">
<?php partial('page_header', ['heading' => $title, 'sub' => $s ? 'Nach dem Speichern wird die Webseite erneut geprüft. Bestehende Buchungen bleiben gültig.' : 'Beschreiben Sie Ihre Webseite und Zielgruppe.']); ?>
<form method="post" action="<?= url('site.form', $s ? ['id' => $s['id']] : []) ?>" class="card space-y-5 p-6">
  <?= csrf_field() ?>
  <div><label class="label" for="name">Name der Webseite</label><input class="input" id="name" name="name" value="<?= e(old('name', $s['name'] ?? '')) ?>" required maxlength="160"></div>
  <div><label class="label" for="url">URL</label><input class="input" type="url" id="url" name="url" value="<?= e(old('url', $s['url'] ?? '')) ?>" required maxlength="255" placeholder="https://"></div>
  <div class="grid gap-4 sm:grid-cols-2">
    <div><label class="label" for="category">Kategorie</label>
      <select class="input" id="category" name="category" required><option value="">Bitte wählen</option>
      <?php foreach (SITE_CATEGORIES as $c): ?><option <?= old('category', $s['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div><label class="label" for="monthly_visitors">Besucher pro Monat</label><input class="input" type="number" min="0" id="monthly_visitors" name="monthly_visitors" value="<?= e(old('monthly_visitors', (string) ($s['monthly_visitors'] ?? ''))) ?>"></div>
  </div>
  <div><label class="label" for="description">Beschreibung &amp; Zielgruppe</label><textarea class="input" id="description" name="description" rows="5" required minlength="20" maxlength="3000"><?= e(old('description', $s['description'] ?? '')) ?></textarea></div>
  <div class="flex gap-2"><button class="btn-primary"><i class="bx bx-save"></i> Speichern</button><a class="btn-ghost" href="<?= url('sites') ?>">Abbrechen</a></div>
</form>
</div>
