<?php
/**
 * @var string       $title
 * @var list<string> $styles  CSS files under assets/css (without extension)
 * @var list<string> $scripts JS files under assets/js (without extension)
 * @var bool         $noindex
 */
$pageTitle = trim(($title ?? '') !== '' ? $title . ' · ' : '') . config('brand.event_name') . ' · ' . config('brand.school_name');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="Regisztráció a(z) <?= e(config('brand.school_name')) ?> nyílt napjára.">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#0B1530">
<meta name="color-scheme" content="light">
<link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('/assets/fonts/inter-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('/assets/fonts/instrument-serif-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php foreach (array_merge(['tokens', 'base'], $styles ?? []) as $style): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . $style . '.css')) ?>">
<?php endforeach; ?>
<?php foreach ($scripts ?? [] as $script): ?>
<script src="<?= e(asset('js/' . $script . '.js')) ?>" defer></script>
<?php endforeach; ?>
