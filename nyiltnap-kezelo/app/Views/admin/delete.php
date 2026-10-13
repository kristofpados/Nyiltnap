<?php
/**
 * No-JavaScript confirmation page for deleting a registration.
 *
 * @var array<string, mixed> $registration
 * @var string $returnTo
 */
$r = $registration;
?>
<header class="page-head">
  <div class="page-head__text">
    <p class="eyebrow">Jelentkezések</p>
    <h1 class="page-head__title">Jelentkezés törlése</h1>
  </div>
</header>

<section class="panel confirm" aria-labelledby="confirm-title">
  <div class="confirm__head">
    <span class="modal__icon"><?= icon('trash') ?></span>
    <h2 class="modal__title" id="confirm-title">Biztosan törlöd <?= e($r['full_name']) ?> jelentkezését?</h2>
  </div>
  <p class="modal__text">A jelentkezés és a hozzá tartozó szakmaválasztások véglegesen törlődnek. A művelet nem vonható vissza.</p>

  <dl class="modal__details">
    <div><dt>Jelenlegi iskola</dt><dd><?= e($r['current_school']) ?></dd></div>
    <div><dt>Szakmák</dt><dd><?= e(implode(', ', array_column($r['programs'], 'name'))) ?></dd></div>
    <div><dt>Kollégium</dt><dd><?= e(residency_label($r['residency_preference'])) ?></dd></div>
    <div><dt>Végzős</dt><dd><?= $r['is_graduating'] ? 'Igen' : 'Nem' ?></dd></div>
    <div><dt>Azonosító</dt><dd class="mono"><?= e(format_reference($r['reference_code'])) ?></dd></div>
    <div><dt>Beküldve</dt><dd><?= e(hu_datetime($r['created_at'])) ?></dd></div>
  </dl>

  <form class="modal__actions" method="post" action="<?= e(url('/admin/registrations/' . $r['id'] . '/delete')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="reference" value="<?= e($r['reference_code']) ?>">
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">
    <a class="btn btn--ghost" href="<?= e(url($returnTo)) ?>">Mégsem</a>
    <button class="btn btn--danger" type="submit"><?= icon('trash') ?>Végleges törlés</button>
  </form>
</section>
