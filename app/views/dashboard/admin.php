<?php partial('page_header', ['heading' => 'Administration', 'sub' => 'Plattform-Übersicht']); ?>
<?php if (!$licence->isValid()): ?>
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><i class="bx bx-error"></i> Lizenzprüfung fehlgeschlagen: <?= e($licence->status()) ?></div>
<?php endif; ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php partial('kpi', ['label' => 'Umsatz (brutto)', 'value' => money($summary['gross']), 'icon' => 'bx-trending-up', 'hint' => 'bezahlte + abgeschlossene Buchungen']); ?>
  <?php partial('kpi', ['label' => 'Plattformprovision', 'value' => money($summary['commission']), 'icon' => 'bx-pie-chart-alt-2', 'hint' => 'aktueller Satz ' . percent_bp(setting('commission_bp'))]); ?>
  <?php partial('kpi', ['label' => 'Offene Forderungen', 'value' => money($summary['receivables']), 'icon' => 'bx-time-five', 'hint' => $summary['awaiting_payment'] . ' Buchung(en), ' . $summary['reported_payments'] . ' gemeldet']); ?>
  <?php partial('kpi', ['label' => 'Auszahlungen fällig', 'value' => money($summary['payable']), 'icon' => 'bx-transfer', 'hint' => 'bereits ausgezahlt: ' . money($summary['paid_out'])]); ?>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-3">
  <?php foreach (['sites' => ['Webseiten', 'bx-globe'], 'slots' => ['Werbeplätze', 'bx-layout'], 'campaigns' => ['Kampagnen', 'bx-image-alt']] as $t => [$label, $icon]): ?>
  <a href="<?= url('admin.reviews', ['type' => $t]) ?>" class="card flex items-center justify-between p-5 hover:border-brand-300">
    <span class="flex items-center gap-3"><i class="bx <?= $icon ?> text-2xl text-slate-400"></i><span><span class="block font-medium text-slate-900"><?= $label ?></span><span class="text-sm text-slate-500">warten auf Freigabe</span></span></span>
    <span class="text-2xl font-semibold <?= $reviews[$t] > 0 ? 'text-amber-600' : 'text-slate-300' ?>"><?= (int) $reviews[$t] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-3">
  <div class="lg:col-span-2">
    <div class="mb-3 flex items-center justify-between"><h2 class="font-semibold text-slate-900">Zahlungseingang erwartet</h2><a class="link text-sm" href="<?= url('admin.bookings', ['status' => 'accepted']) ?>">Alle anzeigen</a></div>
    <?php if ($awaiting): partial('booking_table', ['rows' => $awaiting, 'as' => 'admin']); else: ?><p class="card p-5 text-sm text-slate-500">Keine offenen Zahlungen.</p><?php endif; ?>
  </div>
  <div class="space-y-6">
    <?php partial('chart', ['rows' => $monthly, 'key' => 'commission', 'label' => 'Provision (6 Monate)']); ?>
    <div class="card p-5 text-sm">
      <h3 class="font-medium text-slate-900">Benutzer &amp; System</h3>
      <dl class="mt-3 space-y-1 text-slate-600">
        <div class="flex justify-between"><dt>Werbetreibende</dt><dd><?= (int) $users['advertisers'] ?></dd></div>
        <div class="flex justify-between"><dt>Webseitenbetreiber</dt><dd><?= (int) $users['publishers'] ?></dd></div>
        <div class="flex justify-between"><dt>Gesperrt</dt><dd><?= (int) $users['blocked'] ?></dd></div>
        <div class="flex justify-between"><dt>Laufende Buchungen</dt><dd><?= (int) $summary['live'] ?></dd></div>
      </dl>
      <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500"><i class="bx bx-shield-quarter"></i> <?= e($licence->status()) ?></p>
      <a class="link mt-2 inline-block text-xs" href="<?= url('admin.audit') ?>">Protokoll ansehen</a>
    </div>
  </div>
</div>
