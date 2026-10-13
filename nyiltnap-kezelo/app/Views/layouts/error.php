<?php
/** @var string $content */
use App\Core\View;
?>
<!doctype html>
<html lang="hu">
<head>
<?= View::partial('head', ['title' => $title ?? 'Hiba', 'styles' => ['public'], 'noindex' => true]) ?>
</head>
<body class="page-error">
<?= View::partial('icons') ?>
<div class="masthead masthead--full">
  <header class="site-header">
    <div class="container site-header__inner">
      <?= View::partial('brand', ['href' => url('/')]) ?>
    </div>
  </header>
  <main id="main" class="container error-page">
    <?= $content ?>
  </main>
</div>
</body>
</html>
