<?php
/**
 * @var string      $title
 * @var string      $text
 * @var string|null $actionHref
 * @var string|null $actionLabel
 */
?>
<div class="empty">
  <svg class="empty__art" viewBox="0 0 120 96" aria-hidden="true" focusable="false">
    <path d="M10 92h100" fill="none" stroke="#0B1530" stroke-opacity=".18" stroke-width="1.5" stroke-linecap="round"/>
    <path d="M30 92V48a30 30 0 0 1 60 0v44" fill="none" stroke="#0B1530" stroke-opacity=".22" stroke-width="1.5"/>
    <path d="M44 92V54a16 16 0 0 1 32 0v38z" fill="#2C50E6" fill-opacity=".1" stroke="#2C50E6" stroke-opacity=".55" stroke-width="1.5"/>
    <path d="M53 92a7 7 0 0 1 14 0z" fill="#F2B45A"/>
  </svg>
  <p class="empty__title"><?= e($title) ?></p>
  <p class="empty__text"><?= e($text) ?></p>
  <?php if (!empty($actionHref)): ?>
    <a class="btn btn--ghost btn--sm" href="<?= e($actionHref) ?>"><?= e($actionLabel ?? '') ?></a>
  <?php endif; ?>
</div>
