<?php
/**
 * Progressive enhancement: admin.js opens this dialog instead of following
 * the "Törlés" link. Without JavaScript the link leads to the confirmation
 * page, so deletion always needs an explicit POST with a CSRF token.
 *
 * @var string $returnTo
 */
?>
<dialog class="modal" id="delete-dialog" aria-labelledby="delete-dialog-title" aria-describedby="delete-dialog-text">
  <form class="modal__form" method="post" action="" data-delete-form>
    <?= csrf_field() ?>
    <input type="hidden" name="reference" value="" data-delete-reference>
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">

    <div class="modal__head">
      <span class="modal__icon"><?= icon('trash') ?></span>
      <h2 class="modal__title" id="delete-dialog-title">Jelentkezés törlése</h2>
    </div>
    <p class="modal__text" id="delete-dialog-text">
      Biztosan törlöd <strong data-delete-name></strong> jelentkezését? A művelet nem vonható vissza.
    </p>
    <dl class="modal__details">
      <div><dt>Jelenlegi iskola</dt><dd data-delete-school></dd></div>
      <div><dt>Azonosító</dt><dd class="mono" data-delete-reference-label></dd></div>
      <div><dt>Beküldve</dt><dd data-delete-date></dd></div>
    </dl>
    <div class="modal__actions">
      <button class="btn btn--ghost" type="button" data-dialog-close>Mégsem</button>
      <button class="btn btn--danger" type="submit"><?= icon('trash') ?>Végleges törlés</button>
    </div>
  </form>
</dialog>
