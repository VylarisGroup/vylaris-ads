<?php partial('page_header', ['heading' => 'Benutzer', 'sub' => 'Konten verwalten, sperren und Passwörter zurücksetzen.']); ?>
<form method="get" action="index.php" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
  <input type="hidden" name="p" value="admin.users">
  <div class="min-w-48 flex-1"><label class="label" for="q">Suche</label><input class="input" id="q" name="q" value="<?= e(input('q')) ?>" placeholder="Name, E-Mail, Firma"></div>
  <div><label class="label" for="role">Rolle</label><select class="input" id="role" name="role"><option value="">Alle</option><?php foreach (ROLE_LABELS as $k => $v): ?><option value="<?= $k ?>" <?= input('role') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <button class="btn-primary"><i class="bx bx-search"></i> Suchen</button>
</form>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>Benutzer</th><th>Rolle</th><th>Status</th><th>Aktivität</th><th>Letzter Login</th><th>Aktionen</th></tr></thead>
  <tbody>
  <?php foreach ($result['rows'] as $u): $self = (int) $u['id'] === uid(); ?>
    <tr class="align-top">
      <td><p class="font-medium text-slate-900"><?= e($u['name']) ?> <?= $self ? '<span class="text-xs text-slate-400">(Sie)</span>' : '' ?></p><p class="text-xs text-slate-500"><?= e($u['email']) ?><?= $u['company'] ? ' · ' . e($u['company']) : '' ?></p><?php if (!$u['email_verified_at'] && $u['status'] !== 'deleted'): ?><p class="mt-1"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">E-Mail unbestätigt</span></p><?php endif; ?><p class="text-xs text-slate-400">seit <?= fdate($u['created_at']) ?><?php if ($u['status'] === 'deleted'): ?> · gelöscht <?= fdate($u['deleted_at']) ?><?= $u['anonymize_after'] ? ', Anonymisierung ab ' . fdate($u['anonymize_after']) : '' ?><?php endif; ?></p></td>
      <td><?= e(ROLE_LABELS[$u['role']]) ?></td>
      <td><?= badge($u['status']) ?></td>
      <td class="text-xs text-slate-600"><?= (int) $u['sites'] ?> Webseiten<br><?= (int) $u['campaigns'] ?> Kampagnen<br><?= (int) $u['bookings'] ?> Buchungen</td>
      <td class="text-xs"><?= fdate($u['last_login_at'], true) ?></td>
      <td>
        <?php if (!$self && $u['status'] === 'deleted'): ?>
        <form method="post" action="<?= url('admin.user') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="export"><button class="btn-ghost" title="Auskunft nach Art. 15 DSGVO"><i class="bx bx-download"></i> Datenexport</button></form>
        <?php elseif (!$self): ?>
        <details class="text-sm">
          <summary class="btn-ghost cursor-pointer list-none"><i class="bx bx-dots-horizontal-rounded"></i> Verwalten</summary>
          <div class="mt-2 w-64 space-y-3 rounded-lg border border-slate-200 bg-white p-3">
            <form method="post" action="<?= url('admin.user') ?>" data-confirm="Status wirklich ändern?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="action" value="<?= $u['status'] === 'active' ? 'block' : 'unblock' ?>">
              <button class="<?= $u['status'] === 'active' ? 'btn-danger-ghost' : 'btn-secondary' ?> w-full justify-center"><i class="bx <?= $u['status'] === 'active' ? 'bx-block' : 'bx-lock-open' ?>"></i> <?= $u['status'] === 'active' ? 'Sperren' : 'Entsperren' ?></button></form>
            <?php if (!$u['email_verified_at']): ?>
            <div class="space-y-2 rounded-lg bg-amber-50 p-2">
              <p class="text-xs text-amber-900">E-Mail-Adresse noch nicht bestätigt.</p>
              <form method="post" action="<?= url('admin.user') ?>" data-confirm="Die Adresse <?= e($u['email']) ?> ohne Link als bestätigt markieren?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="verify">
                <button class="btn-secondary w-full justify-center"><i class="bx bx-check-circle"></i> Manuell bestätigen</button></form>
              <?php if (Mailer::configured()): ?>
              <form method="post" action="<?= url('admin.user') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="resend">
                <button class="btn-secondary w-full justify-center"><i class="bx bx-envelope"></i> Link erneut senden</button></form>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ((int) $u['sites'] + (int) $u['campaigns'] + (int) $u['bookings'] === 0 || in_array($u['role'], ['advertiser', 'publisher'], true)): ?>
            <form method="post" action="<?= url('admin.user') ?>" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="role">
              <select class="input" name="role"><?php foreach (ROLE_LABELS as $k => $v): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
              <button class="btn-secondary" title="Rolle ändern"><i class="bx bx-check"></i></button></form>
            <?php endif; ?>
            <form method="post" action="<?= url('admin.user') ?>" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="password">
              <input class="input" type="password" name="password" minlength="10" required placeholder="Neues Passwort" autocomplete="new-password">
              <button class="btn-secondary" title="Passwort setzen"><i class="bx bx-key"></i></button></form>
            <div class="space-y-2 border-t border-slate-100 pt-3">
              <p class="text-xs font-medium text-slate-500">Datenschutz-Anfragen</p>
              <form method="post" action="<?= url('admin.user') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="export">
                <button class="btn-secondary w-full justify-center" title="Auskunft nach Art. 15 DSGVO"><i class="bx bx-download"></i> Daten exportieren</button></form>
              <?php if ($u['role'] !== 'admin'): ?>
              <form method="post" action="<?= url('admin.user') ?>" data-confirm="Konto wirklich löschen bzw. bereinigen? Dies kann nicht rückgängig gemacht werden."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="delete">
                <button class="btn-danger-ghost w-full justify-center"><i class="bx bx-trash"></i> Löschen / anonymisieren</button></form>
              <?php endif; ?>
            </div>
          </div>
        </details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php partial('pagination', ['result' => $result]); ?>
