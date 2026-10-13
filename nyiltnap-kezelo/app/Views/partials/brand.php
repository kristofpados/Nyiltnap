<?php
/**
 * Brand lockup: mark + school name + event label.
 * @var string|null $href  link target (null = not a link)
 * @var string|null $sub   second line (defaults to the event name)
 */
use App\Core\View;

$tag = isset($href) && $href !== null ? 'a' : 'span';
$sub ??= config('brand.event_name') . ' ' . config('brand.event_year');
?>
<<?= $tag ?> class="brand"<?= $tag === 'a' ? ' href="' . e($href) . '"' : '' ?>>
  <?= View::partial('mark') ?>
  <span class="brand__text">
    <span class="brand__name"><?= e(config('brand.school_name')) ?></span>
    <span class="brand__sub"><?= e($sub) ?></span>
  </span>
</<?= $tag ?>>
