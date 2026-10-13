<?php
/**
 * Horizontal bars per program: full bar = all applicants interested in the
 * program, dark segment = graduating applicants among them. Exact values
 * are always printed as text, so the chart never relies on colour alone.
 *
 * @var list<array{name: string, icon: ?string, is_active: bool, total: int, graduating: int, non_graduating: int}> $byProgram
 */
$max = max([1, ...array_column($byProgram, 'total')]);
?>
<div class="legend" aria-hidden="true">
  <span class="legend__item"><span class="legend__swatch legend__swatch--grad"></span>Végzős</span>
  <span class="legend__item"><span class="legend__swatch legend__swatch--rest"></span>Nem végzős</span>
</div>
<ul class="bars" role="list">
  <?php foreach ($byProgram as $program): ?>
    <li class="bar-row">
      <div class="bar-row__label">
        <span class="bar-row__icon"><?= program_icon($program['icon']) ?></span>
        <span class="bar-row__name">
          <?= e($program['name']) ?>
          <?php if (!$program['is_active']): ?><span class="tag">inaktív</span><?php endif; ?>
        </span>
      </div>
      <svg class="bar" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <rect class="bar__track" x="0" y="0" width="100" height="10"/>
        <rect class="bar__rest" x="0" y="0" width="<?= e(share($program['total'], $max)) ?>" height="10"/>
        <rect class="bar__grad" x="0" y="0" width="<?= e(share($program['graduating'], $max)) ?>" height="10"/>
      </svg>
      <p class="bar-row__values">
        <span class="bar-row__total"><strong><?= e(hu_number($program['total'])) ?></strong> jelentkező</span>
        <span class="bar-row__grad">ebből <strong><?= e(hu_number($program['graduating'])) ?></strong> végzős</span>
      </p>
    </li>
  <?php endforeach; ?>
</ul>
