<?php
/** @var string $content */
use App\Core\View;
?>
<!doctype html>
<html lang="hu">
<head>
<?= View::partial('head', ['title' => $title ?? 'Bejelentkezés', 'styles' => ['admin'], 'scripts' => ['admin'], 'noindex' => true]) ?>
</head>
<body class="page-auth">
<?= View::partial('icons') ?>
<div class="auth">
  <section class="auth__brand" aria-label="<?= e(config('brand.school_name')) ?> – adminisztráció">
    <?= View::partial('brand', ['href' => url('/'), 'sub' => 'Adminisztráció']) ?>
    <div class="auth__brand-copy">
      <p class="eyebrow eyebrow--light"><?= e(config('brand.event_name')) ?> <?= e(config('brand.event_year')) ?></p>
      <p class="auth__headline">Jelentkezések, <em>egy helyen.</em></p>
      <p class="auth__lede">Kövesd a nyílt napi regisztrációkat, nézd meg szakmánkénti bontásban, és készüljetek fel a látogatókra.</p>
    </div>
    <p class="auth__fineprint"><?= icon('lock') ?>Védett felület — csak az iskola jogosult munkatársai számára.</p>
  </section>
  <main class="auth__main" id="main" tabindex="-1">
    <?= $content ?>
  </main>
</div>
</body>
</html>
