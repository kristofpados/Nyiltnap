<?php
$details = array_filter([
    ['calendar', 'Dátum', (string) config('brand.event_date', '')],
    ['clock', 'Időpont', (string) config('brand.event_time', '')],
    ['pin', 'Helyszín', (string) config('brand.event_place', '')],
], static fn (array $row) => $row[2] !== '');
?>
<section class="container hero" aria-labelledby="hero-title">
  <div class="hero__copy">
    <p class="hero__eyebrow"><?= e(config('brand.event_name')) ?> · <?= e(config('brand.event_year')) ?> · Regisztráció</p>
    <h1 class="hero__title" id="hero-title">A jövőd <em>itt</em> kezdődik.</h1>
    <p class="hero__lede">Ismerd meg személyesen a szakmáinkat, a tanműhelyeket és a közösséget. Regisztrálj előre, hogy a nyílt napon a téged érdeklő területekre készülhessünk.</p>
    <div class="hero__actions">
      <a class="btn btn--primary btn--lg" href="#regisztracio">Regisztrálok<?= icon('arrow-down') ?></a>
      <span class="hero__note">Kb. 1 perc · 5 rövid kérdés</span>
    </div>
  </div>

  <?php if ($details !== []): ?>
    <aside class="event-card" aria-labelledby="event-card-title">
      <div class="event-card__head">
        <h2 class="eyebrow eyebrow--light" id="event-card-title"><?= e(config('brand.event_name')) ?> <?= e(config('brand.event_year')) ?></h2>
        <?= icon('door', 'event-card__icon') ?>
      </div>
      <dl class="event-card__list">
        <?php foreach ($details as [$iconName, $label, $value]): ?>
          <div class="event-card__row">
            <dt><?= icon($iconName) ?><?= e($label) ?></dt>
            <dd><?= e($value) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </aside>
  <?php endif; ?>

  <svg class="hero__art" viewBox="0 0 520 420" aria-hidden="true" focusable="false">
    <g fill="none" stroke="#F6F2E9" stroke-width="1">
      <path d="M60 420V230a200 200 0 0 1 400 0v190" stroke-opacity=".10"/>
      <path d="M120 420V250a140 140 0 0 1 280 0v170" stroke-opacity=".12"/>
      <path d="M180 420V270a80 80 0 0 1 160 0v150" stroke="#7A93FF" stroke-opacity=".55"/>
      <path d="M0 419.5h520" stroke-opacity=".16"/>
      <path d="M260 10v20M250 20h20" stroke-opacity=".3"/>
      <path d="M60 230h-24M484 230h-24M120 250h-12M412 250h-12" stroke-opacity=".22"/>
    </g>
  </svg>
</section>

<div class="facts">
  <ul class="container facts__grid" role="list">
    <li class="fact">
      <span class="fact__icon"><?= icon('clock') ?></span>
      <span><strong class="fact__title">Egy perc az egész</strong><span class="fact__text">Öt rövid kérdés, fiók létrehozása nélkül.</span></span>
    </li>
    <li class="fact">
      <span class="fact__icon"><?= icon('layers') ?></span>
      <span><strong class="fact__title">Több szakmát is választhatsz</strong><span class="fact__text">Jelöld meg mindazt, ami érdekel.</span></span>
    </li>
    <li class="fact">
      <span class="fact__icon"><?= icon('shield') ?></span>
      <span><strong class="fact__title">Biztonságban az adataid</strong><span class="fact__text">Csak az iskola illetékes munkatársai látják.</span></span>
    </li>
  </ul>
</div>
