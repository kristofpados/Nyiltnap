<?php
/**
 * @var string      $next
 * @var string      $username
 * @var string|null $error
 * @var list<array{type: string, message: string}> $flashes
 */
use App\Core\View;
?>
<div class="auth-card">
  <?= View::partial('flashes', ['flashes' => $flashes]) ?>

  <p class="eyebrow">Biztonságos belépés</p>
  <h1 class="auth-card__title">Bejelentkezés</h1>
  <p class="auth-card__text">Lépj be az adminisztrációs felületre a felhasználóneveddel és a jelszavaddal.</p>

  <?php if ($error !== null): ?>
    <div class="alert alert--error" role="alert">
      <?= icon('alert') ?>
      <p><?= e($error) ?></p>
    </div>
  <?php endif; ?>

  <form class="auth-form" method="post" action="<?= e(url('/admin/login')) ?>" data-login-form>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <div class="field">
      <label class="field__label" for="username">Felhasználónév</label>
      <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>"
             required maxlength="60" autocomplete="username" autocapitalize="none" spellcheck="false"<?= $username === '' ? ' autofocus' : '' ?>>
    </div>

    <div class="field">
      <label class="field__label" for="password">Jelszó</label>
      <div class="password-field">
        <input class="input" type="password" id="password" name="password" required maxlength="200" autocomplete="current-password"<?= $username !== '' ? ' autofocus' : '' ?>>
        <button class="password-field__toggle" type="button" data-password-toggle aria-controls="password" aria-pressed="false" hidden>Mutasd</button>
      </div>
    </div>

    <button class="btn btn--primary btn--lg btn--block" type="submit">
      Bejelentkezés<?= icon('arrow-right') ?>
    </button>
  </form>

  <a class="auth-card__back" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?>Vissza a regisztrációs oldalra</a>
</div>
