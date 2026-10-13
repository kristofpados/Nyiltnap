<?php
/**
 * @var string $content
 * @var string $title
 * @var string $active  dashboard | registrations | statistics
 * @var array{username: string} $admin
 * @var list<array{type: string, message: string}> $flashes
 */
use App\Core\View;

$nav = [
    'dashboard'     => ['/admin', 'grid', 'Áttekintés'],
    'registrations' => ['/admin/registrations', 'list', 'Jelentkezések'],
    'statistics'    => ['/admin/statistics', 'chart', 'Statisztikák'],
];
?>
<!doctype html>
<html lang="hu">
<head>
<?= View::partial('head', ['title' => $title ?? 'Adminisztráció', 'styles' => ['admin'], 'scripts' => ['admin'], 'noindex' => true]) ?>
</head>
<body class="page-admin">
<?= View::partial('icons') ?>
<a class="skip-link" href="#main">Ugrás a tartalomra</a>

<div class="admin-shell">
  <header class="sidebar">
    <div class="sidebar__brand">
      <?= View::partial('brand', ['href' => url('/admin'), 'sub' => 'Adminisztráció']) ?>
    </div>

    <nav class="sidebar__nav" aria-label="Adminisztráció">
      <ul role="list">
        <?php foreach ($nav as $key => [$path, $iconName, $label]): ?>
          <li>
            <a class="nav-link" href="<?= e(url($path)) ?>"<?= ($active ?? '') === $key ? ' aria-current="page"' : '' ?>>
              <?= icon($iconName) ?><span><?= e($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="sidebar__footer">
      <a class="nav-link nav-link--subtle sidebar__public-link" href="<?= e(url('/')) ?>">
        <?= icon('external') ?><span>Nyilvános űrlap</span>
      </a>
      <div class="sidebar__user">
        <span class="avatar" aria-hidden="true"><?= e(initials($admin['username'])) ?></span>
        <span class="sidebar__user-text">
          <span class="sidebar__user-name"><?= e($admin['username']) ?></span>
          <span class="sidebar__user-role">Adminisztrátor</span>
        </span>
        <form method="post" action="<?= e(url('/admin/logout')) ?>" class="sidebar__logout">
          <?= csrf_field() ?>
          <button class="icon-button" type="submit" title="Kijelentkezés">
            <?= icon('logout') ?><span class="visually-hidden">Kijelentkezés</span>
          </button>
        </form>
      </div>
    </div>
  </header>

  <main id="main" class="admin-main" tabindex="-1">
    <?= View::partial('flashes', ['flashes' => $flashes ?? []]) ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
