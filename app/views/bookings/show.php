<?php
$role = $user['role'];
$isAdv = (int) $b['advertiser_id'] === (int) $user['id'];
$isPub = (int) $b['publisher_id'] === (int) $user['id'];
$isAdmin = $role === 'admin';
$phase = Booking::phase($b);
$totI = array_sum(array_column($daily, 'impressions'));
$totC = array_sum(array_column($daily, 'clicks'));
$methods = ['bank_transfer' => 'Banküberweisung', 'cash' => 'Bar', 'other' => 'Andere', 'refund' => 'Erstattung'];
?>
<a href="<?= url($isAdmin ? 'admin.bookings' : 'bookings') ?>" class="link mb-4 inline-flex items-center gap-1 text-sm"><i class="bx bx-arrow-back"></i> Alle Buchungen</a>
<div class="mb-6 flex flex-wrap items-center gap-3">
  <h1 class="text-2xl font-semibold text-slate-900">Buchung <?= e($b['reference']) ?></h1>
  <?= badge($phase) ?>
  <?php if (in_array($b['status'], ['accepted', 'paid', 'completed', 'refunded'], true)): ?><?= badge($b['payment_status']) ?><?php endif; ?>
  <?php if ($b['invoice_no']): ?><span class="text-sm text-slate-500">Beleg <?= e($b['invoice_no']) ?></span><?php endif; ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <div class="space-y-6 lg:col-span-2">
    <div class="card p-6">
      <div class="grid gap-6 sm:grid-cols-2">
        <div>
          <p class="text-xs uppercase tracking-wide text-slate-500">Werbeplatz</p>
          <p class="mt-1 font-medium text-slate-900"><a class="link" href="<?= url('slot', ['id' => $b['slot_id']]) ?>"><?= e($b['slot_name']) ?></a></p>
          <p class="text-sm text-slate-600"><?= e($b['site_name']) ?> · <?= e($b['format']) ?><?= $b['placement'] ? ' · ' . e($b['placement']) : '' ?></p>
          <p class="mt-1 text-sm text-slate-500">Betreiber: <?= e($b['pub_company'] ?: $b['pub_name']) ?><?php if (!$isPub): ?> · <a class="link" href="mailto:<?= e($b['pub_email']) ?>"><?= e($b['pub_email']) ?></a><?php endif; ?></p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-wide text-slate-500">Kampagne</p>
          <p class="mt-1 font-medium text-slate-900"><?= e($b['campaign_name']) ?></p>
          <a class="link block truncate text-sm" href="<?= e($b['target_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($b['target_url']) ?></a>
          <p class="mt-1 text-sm text-slate-500">Werbetreibender: <?= e($b['adv_company'] ?: $b['adv_name']) ?><?php if (!$isAdv): ?> · <a class="link" href="mailto:<?= e($b['adv_email']) ?>"><?= e($b['adv_email']) ?></a><?php endif; ?></p>
        </div>
      </div>
      <div class="mt-5 grid place-items-center rounded-lg bg-slate-50 p-4"><img src="<?= url('banner', ['id' => $b['campaign_id']]) ?>" alt="<?= e($b['alt_text']) ?>" class="max-h-64 max-w-full"></div>
      <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div><dt class="text-slate-500">Start</dt><dd class="font-medium"><?= fdate($b['start_date']) ?></dd></div>
        <div><dt class="text-slate-500">Ende</dt><dd class="font-medium"><?= fdate($b['end_date']) ?></dd></div>
        <div><dt class="text-slate-500">Laufzeit</dt><dd class="font-medium"><?= (int) $b['days'] ?> Tage</dd></div>
        <div><dt class="text-slate-500">Angefragt am</dt><dd class="font-medium"><?= fdate($b['created_at'], true) ?></dd></div>
      </dl>
      <?php if ($b['message'] !== ''): ?><div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm"><p class="text-xs text-slate-500">Nachricht des Werbetreibenden</p><p class="whitespace-pre-line"><?= e($b['message']) ?></p></div><?php endif; ?>
      <?php if ($b['decision_note']): ?><div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm"><p class="text-xs text-slate-500">Notiz zur Entscheidung</p><p class="whitespace-pre-line"><?= e($b['decision_note']) ?></p></div><?php endif; ?>
    </div>

    <div class="card p-6">
      <h2 class="font-semibold text-slate-900">Preis</h2>
      <dl class="mt-3 space-y-2 text-sm">
        <div class="flex justify-between"><dt><?= (int) $b['days'] ?> Tage × <?= e(money($b['price_per_day'], $b['currency'])) ?></dt><dd class="tabular-nums font-medium"><?= e(money($b['total_amount'], $b['currency'])) ?></dd></div>
        <?php if ($isPub || $isAdmin): ?>
        <div class="flex justify-between text-slate-500"><dt>Plattformprovision (<?= e(percent_bp($b['commission_bp'])) ?>)</dt><dd class="tabular-nums">− <?= e(money($b['commission_amount'], $b['currency'])) ?></dd></div>
        <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold"><dt>Erlös Webseitenbetreiber</dt><dd class="tabular-nums"><?= e(money($b['publisher_amount'], $b['currency'])) ?></dd></div>
        <?php endif; ?>
      </dl>
      <p class="mt-3 text-xs text-slate-500">Festpreis, beim Anfragen verbindlich festgeschrieben.</p>
    </div>

    <?php if (in_array($b['status'], ['paid', 'completed', 'refunded'], true)): ?>
    <div class="card p-6">
      <h2 class="font-semibold text-slate-900">Statistik</h2>
      <div class="mt-3 grid grid-cols-3 gap-4 text-center">
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Impressionen</p><p class="text-xl font-semibold tabular-nums"><?= number_format($totI, 0, '.', "'") ?></p></div>
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Klicks</p><p class="text-xl font-semibold tabular-nums"><?= number_format($totC, 0, '.', "'") ?></p></div>
        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">CTR</p><p class="text-xl font-semibold tabular-nums"><?= $totI ? round($totC / $totI * 100, 2) : 0 ?> %</p></div>
      </div>
      <?php if ($daily): ?>
      <div class="mt-4 max-h-72 overflow-y-auto"><table class="table">
        <thead><tr><th>Tag</th><th class="text-right">Impressionen</th><th class="text-right">Klicks</th></tr></thead>
        <tbody><?php foreach (array_reverse($daily) as $d): ?><tr><td><?= fdate($d['day']) ?></td><td class="text-right tabular-nums"><?= (int) $d['impressions'] ?></td><td class="text-right tabular-nums"><?= (int) $d['clicks'] ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><p class="mt-3 text-sm text-slate-500">Noch keine Daten. Statistiken werden erfasst, wenn das Banner über den Einbindungscode ausgeliefert wird.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($payments && ($isAdmin || $isAdv)): ?>
    <div class="card p-6">
      <h2 class="font-semibold text-slate-900">Zahlungen</h2>
      <table class="table mt-2"><thead><tr><th>Datum</th><th>Art</th><th>Referenz / Notiz</th><th class="text-right">Betrag</th></tr></thead><tbody>
      <?php foreach ($payments as $p): ?><tr><td><?= fdate($p['received_on']) ?></td><td><?= e($methods[$p['method']] ?? $p['method']) ?></td><td><?= e(trim(($p['provider_ref'] ?? '') . ' ' . $p['note'])) ?></td><td class="text-right tabular-nums"><?= e(money($p['amount'], $p['currency'])) ?></td></tr><?php endforeach; ?>
      </tbody></table>
    </div>
    <?php endif; ?>
  </div>

  <aside class="space-y-6">
    <?php if ($isPub && $b['status'] === 'requested'): ?>
      <div class="card p-6">
        <h2 class="font-semibold text-slate-900">Anfrage entscheiden</h2>
        <p class="mt-1 text-sm text-slate-500">Ihr Erlös: <strong><?= e(money($b['publisher_amount'], $b['currency'])) ?></strong>. Konkurrierende Anfragen für denselben Zeitraum werden beim Annehmen automatisch abgelehnt.</p>
        <form method="post" action="<?= url('booking.accept') ?>" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <textarea class="input" name="note" rows="2" maxlength="500" placeholder="Notiz (optional)"></textarea>
          <button class="btn-primary w-full justify-center"><i class="bx bx-check"></i> Annehmen</button></form>
        <form method="post" action="<?= url('booking.decline') ?>" class="mt-3 space-y-3" data-confirm="Anfrage wirklich ablehnen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <input class="input" name="note" maxlength="500" placeholder="Grund der Ablehnung (optional)">
          <button class="btn-secondary w-full justify-center"><i class="bx bx-x"></i> Ablehnen</button></form>
      </div>
    <?php endif; ?>

    <?php if ($isAdv && $b['status'] === 'accepted'): ?>
      <div class="card border-brand-200 p-6">
        <h2 class="flex items-center gap-2 font-semibold text-slate-900"><i class="bx bx-credit-card text-brand-600"></i> Zahlung</h2>
        <p class="mt-1 text-sm text-slate-600">Der Betreiber hat angenommen. Bitte überweisen Sie den Betrag mit der Referenz. Alle Zahlungen laufen ausschliesslich über <?= e(setting('platform_name')) ?> – bitte nicht direkt an den Betreiber der Webseite zahlen. Die Kampagne startet nach Zahlungseingang.</p>
        <dl class="mt-4 space-y-2 rounded-lg bg-slate-50 p-4 text-sm">
          <div class="flex justify-between"><dt class="text-slate-500">Betrag</dt><dd class="font-semibold"><?= e(money($b['total_amount'], $b['currency'])) ?></dd></div>
          <div class="flex justify-between"><dt class="text-slate-500">Referenz</dt><dd class="font-mono font-semibold"><?= e($b['reference']) ?></dd></div>
          <div class="border-t border-slate-200 pt-2"><dt class="text-slate-500">Bankverbindung</dt><dd class="mt-1 whitespace-pre-line font-mono text-xs"><?= e(setting('bank_details')) ?></dd></div>
        </dl>
        <p class="mt-2 text-xs text-slate-500">Zahlbar bis spätestens zum Ende der Laufzeit (<?= fdate($b['end_date']) ?>). Je früher die Zahlung eingeht, desto länger läuft Ihre Kampagne.</p>
        <?php if ($b['payment_status'] === 'unpaid'): ?>
          <form method="post" action="<?= url('booking.report_payment') ?>" class="mt-4"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn-primary w-full justify-center"><i class="bx bx-paper-plane"></i> Ich habe überwiesen</button></form>
        <?php else: ?><p class="mt-4 rounded-lg bg-sky-50 p-3 text-sm text-sky-800"><i class="bx bx-time"></i> Zahlung gemeldet – wird vom Administrator geprüft.</p><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($isPub && in_array($b['status'], ['accepted', 'paid', 'completed'], true)): ?>
      <div class="card p-6">
        <h2 class="flex items-center gap-2 font-semibold text-slate-900"><i class="bx bx-wallet text-brand-600"></i> Abrechnung</h2>
        <p class="mt-1 text-sm text-slate-600">Die Zahlung des Werbetreibenden geht zentral bei <?= e(setting('platform_name')) ?> ein. Ihr Erlösanteil von <strong><?= e(money($b['publisher_amount'], $b['currency'])) ?></strong> wird nach Ende der Laufzeit ausgezahlt. Sie müssen keine Zahlung selbst einfordern oder entgegennehmen.</p>
      </div>
    <?php endif; ?>

    <?php if ($isAdmin && $b['status'] === 'accepted'): ?>
      <div class="card p-6">
        <h2 class="font-semibold text-slate-900">Zahlungseingang erfassen</h2>
        <p class="mt-1 text-sm text-slate-500">Betrag fix: <strong><?= e(money($b['total_amount'], $b['currency'])) ?></strong> (Referenz <?= e($b['reference']) ?>)</p>
        <form method="post" action="<?= url('admin.payment') ?>" class="mt-4 space-y-3" data-confirm="Zahlungseingang über <?= e(money($b['total_amount'], $b['currency'])) ?> bestätigen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <div><label class="label" for="method">Zahlungsart</label><select class="input" id="method" name="method"><option value="bank_transfer">Banküberweisung</option><option value="cash">Bar</option><option value="other">Andere</option></select></div>
          <div><label class="label" for="received_on">Eingangsdatum</label><input class="input" type="date" id="received_on" name="received_on" value="<?= today() ?>" max="<?= today() ?>" required></div>
          <div><label class="label" for="provider_ref">Transaktions-Referenz <span class="text-slate-400">(optional)</span></label><input class="input" id="provider_ref" name="provider_ref" maxlength="120"></div>
          <div><label class="label" for="pnote">Notiz <span class="text-slate-400">(optional)</span></label><input class="input" id="pnote" name="note" maxlength="500"></div>
          <button class="btn-primary w-full justify-center"><i class="bx bx-check-double"></i> Zahlung bestätigen</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($isAdmin && $b['status'] === 'paid'): ?>
      <div class="card p-6">
        <h2 class="font-semibold text-slate-900">Erstattung</h2>
        <form method="post" action="<?= url('admin.refund') ?>" class="mt-3 space-y-3" data-confirm="Buchung wirklich erstatten? Die Kampagne wird sofort beendet."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <input class="input" name="note" required maxlength="500" placeholder="Grund der Erstattung">
          <button class="btn-danger w-full justify-center"><i class="bx bx-undo"></i> Erstatten</button>
        </form>
        <p class="mt-2 text-xs text-slate-500">Die Rücküberweisung an den Werbetreibenden erfolgt manuell.</p>
      </div>
    <?php endif; ?>

    <?php if (in_array($b['status'], ['requested', 'accepted'], true) && ($isAdv || $isAdmin || ($isPub && $b['status'] === 'accepted'))): ?>
      <form method="post" action="<?= url('booking.cancel') ?>" class="card space-y-3 p-6" data-confirm="Buchung wirklich stornieren?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
        <h2 class="font-semibold text-slate-900">Stornieren</h2>
        <input class="input" name="note" maxlength="500" placeholder="Grund (optional)">
        <button class="btn-danger-ghost w-full justify-center border border-rose-200"><i class="bx bx-block"></i> Buchung stornieren</button>
      </form>
    <?php endif; ?>

    <?php if (($isPub || $isAdmin) && in_array($b['status'], ['paid', 'completed'], true)):
      $img = abs_url('serve', ['t' => $b['serve_token']]);
      $click = abs_url('click', ['t' => $b['serve_token']]);
      $code = '<a href="' . e($click) . '" target="_blank" rel="sponsored noopener"><img src="' . e($img) . '" width="' . (int) $b['width'] . '" height="' . (int) $b['height'] . '" alt="' . e($b['alt_text'] ?: 'Anzeige') . '" loading="lazy"></a>'; ?>
      <div class="card p-6">
        <h2 class="flex items-center gap-2 font-semibold text-slate-900"><i class="bx bx-code-alt text-brand-600"></i> Einbindungscode</h2>
        <p class="mt-1 text-sm text-slate-600">Fügen Sie diesen Code an der gebuchten Position ein. Das Banner erscheint automatisch vom <?= fdate($b['start_date']) ?> bis <?= fdate($b['end_date']) ?>; ausserhalb wird nichts angezeigt. Impressionen und Klicks werden gezählt.</p>
        <textarea class="input mt-3 font-mono text-xs" rows="6" readonly data-select><?= e($code) ?></textarea>
        <button type="button" class="btn-secondary mt-2" data-copy><i class="bx bx-copy"></i> Kopieren</button>
      </div>
    <?php endif; ?>

    <?php if ($b['invoice_no']): ?>
      <div class="card p-6">
        <h2 class="font-semibold text-slate-900">Belege</h2>
        <div class="mt-3 flex flex-col gap-2">
          <?php if ($isAdv || $isAdmin): ?><a class="btn-secondary" href="<?= url('receipt', ['id' => $b['id'], 'type' => 'invoice']) ?>"><i class="bx bx-receipt"></i> Rechnung <?= e($b['invoice_no']) ?></a><?php endif; ?>
          <?php if ($isPub || $isAdmin): ?><a class="btn-secondary" href="<?= url('receipt', ['id' => $b['id'], 'type' => 'credit']) ?>"><i class="bx bx-file"></i> Gutschrift <?= e($b['invoice_no']) ?></a><?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?><a class="link text-sm" href="<?= url('admin.audit', ['entity' => 'booking', 'entity_id' => $b['id']]) ?>"><i class="bx bx-history"></i> Verlauf im Protokoll</a><?php endif; ?>
  </aside>
</div>
