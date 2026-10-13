<?php
/** @var list<array{type: string, message: string}> $flashes */
if (empty($flashes)) {
    return;
}
$icons = ['success' => 'check-circle', 'error' => 'alert', 'info' => 'info'];
?>
<div class="flashes">
  <?php foreach ($flashes as $flash): ?>
    <?php $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
    <div class="alert alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
      <?= icon($icons[$type]) ?>
      <p><?= e($flash['message']) ?></p>
    </div>
  <?php endforeach; ?>
</div>
