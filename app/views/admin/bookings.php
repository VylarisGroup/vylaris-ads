<?php
$tabs = ['' => 'Alle', 'requested' => 'Angefragt', 'accepted' => 'Zahlung offen', 'paid' => 'Bezahlt', 'completed' => 'Abgeschlossen', 'refunded' => 'Erstattet', 'cancelled' => 'Storniert', 'declined' => 'Abgelehnt', 'expired' => 'Abgelaufen'];
partial('page_header', ['heading' => 'Buchungen & Zahlungen', 'sub' => 'Zahlungseingänge werden in der Buchungsdetailansicht erfasst.']); ?>
<form method="get" action="index.php" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
  <input type="hidden" name="p" value="admin.bookings">
  <div class="min-w-48 flex-1"><label class="label" for="q">Suche</label><input class="input" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="Referenz, Belegnr., E-Mail, Webseite"></div>
  <div><label class="label" for="status">Status</label><select class="input" id="status" name="status"><?php foreach ($tabs as $k => $v): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="payment" value="reported" class="accent-brand-600" <?= input('payment') === 'reported' ? 'checked' : '' ?>> nur gemeldete Zahlungen</label>
  <button class="btn-primary"><i class="bx bx-filter-alt"></i> Filtern</button>
</form>
<?php if (!$result['rows']): partial('empty', ['icon' => 'bx-calendar', 'text' => 'Keine Buchungen gefunden.']); else: partial('booking_table', ['rows' => $result['rows'], 'as' => 'admin', 'stats' => Billing::statsFor(array_column($result['rows'], 'id'))]); partial('pagination', ['result' => $result]); endif; ?>
