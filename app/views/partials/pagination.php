<?php /** @var array $result */ if ($result['pages'] > 1):
  $qs = $_GET; ?>
<nav class="mt-4 flex items-center justify-between text-sm text-slate-600">
  <span><?= (int) $result['total'] ?> Einträge · Seite <?= (int) $result['page'] ?> von <?= (int) $result['pages'] ?></span>
  <span class="flex gap-2">
    <?php if ($result['page'] > 1): $qs['page'] = $result['page'] - 1; ?><a class="btn-secondary" href="index.php?<?= e(http_build_query($qs)) ?>"><i class="bx bx-chevron-left"></i> Zurück</a><?php endif; ?>
    <?php if ($result['page'] < $result['pages']): $qs['page'] = $result['page'] + 1; ?><a class="btn-secondary" href="index.php?<?= e(http_build_query($qs)) ?>">Weiter <i class="bx bx-chevron-right"></i></a><?php endif; ?>
  </span>
</nav>
<?php endif; ?>
