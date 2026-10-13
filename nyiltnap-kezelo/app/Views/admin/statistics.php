<?php
/**
 * @var array<string, mixed> $overview
 * @var list<array<string, mixed>> $byProgram
 * @var list<array{day: string, total: int, graduating: int}> $daily
 * @var int $selections
 * @var int $represented
 * @var int $activePrograms
 */
use App\Core\View;

$o = $overview;
$hasData = $o['total'] > 0;
$average = $hasData ? $selections / $o['total'] : 0;
$dailyMax = max([1, ...array_column($daily, 'total')]);
?>
<header class="page-head">
  <div class="page-head__text">
    <p class="eyebrow">Elemzés</p>
    <h1 class="page-head__title">Statisztikák</h1>
    <p class="page-head__meta">
      <span><?= e(hu_number($o['total'])) ?> jelentkező alapján</span>
      <span aria-hidden="true">·</span>
      <span>Állapot: <?= e(hu_datetime(date('Y-m-d H:i:s'))) ?></span>
    </p>
  </div>
  <div class="page-head__actions">
    <button class="btn btn--ghost" type="button" data-print hidden><?= icon('printer') ?>Nyomtatás</button>
    <a class="btn btn--ink" href="<?= e(url('/admin/registrations')) ?>"><?= icon('list') ?>Jelentkezések</a>
  </div>
</header>

<?php if (!$hasData): ?>
  <section class="panel" aria-label="Nincs még adat">
    <?= View::partial('admin/empty', [
        'title'       => 'Még nincs statisztika',
        'text'        => 'A statisztikák az első jelentkezés beérkezése után jelennek meg.',
        'actionHref'  => url('/'),
        'actionLabel' => 'Nyilvános űrlap megnyitása',
    ]) ?>
  </section>
<?php else: ?>

  <!-- 1. Totals -->
  <section class="stat-summary" aria-labelledby="summary-title">
    <h2 class="visually-hidden" id="summary-title">Összesítés</h2>
    <div class="panel stat-total">
      <p class="kpi__label">Összes jelentkező</p>
      <p class="stat-total__value"><?= e(hu_number($o['total'])) ?></p>
      <p class="kpi__meta">
        <?= e(hu_datetime($o['first_at'], false)) ?> – <?= e(hu_datetime($o['latest_at'], false)) ?>
      </p>
    </div>

    <?php foreach ([
        ['Végzős státusz', 'cap', 'Végzős', $o['graduating'], 'Nem végzős', $o['non_graduating']],
        ['Kollégiumi igény', 'bed', 'Kollégista', $o['boarding'], 'Bejárós', $o['commuter']],
    ] as [$heading, $iconName, $mainLabel, $mainValue, $restLabel, $restValue]): ?>
      <?php [$mainPercent, $restPercent] = percent_pair($mainValue, $o['total']); ?>
      <div class="panel stat-split">
        <p class="kpi__label"><?= icon($iconName) ?><?= e($heading) ?></p>
        <dl class="stat-split__values">
          <div>
            <dt><span class="legend__swatch legend__swatch--grad" aria-hidden="true"></span><?= e($mainLabel) ?></dt>
            <dd><strong><?= e(hu_number($mainValue)) ?></strong> <span><?= e($mainPercent) ?></span></dd>
          </div>
          <div>
            <dt><span class="legend__swatch legend__swatch--rest" aria-hidden="true"></span><?= e($restLabel) ?></dt>
            <dd><strong><?= e(hu_number($restValue)) ?></strong> <span><?= e($restPercent) ?></span></dd>
          </div>
        </dl>
        <svg class="split__bar" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true" focusable="false">
          <rect class="split__rest" x="0" y="0" width="100" height="10"/>
          <rect class="split__main" x="0" y="0" width="<?= e(share($mainValue, $o['total'])) ?>" height="10"/>
        </svg>
      </div>
    <?php endforeach; ?>
  </section>

  <!-- 2. Boarding × graduating -->
  <section class="panel" aria-labelledby="boarding-title">
    <header class="panel__head">
      <div>
        <h2 class="panel__title" id="boarding-title">Kollégista jelentkezők végzős státusz szerint</h2>
        <p class="panel__desc">Hány jelentkező kérne kollégiumot összesen, és ebből hányan végzősök.</p>
      </div>
    </header>
    <div class="boarding-layout">
      <div class="boarding-callouts">
        <div class="callout callout--key">
          <p class="callout__value"><?= e(hu_number($o['boarding'])) ?></p>
          <p class="callout__label">Kollégista összesen</p>
        </div>
        <div class="callout">
          <p class="callout__value"><?= e(hu_number($o['boarding_graduating'])) ?></p>
          <p class="callout__label">Kollégista <strong>és végzős</strong></p>
        </div>
        <div class="callout">
          <p class="callout__value"><?= e(hu_number($o['boarding_non_graduating'])) ?></p>
          <p class="callout__label">Kollégista, <strong>nem végzős</strong></p>
        </div>
      </div>
      <div><?= View::partial('admin/residency-matrix', ['overview' => $o]) ?></div>
    </div>
  </section>

  <!-- 3. Per program -->
  <section class="panel" id="szakmak" aria-labelledby="programs-title" tabindex="-1">
    <header class="panel__head">
      <div>
        <h2 class="panel__title" id="programs-title">Szakmánkénti bontás</h2>
        <p class="panel__desc">Jelentkezések száma szakmánként; egy jelentkező több szakmánál is szerepelhet.</p>
      </div>
    </header>

    <?= View::partial('admin/program-bars', ['byProgram' => $byProgram]) ?>

    <div class="table-scroll stat-table-wrap" role="region" aria-labelledby="program-table-caption" tabindex="0">
      <table class="stat-table">
        <caption id="program-table-caption">Részletes adatok szakmánként</caption>
        <thead>
          <tr>
            <th scope="col">Szakma</th>
            <th scope="col" class="num">Jelentkező</th>
            <th scope="col" class="num stat-table__key">Végzős</th>
            <th scope="col" class="num">Nem végzős</th>
            <th scope="col" class="num">Kollégista</th>
            <th scope="col" class="num stat-table__key">Kollégista + végzős</th>
            <th scope="col" class="num">Kollégista + nem végzős</th>
            <th scope="col" class="num">Bejárós</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byProgram as $p): ?>
            <tr>
              <th scope="row"><?= e($p['name']) ?><?php if (!$p['is_active']): ?> <span class="tag">inaktív</span><?php endif; ?></th>
              <td class="num"><strong><?= e(hu_number($p['total'])) ?></strong></td>
              <td class="num stat-table__key"><?= e(hu_number($p['graduating'])) ?></td>
              <td class="num"><?= e(hu_number($p['non_graduating'])) ?></td>
              <td class="num"><?= e(hu_number($p['boarding'])) ?></td>
              <td class="num stat-table__key"><?= e(hu_number($p['boarding_graduating'])) ?></td>
              <td class="num"><?= e(hu_number($p['boarding_non_graduating'])) ?></td>
              <td class="num"><?= e(hu_number($p['commuter'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th scope="row">Egyedi jelentkezők <span class="stat-table__hint">(nem az oszlopok összege)</span></th>
            <td class="num"><strong><?= e(hu_number($o['total'])) ?></strong></td>
            <td class="num stat-table__key"><?= e(hu_number($o['graduating'])) ?></td>
            <td class="num"><?= e(hu_number($o['non_graduating'])) ?></td>
            <td class="num"><?= e(hu_number($o['boarding'])) ?></td>
            <td class="num stat-table__key"><?= e(hu_number($o['boarding_graduating'])) ?></td>
            <td class="num"><?= e(hu_number($o['boarding_non_graduating'])) ?></td>
            <td class="num"><?= e(hu_number($o['commuter'])) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="footnote">
      <?= icon('info') ?>
      <span>Egy jelentkező több szakmát is megjelölhet, ezért a szakmánkénti számok összege nagyobb lehet a jelentkezők számánál.
      Átlagosan <strong><?= e(hu_number($average, 1)) ?></strong> szakmát jelöltek meg jelentkezőnként (összesen <?= e(hu_number($selections)) ?> megjelölés, <?= e(hu_number($represented)) ?> / <?= e(hu_number($activePrograms)) ?> aktív szakma képviselve).</span>
    </p>
  </section>

  <!-- 4. Over time -->
  <section class="panel" aria-labelledby="timeline-title">
    <header class="panel__head">
      <div>
        <h2 class="panel__title" id="timeline-title">Jelentkezések időrendben</h2>
        <p class="panel__desc">Napi bontás<?= count($daily) >= 30 ? ' (az utolsó 30 nap, amelyen jelentkezés érkezett)' : '' ?>.</p>
      </div>
    </header>
    <div class="columns" aria-hidden="true">
      <?php foreach ($daily as $day): ?>
        <div class="columns__item">
          <span class="columns__value"><?= e($day['total']) ?></span>
          <svg class="columns__bar" viewBox="0 0 10 100" preserveAspectRatio="none" focusable="false">
            <rect class="bar__rest" x="0" y="<?= e(100 - share($day['total'], $dailyMax)) ?>" width="10" height="<?= e(share($day['total'], $dailyMax)) ?>"/>
            <rect class="bar__grad" x="0" y="<?= e(100 - share($day['graduating'], $dailyMax)) ?>" width="10" height="<?= e(share($day['graduating'], $dailyMax)) ?>"/>
          </svg>
          <span class="columns__label"><?= e(hu_short_date($day['day'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <details class="data-details">
      <summary>Adatok táblázatban</summary>
      <div class="table-scroll">
        <table class="stat-table stat-table--compact">
          <caption class="visually-hidden">Napi jelentkezések száma</caption>
          <thead><tr><th scope="col">Nap</th><th scope="col" class="num">Jelentkező</th><th scope="col" class="num">Ebből végzős</th></tr></thead>
          <tbody>
            <?php foreach (array_reverse($daily) as $day): ?>
              <tr>
                <th scope="row"><?= e(hu_datetime($day['day'], false)) ?></th>
                <td class="num"><?= e(hu_number($day['total'])) ?></td>
                <td class="num"><?= e(hu_number($day['graduating'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </section>
<?php endif; ?>
