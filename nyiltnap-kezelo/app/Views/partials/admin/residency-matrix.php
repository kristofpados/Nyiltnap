<?php
/**
 * Boarding / commuter × graduating cross-table — the key "kollégista"
 * breakdown, readable at a glance and fully accessible as a data table.
 *
 * @var array<string, int> $overview from Statistics::overview()
 */
$o = $overview;
$boardingShare = share($o['boarding'], max(1, $o['total']));
[$boardingPercent, $commuterPercent] = percent_pair($o['boarding'], $o['total']);
?>
<p class="matrix-lead">
  <strong><?= e(hu_number($o['boarding'])) ?></strong> kollégista jelentkező —
  ebből <strong><?= e(hu_number($o['boarding_graduating'])) ?></strong> végzős
  és <strong><?= e(hu_number($o['boarding_non_graduating'])) ?></strong> nem végzős.
</p>

<div class="split" aria-hidden="true">
  <svg class="split__bar" viewBox="0 0 100 10" preserveAspectRatio="none" focusable="false">
    <rect class="split__rest" x="0" y="0" width="100" height="10"/>
    <rect class="split__main" x="0" y="0" width="<?= e($boardingShare) ?>" height="10"/>
  </svg>
  <div class="split__labels">
    <span><?= icon('bed') ?>Kollégista <?= e($boardingPercent) ?></span>
    <span>Bejárós <?= e($commuterPercent) ?><?= icon('bus') ?></span>
  </div>
</div>

<div class="table-scroll">
  <table class="matrix">
    <caption class="visually-hidden">Kollégista és bejárós jelentkezők száma végzős státusz szerint</caption>
    <thead>
      <tr>
        <th scope="col"><span class="visually-hidden">Kollégiumi igény</span></th>
        <th scope="col" class="num">Végzős</th>
        <th scope="col" class="num">Nem végzős</th>
        <th scope="col" class="num">Összesen</th>
      </tr>
    </thead>
    <tbody>
      <tr class="matrix__row--key">
        <th scope="row"><?= icon('bed') ?>Kollégista</th>
        <td class="num"><?= e(hu_number($o['boarding_graduating'])) ?></td>
        <td class="num"><?= e(hu_number($o['boarding_non_graduating'])) ?></td>
        <td class="num matrix__total"><?= e(hu_number($o['boarding'])) ?></td>
      </tr>
      <tr>
        <th scope="row"><?= icon('bus') ?>Bejárós</th>
        <td class="num"><?= e(hu_number($o['commuter_graduating'])) ?></td>
        <td class="num"><?= e(hu_number($o['commuter_non_graduating'])) ?></td>
        <td class="num matrix__total"><?= e(hu_number($o['commuter'])) ?></td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <th scope="row">Összesen</th>
        <td class="num"><?= e(hu_number($o['graduating'])) ?></td>
        <td class="num"><?= e(hu_number($o['non_graduating'])) ?></td>
        <td class="num matrix__total"><?= e(hu_number($o['total'])) ?></td>
      </tr>
    </tfoot>
  </table>
</div>
