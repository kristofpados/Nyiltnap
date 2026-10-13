<?php
/** @var array<string, mixed> $registration */
?>
<section class="container success-hero" aria-labelledby="success-title">
  <div class="success-hero__badge" aria-hidden="true">
    <svg class="success-check" viewBox="0 0 64 64" focusable="false">
      <circle class="success-check__ring" cx="32" cy="32" r="29" fill="none" stroke="#7A93FF" stroke-width="2.5"/>
      <path class="success-check__tick" d="M20 33.5l8 8 16-17" fill="none" stroke="#F2B45A" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>
  <p class="eyebrow eyebrow--light">Regisztráció rögzítve</p>
  <h1 class="success-hero__title" id="success-title">Sikeres regisztráció</h1>
  <p class="success-hero__lede">Köszönjük, <strong><?= e($registration['full_name']) ?></strong>! Szeretettel várunk a nyílt napon.</p>
</section>
