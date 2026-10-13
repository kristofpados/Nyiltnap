<?php
/**
 * @var list<array{id: int, name: string, is_active: bool}> $programs
 * @var \App\Support\RegistrationFilters $filters
 * @var list<array<string, mixed>> $rows
 * @var int $total
 * @var int $grandTotal
 * @var int $pages
 * @var string $returnTo
 */
use App\Core\View;
use App\Support\RegistrationFilters;

$f = $filters;
$programNames = array_column($programs, 'name', 'id');
$graduatingValue = $f->graduating === null ? '' : ($f->graduating ? 'yes' : 'no');
$listUrl = static fn (array $overrides = []): string => url('/admin/registrations', $f->toQuery($overrides + ['page' => null]));

// Removable chips describing the active filters.
$chips = [];
if ($f->search !== '') {
    $chips[] = ['Keresés: „' . $f->search . '”', $listUrl(['q' => null])];
}
if ($f->programId !== null) {
    $chips[] = ['Szakma: ' . ($programNames[$f->programId] ?? ''), $listUrl(['program' => null])];
}
if ($f->graduating !== null) {
    $chips[] = [$f->graduating ? 'Végzős' : 'Nem végzős', $listUrl(['graduating' => null])];
}
if ($f->residency !== null) {
    $chips[] = [residency_label($f->residency), $listUrl(['residency' => null])];
}

$firstRow = $total === 0 ? 0 : $f->offset() + 1;
$lastRow = min($f->offset() + $f->perPage, $total);
?>
<header class="page-head">
  <div class="page-head__text">
    <p class="eyebrow">Nyilvántartás</p>
    <h1 class="page-head__title">Jelentkezések</h1>
    <p class="page-head__meta"><span>Összesen <?= e(hu_number($grandTotal)) ?> jelentkezés</span></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url('/admin/registrations/export', $f->toQuery(['page' => null]))) ?>">
      <?= icon('download') ?>CSV letöltése<span class="visually-hidden"> (a szűrt lista)</span>
    </a>
  </div>
</header>

<?php if ($f->invalidProgram): ?>
  <div class="alert alert--info" role="status">
    <?= icon('info') ?>
    <p>A hivatkozásban szereplő szakma nem található, ezért ezt a szűrőt figyelmen kívül hagytuk.</p>
  </div>
<?php endif; ?>

<form class="filters panel" method="get" action="<?= e(url('/admin/registrations')) ?>" role="search" aria-label="Jelentkezések szűrése" data-filter-form>
  <div class="filters__grid">
    <div class="field filters__search">
      <label class="field__label" for="f-q">Keresés</label>
      <div class="input-icon">
        <?= icon('search') ?>
        <input class="input" type="search" id="f-q" name="q" value="<?= e($f->search) ?>" maxlength="<?= RegistrationFilters::SEARCH_MAX_LENGTH ?>" placeholder="Név vagy iskola…" autocomplete="off">
      </div>
    </div>

    <div class="field filters__program">
      <label class="field__label" for="f-program">Szakma</label>
      <select class="input select" id="f-program" name="program">
        <option value="">Összes szakma</option>
        <?php foreach ($programs as $program): ?>
          <option value="<?= e($program['id']) ?>"<?= $f->programId === $program['id'] ? ' selected' : '' ?>>
            <?= e($program['name']) ?><?= $program['is_active'] ? '' : ' (inaktív)' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <fieldset class="field segmented-field">
      <legend class="field__label">Végzős</legend>
      <div class="segmented">
        <?php foreach (['' => 'Mind', 'yes' => 'Igen', 'no' => 'Nem'] as $value => $label): ?>
          <label class="segmented__option">
            <input type="radio" name="graduating" value="<?= e($value) ?>"<?= $graduatingValue === $value ? ' checked' : '' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="field segmented-field">
      <legend class="field__label">Kollégium</legend>
      <div class="segmented">
        <?php foreach (['' => 'Mind', 'boarding' => 'Kollégista', 'commuter' => 'Bejárós'] as $value => $label): ?>
          <label class="segmented__option">
            <input type="radio" name="residency" value="<?= e($value) ?>"<?= ($f->residency ?? '') === $value ? ' checked' : '' ?>>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="field filters__sort">
      <label class="field__label" for="f-sort">Rendezés</label>
      <select class="input select" id="f-sort" name="sort">
        <?php foreach (RegistrationFilters::SORTS as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $f->sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field filters__perpage">
      <label class="field__label" for="f-per-page">Oldalanként</label>
      <select class="input select" id="f-per-page" name="per_page">
        <?php foreach ((array) config('admin.per_page_options', [25, 50, 100]) as $option): ?>
          <option value="<?= e($option) ?>"<?= $f->perPage === (int) $option ? ' selected' : '' ?>><?= e($option) ?> sor</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filters__actions">
      <button class="btn btn--ink" type="submit"><?= icon('filter') ?>Szűrés</button>
      <?php if ($f->hasActiveFilters() || $f->sort !== 'newest'): ?>
        <a class="btn btn--ghost" href="<?= e(url('/admin/registrations')) ?>"><?= icon('x') ?>Szűrők törlése</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<section class="panel panel--flush" aria-labelledby="results-title">
  <header class="results-bar">
    <h2 class="results-bar__title" id="results-title">
      <?php if ($f->hasActiveFilters()): ?>
        <strong><?= e(hu_number($total)) ?></strong> találat
      <?php else: ?>
        <strong><?= e(hu_number($total)) ?></strong> jelentkezés
      <?php endif; ?>
      <?php if ($total > 0): ?>
        <span class="results-bar__range"><?= e($firstRow) ?>–<?= e($lastRow) ?>. sor · <?= e($f->page) ?>. oldal / <?= e($pages) ?></span>
      <?php endif; ?>
    </h2>
    <?php if ($chips !== []): ?>
      <ul class="filter-chips" role="list" aria-label="Aktív szűrők">
        <?php foreach ($chips as [$label, $href]): ?>
          <li>
            <a class="filter-chip" href="<?= e($href) ?>">
              <span><?= e($label) ?></span><?= icon('x') ?><span class="visually-hidden"> – szűrő eltávolítása</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </header>

  <?php if ($rows === []): ?>
    <?php if ($f->hasActiveFilters()): ?>
      <?= View::partial('admin/empty', [
          'title'       => 'Nincs a szűrőknek megfelelő jelentkezés',
          'text'        => 'Próbálj más keresőszót, vagy távolíts el néhány szűrőt.',
          'actionHref'  => url('/admin/registrations'),
          'actionLabel' => 'Szűrők törlése',
      ]) ?>
    <?php else: ?>
      <?= View::partial('admin/empty', [
          'title'       => 'Még nincs jelentkezés',
          'text'        => 'Amint valaki kitölti a nyilvános űrlapot, a jelentkezése itt jelenik meg.',
          'actionHref'  => url('/'),
          'actionLabel' => 'Nyilvános űrlap megnyitása',
      ]) ?>
    <?php endif; ?>
  <?php else: ?>
    <?= View::partial('admin/registration-table', ['rows' => $rows, 'returnTo' => $returnTo, 'labelId' => 'results-title']) ?>
    <footer class="panel__foot">
      <?= View::partial('admin/pagination', ['filters' => $f, 'pages' => $pages]) ?>
    </footer>
  <?php endif; ?>
</section>

<?php if ($rows !== []): ?>
  <?= View::partial('admin/delete-dialog', ['returnTo' => $returnTo]) ?>
<?php endif; ?>
