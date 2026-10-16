<?php
$st = Updater::state();
$latest = $st['latest'] ?? null;
$avail = Updater::available();
$reqs = Updater::requirements();
$reqOk = !in_array(false, $reqs, true);
$backups = Updater::backups();
$fmt = fn ($t) => $t ? date('d.m.Y H:i', (int) $t) : '–';
?>
<h1 class="text-2xl font-semibold text-slate-900">Updates</h1>
<p class="mt-1 text-sm text-slate-500">Aktualisierung aus GitHub-Releases. Ein Release gilt als Update, wenn sein Tag <code>vX.Y.Z</code> neuer als die installierte Version ist. <a class="underline" href="<?= url('admin.changelog') ?>">Änderungsprotokoll</a></p>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <div class="card p-6">
    <div class="flex items-center gap-2 text-lg font-semibold <?= $avail ? 'text-sky-700' : 'text-emerald-700' ?>">
      <i class="bx <?= $avail ? 'bx-up-arrow-circle' : 'bx-check-circle' ?> text-2xl"></i><?= $avail ? 'Update verfügbar: ' . e($avail['version']) : 'Auf dem neuesten Stand' ?>
    </div>
    <dl class="mt-3 space-y-1 text-sm">
      <div class="flex justify-between"><dt class="text-slate-500">Installiert</dt><dd><?= APP_VERSION ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Neuestes Release</dt><dd><?= $latest ? e($latest['version']) : '–' ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Zuletzt geprüft</dt><dd><?= e($fmt($st['checked_at'] ?? null)) ?></dd></div>
      <?php if (!empty($st['installed'])): ?><div class="flex justify-between gap-4"><dt class="text-slate-500">Letzte Änderung</dt><dd class="text-right"><?= e(($st['installed']['rollback'] ?? false) ? 'Rollback' : 'Update') ?> <?= e($st['installed']['from']) ?> → <?= e($st['installed']['to']) ?> (<?= e($fmt($st['installed']['at'])) ?>)</dd></div><?php endif; ?>
    </dl>
    <?php if (!empty($st['error'])): ?><p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900"><?= e($st['error']) ?></p><?php endif; ?>
    <?php if ($avail && !empty($avail['notes'])): ?>
      <details class="mt-3 text-sm"><summary class="cursor-pointer font-medium text-slate-700">Release-Hinweise</summary><pre class="mt-2 max-h-60 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-xs"><?= e($avail['notes']) ?></pre></details>
    <?php endif; ?>

    <ul class="mt-4 space-y-1 text-sm">
      <?php foreach ($reqs as $label => $ok): ?><li class="flex items-center gap-2 <?= $ok ? 'text-emerald-700' : 'text-rose-700' ?>"><i class="bx <?= $ok ? 'bx-check' : 'bx-x' ?>"></i><?= e($label) ?></li><?php endforeach; ?>
    </ul>

    <div class="mt-4 flex flex-wrap gap-2">
      <?php if (Updater::configured()): ?>
      <form method="post" action="<?= url('admin.update') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="check"><button class="btn-ghost"><i class="bx bx-refresh"></i> Jetzt prüfen</button></form>
      <?php endif; ?>
      <?php if ($avail && $reqOk): ?>
      <form method="post" action="<?= url('admin.update') ?>" data-confirm="Version <?= e($avail['version']) ?> jetzt installieren? Die Anwendung ist dabei kurz nicht erreichbar; vorher wird eine Dateisicherung angelegt."><?= csrf_field() ?><input type="hidden" name="action" value="install"><button class="btn-primary"><i class="bx bx-download"></i> Jetzt aktualisieren</button></form>
      <?php endif; ?>
    </div>
    <p class="mt-3 text-xs text-slate-500">Gesichert werden die Programmdateien. <strong>Die Datenbank wird nicht gesichert</strong> – vor einem Update bitte ein Datenbank-Backup erstellen. Konfiguration, Uploads und <code>storage/</code> bleiben unverändert.</p>

    <?php if ($log): ?><pre class="mt-4 whitespace-pre-wrap rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><?= e(implode("\n", $log)) ?></pre><?php endif; ?>
  </div>

  <div class="space-y-6">
    <form method="post" action="<?= url('admin.update') ?>" class="card space-y-3 p-6"><?= csrf_field() ?><input type="hidden" name="action" value="save">
      <h2 class="font-semibold text-slate-900">Update-Quelle</h2>
      <p class="text-sm text-slate-600">Updates werden ausschliesslich aus dem offiziellen Release-Repository bezogen: <code class="break-all"><?= e(Updater::repo()) ?></code>. Die Quelle ist fest vorgegeben und kann hier nicht geändert werden.</p>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto" value="1" class="accent-brand-600" <?= setting('update_auto', '1') === '1' ? 'checked' : '' ?>> Täglich automatisch auf neue Versionen prüfen (Hinweis, keine automatische Installation)</label>
      <button class="btn-primary"><i class="bx bx-save"></i> Speichern</button>
    </form>

    <div class="card p-6">
      <h2 class="font-semibold text-slate-900">Sicherungen</h2>
      <?php if (!$backups): ?><p class="mt-2 text-sm text-slate-500">Noch keine Sicherung vorhanden. Sie entsteht automatisch vor jedem Update (die letzten 3 werden aufbewahrt).</p>
      <?php else: ?>
      <ul class="mt-3 divide-y divide-slate-100 text-sm">
        <?php foreach ($backups as $b): ?>
          <li class="flex items-center justify-between gap-3 py-2"><span class="min-w-0"><span class="block truncate font-mono text-xs"><?= e($b['name']) ?></span><span class="text-xs text-slate-500"><?= e($fmt($b['time'])) ?> · <?= number_format($b['size'] / 1024, 0, ',', "'") ?> KB</span></span>
            <form method="post" action="<?= url('admin.update') ?>" data-confirm="Programmdateien auf diese Sicherung zurücksetzen? Datenbankänderungen werden nicht zurückgenommen."><?= csrf_field() ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="file" value="<?= e($b['file']) ?>"><button class="btn-ghost"><i class="bx bx-undo"></i> Wiederherstellen</button></form></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
