<h1 class="text-2xl font-semibold text-slate-900">Lizenz</h1>
<?php if (!$trs): ?>
  <div class="card mt-6 p-6 text-sm text-slate-600">Es ist ein eigener Lizenzanbieter konfiguriert: <?= e(licence()->status()) ?></div>
<?php else:
  $ev = $lic->evaluate();
  $scopes = licence_scopes();
  $fmt = fn ($t) => $t ? date('d.m.Y H:i', (int) $t) : '–';
?>
<p class="mt-1 text-sm text-slate-500">Diese Installation ist lizenzpflichtig. Die Lizenz wird gegen <?= e($lic->endpoint()) ?> geprüft (Produkt <code><?= e($lic->product()) ?></code>).</p>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
  <div class="card p-6">
    <div class="flex items-center gap-2 text-lg font-semibold <?= $ev['valid'] ? 'text-emerald-700' : 'text-rose-700' ?>">
      <i class="bx <?= $ev['valid'] ? 'bx-check-shield' : 'bx-error-circle' ?> text-2xl"></i><?= $ev['valid'] ? 'Lizenz gültig' : 'Keine gültige Lizenz' ?>
    </div>
    <p class="mt-1 text-sm text-slate-600"><?= e(TrsLicence::describe($ev['code'])) ?><?= $ev['mode'] === 'offline' ? ' (bis ' . e($fmt($ev['valid_until'])) . ')' : '' ?></p>
    <?php if (!$ev['valid']): ?><p class="mt-2 text-sm text-slate-600">Im Lesemodus bleiben Marktplatz, Anmeldung, Datenauskunft/-löschung sowie die Ansichten <em>Statistik</em>, <em>Audit-Log</em> und <em>CSV-Export</em> verfügbar; alle verändernden Funktionen sind gesperrt.</p><?php endif; ?>
    <dl class="mt-4 space-y-1 text-sm">
      <div class="flex justify-between"><dt class="text-slate-500">Tarif</dt><dd><?= e($ev['plan'] ?: '–') ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Gültig bis</dt><dd><?= $ev['valid'] || $ev['license'] ? ($ev['expires'] ? e(date('d.m.Y', strtotime((string) $ev['expires']))) : 'unbegrenzt') : '–' ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Geräte</dt><dd><?= isset($ev['license']['devices']) ? (int) $ev['license']['devices'] . ' / ' . (int) ($ev['license']['maxDevices'] ?? 0) : '–' ?></dd></div>
      <div class="flex justify-between"><dt class="text-slate-500">Zuletzt geprüft</dt><dd><?= e($fmt($ev['checked'])) ?></dd></div>
      <div class="flex justify-between gap-4"><dt class="text-slate-500">Geräte-ID</dt><dd class="break-all text-right font-mono text-xs"><?= e($lic->hwid()) ?></dd></div>
    </dl>
    <?php if ($lic->lastError() !== ''): ?><p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900"><?= e($lic->lastError()) ?></p><?php endif; ?>

    <form method="post" action="<?= url('admin.licence') ?>" class="mt-5 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="save">
      <label class="label" for="licence_key">Lizenzschlüssel</label>
      <input class="input font-mono" id="licence_key" name="licence_key" value="<?= e($lic->key()) ?>" maxlength="64" autocomplete="off" required placeholder="VA-XXXX-XXXX-XXXX">
      <button class="btn-primary"><i class="bx bx-key"></i> Speichern &amp; aktivieren</button>
    </form>
    <?php if ($lic->key() !== ''): ?>
    <div class="mt-3 flex flex-wrap gap-2">
      <form method="post" action="<?= url('admin.licence') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="check"><button class="btn-ghost"><i class="bx bx-refresh"></i> Jetzt prüfen</button></form>
      <form method="post" action="<?= url('admin.licence') ?>" data-confirm="Diese Installation beim Lizenzserver abmelden?"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><button class="btn-ghost"><i class="bx bx-log-out"></i> Gerät abmelden</button></form>
      <form method="post" action="<?= url('admin.licence') ?>" data-confirm="Lizenzschlüssel entfernen? Die Anwendung läuft danach im Lesemodus."><?= csrf_field() ?><input type="hidden" name="action" value="remove"><button class="btn-ghost text-rose-700"><i class="bx bx-trash"></i> Schlüssel entfernen</button></form>
    </div>
    <?php endif; ?>
  </div>

  <div class="card p-6">
    <h2 class="font-semibold text-slate-900">Berechtigungs-Scopes</h2>
    <p class="mt-1 text-xs text-slate-500">Die Scopes entsprechen den <code>features</code> des Tarifs auf dem Lizenzserver. <code>*</code> schaltet alles frei, <code>admin.*</code> eine Gruppe.</p>
    <ul class="mt-3 divide-y divide-slate-100 text-sm">
      <?php foreach ($scopes as $name => [$label, $readonly]):
        $on = $ev['valid'] ? $lic->hasFeature($name) : $readonly; ?>
        <li class="flex items-start gap-2 py-2"><i class="bx <?= $on ? 'bx-check-circle text-emerald-600' : 'bx-lock-alt text-slate-400' ?> mt-0.5 text-lg"></i>
          <span class="min-w-0"><code class="text-xs"><?= e($name) ?></code><span class="block text-slate-600"><?= e($label) ?></span></span></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endif; ?>
