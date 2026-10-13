<?php
/**
 * @var array{reference: string, full_name: string, current_school: string, programs: list<array{name: string, icon: ?string}>, residency: string, is_graduating: bool, created_at: string} $registration
 */
$r = $registration;
?>
<section class="success" aria-label="A jelentkezés összefoglalója">
  <div class="container">
    <article class="ticket">
      <div class="ticket__stub">
        <p class="eyebrow">Azonosítód</p>
        <p class="ticket__code"><?= e(format_reference($r['reference'])) ?></p>
        <p class="ticket__hint">Írd fel vagy fotózd le: ha kérdésed van, ezzel az azonosítóval gyorsan megtaláljuk a jelentkezésedet.</p>
        <p class="ticket__time">
          <?= icon('clock') ?>
          <span>Beküldve: <time datetime="<?= e(to_datetime($r['created_at'])?->format('c')) ?>"><?= e(hu_datetime($r['created_at'])) ?></time></span>
        </p>
      </div>

      <div class="ticket__details">
        <h2 class="ticket__heading">A megadott adatok</h2>
        <dl class="summary">
          <div class="summary__row">
            <dt>Teljes név</dt>
            <dd><?= e($r['full_name']) ?></dd>
          </div>
          <div class="summary__row">
            <dt>Jelenlegi iskola</dt>
            <dd><?= e($r['current_school']) ?></dd>
          </div>
          <div class="summary__row">
            <dt>Érdeklődési terület</dt>
            <dd>
              <ul class="chip-list" role="list">
                <?php foreach ($r['programs'] as $program): ?>
                  <li class="chip"><?= program_icon($program['icon']) ?><?= e($program['name']) ?></li>
                <?php endforeach; ?>
              </ul>
            </dd>
          </div>
          <div class="summary__row">
            <dt>Kollégium</dt>
            <dd>
              <?php if ($r['residency'] === 'boarding'): ?>
                <span class="badge badge--boarding"><?= icon('bed') ?>Kollégista</span>
              <?php else: ?>
                <span class="badge badge--commuter"><?= icon('bus') ?>Bejárós</span>
              <?php endif; ?>
            </dd>
          </div>
          <div class="summary__row">
            <dt>Végzős</dt>
            <dd>
              <?php if ($r['is_graduating']): ?>
                <span class="badge badge--grad"><?= icon('cap') ?>Igen, végzős</span>
              <?php else: ?>
                <span class="badge badge--muted">Nem végzős</span>
              <?php endif; ?>
            </dd>
          </div>
        </dl>
      </div>
    </article>

    <div class="success__actions">
      <a class="btn btn--primary btn--lg" href="<?= e(url('/')) ?>"><?= icon('plus') ?>Új jelentkezés rögzítése</a>
      <p class="success__note">Elírtál valamit? Töltsd ki újra az űrlapot, és szólj az iskola munkatársainak, hogy a hibás jelentkezést töröljék.</p>
    </div>
  </div>
</section>
