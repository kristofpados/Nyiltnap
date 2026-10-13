<?php
/**
 * @var string $content
 * @var string $title
 * @var string $masthead  HTML rendered inside the dark top band (below the header)
 */
use App\Core\View;

$privacy = (array) config('brand.privacy_paragraphs', []);
?>
<!doctype html>
<html lang="hu">
<head>
<?= View::partial('head', ['title' => $title ?? '', 'styles' => ['public'], 'scripts' => ['public']]) ?>
</head>
<body class="page-public">
<?= View::partial('icons') ?>
<a class="skip-link" href="#main">Ugrás a tartalomra</a>

<div class="masthead<?= !empty($mastheadClass) ? ' ' . e($mastheadClass) : '' ?>">
  <header class="site-header">
    <div class="container site-header__inner">
      <?= View::partial('brand', ['href' => url('/')]) ?>
      <div class="site-header__meta">
        <span class="header-pill"><span class="header-pill__dot" aria-hidden="true"></span>Regisztráció nyitva</span>
        <?php if (!empty($headerCta)): ?>
          <a class="btn btn--light btn--sm" href="<?= e($headerCta['href']) ?>"><?= e($headerCta['label']) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <?= $masthead ?? '' ?>
</div>

<main id="main" tabindex="-1">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="site-footer__grid">
      <div class="site-footer__brand">
        <?= View::partial('brand', ['href' => null]) ?>
        <p class="site-footer__tagline">A jövőd itt kezdődik. Várunk szeretettel a nyílt napon!</p>
      </div>
      <section class="site-footer__privacy" id="adatkezeles" aria-labelledby="adatkezeles-cim" tabindex="-1">
        <h2 class="eyebrow eyebrow--light" id="adatkezeles-cim">Adatkezelési tájékoztató</h2>
        <?php foreach ($privacy as $paragraph): ?>
          <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
        <?php if ((string) config('brand.privacy_template_note', '') !== ''): ?>
          <p class="site-footer__template-note"><?= e(config('brand.privacy_template_note')) ?></p>
        <?php endif; ?>
      </section>
    </div>
    <div class="site-footer__bottom">
      <span>© <?= e(config('brand.event_year')) ?> <?= e(config('brand.school_name')) ?></span>
      <span><?= e(config('brand.event_name')) ?> · Regisztrációs felület</span>
    </div>
  </div>
</footer>
</body>
</html>
