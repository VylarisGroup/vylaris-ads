<h1 class="text-2xl font-semibold text-slate-900">Änderungsprotokoll</h1>
<p class="mt-1 text-sm text-slate-500">Installiert: Version <?= e(APP_VERSION) ?>. Nach jedem Update erscheint hier, was sich geändert hat.</p>

<div class="card mt-6 max-w-3xl divide-y divide-slate-100 p-6">
  <?php if (!$entries): ?>
    <p class="text-sm text-slate-500">Es sind keine Einträge vorhanden (Datei <code>CHANGELOG.md</code>).</p>
  <?php else: ?>
    <?= Changelog::html($entries, $seen) ?>
  <?php endif; ?>
</div>
