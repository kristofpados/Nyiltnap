<?php
/**
 * @var \App\Support\RegistrationFilters $filters
 * @var int $pages
 */
if ($pages <= 1) {
    return;
}
$current = $filters->page;
$link = static fn (int $page): string => url('/admin/registrations', $filters->toQuery(['page' => $page === 1 ? null : $page]));

// Window of pages around the current one, plus first and last.
$numbers = array_unique(array_filter(
    [1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $pages],
    static fn (int $n) => $n >= 1 && $n <= $pages,
));
sort($numbers);
?>
<nav class="pagination" aria-label="Lapozás">
  <?php if ($current > 1): ?>
    <a class="pagination__step" href="<?= e($link($current - 1)) ?>" rel="prev"><?= icon('chevron-left') ?><span>Előző</span></a>
  <?php else: ?>
    <span class="pagination__step is-disabled" aria-hidden="true"><?= icon('chevron-left') ?><span>Előző</span></span>
  <?php endif; ?>

  <ol class="pagination__pages" role="list">
    <?php $previous = 0; ?>
    <?php foreach ($numbers as $number): ?>
      <?php if ($number - $previous > 1): ?>
        <li class="pagination__gap" aria-hidden="true">…</li>
      <?php endif; ?>
      <li>
        <?php if ($number === $current): ?>
          <a class="pagination__page is-current" href="<?= e($link($number)) ?>" aria-current="page"><span class="visually-hidden">Oldal </span><?= e($number) ?></a>
        <?php else: ?>
          <a class="pagination__page" href="<?= e($link($number)) ?>"><span class="visually-hidden">Oldal </span><?= e($number) ?></a>
        <?php endif; ?>
      </li>
      <?php $previous = $number; ?>
    <?php endforeach; ?>
  </ol>

  <?php if ($current < $pages): ?>
    <a class="pagination__step" href="<?= e($link($current + 1)) ?>" rel="next"><span>Következő</span><?= icon('chevron-right') ?></a>
  <?php else: ?>
    <span class="pagination__step is-disabled" aria-hidden="true"><span>Következő</span><?= icon('chevron-right') ?></span>
  <?php endif; ?>
</nav>
