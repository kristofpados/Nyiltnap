<?php
/**
 * @var array<string, mixed> $overview
 * @var list<array<string, mixed>> $byProgram
 * @var int $represented
 * @var int $activePrograms
 * @var list<array<string, mixed>> $recent
 */
use App\Core\View;

$o = $overview;
$hasData = $o['total'] > 0;
$returnTo = '/admin';
?>
<header class="page-head">
  <div class="page-head__text">
    <p class="eyebrow">Áttekintés</p>
    <h1 class="page-head__title">Nyílt napi jelentkezések</h1>
    <p class="page-head__meta">
      <?php if ($hasData): ?>
        <span>Utolsó jelentkezés: <time datetime="<?= e(to_datetime($o['latest_at'])?->format('c')) ?>"><?= e(hu_relative_datetime($o['latest_at'])) ?></time></span>
      <?php else: ?>
        <span>Még nem érkezett jelentkezés</span>
      <?php endif; ?>
      <span aria-hidden="true">·</span>
      <span>Frissítve: <?= e(date('G:i')) ?></span>
    </p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--ghost" href="<?= e(url('/admin/statistics')) ?>"><?= icon('chart') ?>Részletes statisztikák</a>
    <a class="btn btn--ink" href="<?= e(url('/admin/registrations')) ?>"><?= icon('list') ?>Összes jelentkezés</a>
  </div>
</header>

<dl class="kpis">
  <div class="kpi kpi--lead">
    <dt class="kpi__label">Összes jelentkező</dt>
    <dd class="kpi__value"><?= e(hu_number($o['total'])) ?></dd>
    <dd class="kpi__meta"><?= $hasData ? 'Első: ' . e(hu_relative_datetime($o['first_at'])) : 'Az űrlap nyitva áll' ?></dd>
  </div>
  <div class="kpi">
    <dt class="kpi__label"><?= icon('cap') ?>Végzős</dt>
    <dd class="kpi__value"><?= e(hu_number($o['graduating'])) ?></dd>
    <dd class="kpi__meta">az összes <?= e(percent_of($o['graduating'], $o['total'])) ?>-a</dd>
  </div>
  <div class="kpi">
    <dt class="kpi__label"><?= icon('bed') ?>Kollégista</dt>
    <dd class="kpi__value"><?= e(hu_number($o['boarding'])) ?></dd>
    <dd class="kpi__meta">ebből <?= e(hu_number($o['boarding_graduating'])) ?> végzős</dd>
  </div>
  <div class="kpi">
    <dt class="kpi__label"><?= icon('layers') ?>Képviselt szakmák</dt>
    <dd class="kpi__value"><?= e(hu_number($represented)) ?><span class="kpi__of"> / <?= e(hu_number($activePrograms)) ?></span></dd>
    <dd class="kpi__meta">aktív szakmából választottak</dd>
  </div>
</dl>

<?php if (!$hasData): ?>
  <section class="panel" aria-label="Nincs még adat">
    <?= View::partial('admin/empty', [
        'title'       => 'Még nincs jelentkezés',
        'text'        => 'Amint valaki kitölti a nyilvános űrlapot, itt jelennek meg a mutatók, a szakmánkénti bontás és a legfrissebb jelentkezések.',
        'actionHref'  => url('/'),
        'actionLabel' => 'Nyilvános űrlap megnyitása',
    ]) ?>
  </section>
<?php else: ?>
  <div class="dashboard-grid">
    <section class="panel" aria-labelledby="programs-title">
      <header class="panel__head">
        <div>
          <h2 class="panel__title" id="programs-title">Jelentkezések szakmánként</h2>
          <p class="panel__desc">Egy jelentkező több szakmánál is szerepelhet.</p>
        </div>
        <a class="panel__link" href="<?= e(url('/admin/statistics')) ?>#szakmak">Részletek<?= icon('arrow-right') ?></a>
      </header>
      <?= View::partial('admin/program-bars', ['byProgram' => $byProgram]) ?>
    </section>

    <section class="panel" aria-labelledby="residency-title">
      <header class="panel__head">
        <div>
          <h2 class="panel__title" id="residency-title">Kollégiumi igény</h2>
          <p class="panel__desc">Kollégista és bejárós jelentkezők végzős státusz szerint.</p>
        </div>
      </header>
      <?= View::partial('admin/residency-matrix', ['overview' => $o]) ?>
    </section>
  </div>

  <section class="panel panel--flush" aria-labelledby="recent-title">
    <header class="panel__head">
      <div>
        <h2 class="panel__title" id="recent-title">Legutóbbi jelentkezések</h2>
        <p class="panel__desc">A <?= e(count($recent)) ?> legfrissebb jelentkezés, a legújabb elöl.</p>
      </div>
      <a class="panel__link" href="<?= e(url('/admin/registrations')) ?>">Összes jelentkezés<?= icon('arrow-right') ?></a>
    </header>
    <?= View::partial('admin/registration-table', ['rows' => $recent, 'returnTo' => $returnTo, 'labelId' => 'recent-title']) ?>
  </section>

  <?= View::partial('admin/delete-dialog', ['returnTo' => $returnTo]) ?>
<?php endif; ?>
