<?php /** @var string $cancel URL zum Abbrechen; @var string $submit Beschriftung des Absende-Knopfs; @var string $icon */ ?>
<div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
  <div class="flex items-center gap-2">
    <button type="button" class="btn-ghost hidden" data-wizard-prev><i class="bx bx-arrow-back"></i> Zurück</button>
    <a class="btn-ghost" href="<?= e($cancel) ?>" data-wizard-cancel>Abbrechen</a>
  </div>
  <div class="flex items-center gap-2">
    <button type="button" class="btn-primary hidden" data-wizard-next>Weiter <i class="bx bx-arrow-back rotate-180"></i></button>
    <button class="btn-primary" data-wizard-submit><i class="bx <?= e($icon ?? 'bx-check') ?>"></i> <?= e($submit) ?></button>
  </div>
</div>
