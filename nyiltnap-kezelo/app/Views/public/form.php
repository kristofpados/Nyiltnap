<?php
/**
 * @var list<array{id: int, name: string, description: ?string, icon: ?string}> $programs
 * @var array{full_name: string, current_school: string, programs: list<int>, residency: ?string, graduating: ?string} $old
 * @var array<string, string> $errors
 * @var string|null $formError
 * @var string $formToken
 */
use App\Forms\RegistrationForm;

$error = static fn (string $field): ?string => $errors[$field] ?? null;
$describedBy = static function (string $field, ?string $hint = null) use ($error): string {
    $ids = array_filter([$hint, $error($field) !== null ? $field . '-error' : null]);

    return $ids === [] ? '' : ' aria-describedby="' . e(implode(' ', $ids)) . '"';
};
$invalid = static fn (string $field): string => $error($field) !== null ? ' aria-invalid="true"' : '';

// Where each error-summary link jumps to.
$firstProgram = $programs[0]['id'] ?? null;
$targets = [
    'full_name'      => 'full_name',
    'current_school' => 'current_school',
    'programs'       => $firstProgram !== null ? 'program-' . $firstProgram : 'programs',
    'residency'      => 'residency-boarding',
    'graduating'     => 'graduating-yes',
];

$fieldError = static function (string $field) use ($error): string {
    $message = $error($field);

    return '<p class="field__error" id="' . e($field) . '-error" data-error-for="' . e($field) . '"' . ($message === null ? ' hidden' : '') . '>'
        . icon('alert') . '<span>' . e($message ?? '') . '</span></p>';
};
?>
<section class="registration" id="regisztracio" aria-labelledby="form-title">
  <div class="container registration__grid">

    <form class="form-card" method="post" action="<?= e(url('/')) ?>#regisztracio" data-registration-form>
      <?= csrf_field() ?>
      <input type="hidden" name="_form_token" value="<?= e($formToken) ?>">

      <header class="form-card__head">
        <p class="eyebrow">Jelentkezési lap</p>
        <h2 class="form-card__title" id="form-title">Regisztráció a nyílt napra</h2>
        <p class="form-card__intro">Minden mező kitöltése kötelező. Az egész körülbelül egy percet vesz igénybe.</p>
      </header>

      <div class="form-card__body">
        <?php if (!empty($formError)): ?>
          <div class="alert alert--error" role="alert">
            <?= icon('alert') ?>
            <p><?= e($formError) ?></p>
          </div>
        <?php endif; ?>

        <div class="error-summary" role="alert" tabindex="-1" aria-labelledby="error-summary-title" data-error-summary<?= $errors === [] ? ' hidden' : '' ?>>
          <p class="error-summary__title" id="error-summary-title"><?= icon('alert') ?><span>Kérjük, javítsd a jelölt mezőket:</span></p>
          <ul class="error-summary__list" data-error-summary-list>
            <?php foreach ($errors as $field => $message): ?>
              <li><a href="#<?= e($targets[$field] ?? $field) ?>"><?= e($message) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- 01 — Personal details -->
        <fieldset class="form-section">
          <legend class="form-section__legend"><span class="step" aria-hidden="true">01</span>Rólad</legend>
          <div class="form-section__body form-grid-2">
            <div class="field" data-field="full_name">
              <label class="field__label" for="full_name">Teljes név<span class="field__req" aria-hidden="true">*</span></label>
              <p class="field__hint" id="full_name-hint">Vezeték- és keresztneved.</p>
              <input class="input" type="text" id="full_name" name="full_name"
                     value="<?= e($old['full_name']) ?>"
                     required minlength="<?= RegistrationForm::NAME_MIN ?>" maxlength="<?= RegistrationForm::NAME_MAX ?>"
                     autocomplete="name" autocapitalize="words" spellcheck="false"
                     placeholder="pl. Kovács Anna"<?= $describedBy('full_name', 'full_name-hint') ?><?= $invalid('full_name') ?>>
              <?= $fieldError('full_name') ?>
            </div>

            <div class="field" data-field="current_school">
              <label class="field__label" for="current_school">Jelenlegi iskolád<span class="field__req" aria-hidden="true">*</span></label>
              <p class="field__hint" id="current_school-hint">Az iskola neve, ahová most jársz.</p>
              <input class="input" type="text" id="current_school" name="current_school"
                     value="<?= e($old['current_school']) ?>"
                     required minlength="<?= RegistrationForm::SCHOOL_MIN ?>" maxlength="<?= RegistrationForm::SCHOOL_MAX ?>"
                     autocomplete="off" autocapitalize="words"
                     placeholder="pl. Példa Utcai Általános Iskola"<?= $describedBy('current_school', 'current_school-hint') ?><?= $invalid('current_school') ?>>
              <?= $fieldError('current_school') ?>
            </div>
          </div>
        </fieldset>

        <!-- 02 — Programs -->
        <fieldset class="form-section<?= $error('programs') !== null ? ' has-error' : '' ?>" id="programs" data-field="programs"<?= $describedBy('programs', 'programs-hint') ?>>
          <legend class="form-section__legend"><span class="step" aria-hidden="true">02</span>Melyik szakma érdekel?<span class="visually-hidden"> (legalább egyet válassz)</span></legend>
          <div class="form-section__body">
            <p class="form-section__hint" id="programs-hint">
              Több szakmát is megjelölhetsz.
              <span class="selection-count" data-selection-count aria-live="polite"></span>
            </p>
            <?= $fieldError('programs') ?>

            <?php if ($programs === []): ?>
              <div class="inline-empty">
                <?= icon('info') ?>
                <p>Jelenleg nincs választható szakma. Kérjük, nézz vissza később, vagy fordulj az iskola titkárságához.</p>
              </div>
            <?php else: ?>
              <div class="tiles">
                <?php foreach ($programs as $program): ?>
                  <?php $inputId = 'program-' . $program['id']; ?>
                  <label class="tile" for="<?= e($inputId) ?>">
                    <input class="tile__input" type="checkbox" id="<?= e($inputId) ?>" name="programs[]"
                           value="<?= e($program['id']) ?>"<?= in_array($program['id'], $old['programs'], true) ? ' checked' : '' ?>>
                    <span class="tile__icon"><?= program_icon($program['icon']) ?></span>
                    <span class="tile__text">
                      <span class="tile__name"><?= e($program['name']) ?></span>
                      <?php if (($program['description'] ?? '') !== ''): ?>
                        <span class="tile__desc"><?= e($program['description']) ?></span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </fieldset>

        <!-- 03 — Boarding or commuting -->
        <fieldset class="form-section<?= $error('residency') !== null ? ' has-error' : '' ?>" id="residency" data-field="residency"<?= $describedBy('residency') ?>>
          <legend class="form-section__legend"><span class="step" aria-hidden="true">03</span>Kollégista vagy bejárós lennél?</legend>
          <div class="form-section__body">
            <?= $fieldError('residency') ?>
            <div class="tiles tiles--two">
              <label class="tile tile--radio" for="residency-boarding">
                <input class="tile__input" type="radio" id="residency-boarding" name="residency" value="boarding" required<?= $old['residency'] === 'boarding' ? ' checked' : '' ?>>
                <span class="tile__icon"><?= icon('bed') ?></span>
                <span class="tile__text">
                  <span class="tile__name">Kollégista</span>
                  <span class="tile__desc">A tanítási héten a kollégiumban laknék.</span>
                </span>
              </label>
              <label class="tile tile--radio" for="residency-commuter">
                <input class="tile__input" type="radio" id="residency-commuter" name="residency" value="commuter"<?= $old['residency'] === 'commuter' ? ' checked' : '' ?>>
                <span class="tile__icon"><?= icon('bus') ?></span>
                <span class="tile__text">
                  <span class="tile__name">Bejárós</span>
                  <span class="tile__desc">Naponta otthonról járnék be.</span>
                </span>
              </label>
            </div>
          </div>
        </fieldset>

        <!-- 04 — Graduating -->
        <fieldset class="form-section<?= $error('graduating') !== null ? ' has-error' : '' ?>" id="graduating" data-field="graduating"<?= $describedBy('graduating', 'graduating-hint') ?>>
          <legend class="form-section__legend"><span class="step" aria-hidden="true">04</span>Végzős vagy?</legend>
          <div class="form-section__body">
            <p class="form-section__hint" id="graduating-hint">Idén fejezed be a jelenlegi iskolád utolsó évfolyamát?</p>
            <?= $fieldError('graduating') ?>
            <div class="tiles tiles--two tiles--compact">
              <label class="tile tile--radio" for="graduating-yes">
                <input class="tile__input" type="radio" id="graduating-yes" name="graduating" value="1" required<?= $old['graduating'] === '1' ? ' checked' : '' ?>>
                <span class="tile__icon"><?= icon('cap') ?></span>
                <span class="tile__text">
                  <span class="tile__name">Igen</span>
                  <span class="tile__desc">Idén végzek.</span>
                </span>
              </label>
              <label class="tile tile--radio" for="graduating-no">
                <input class="tile__input" type="radio" id="graduating-no" name="graduating" value="0"<?= $old['graduating'] === '0' ? ' checked' : '' ?>>
                <span class="tile__icon"><?= icon('calendar') ?></span>
                <span class="tile__text">
                  <span class="tile__name">Nem</span>
                  <span class="tile__desc">Még nem idén végzek.</span>
                </span>
              </label>
            </div>
          </div>
        </fieldset>
      </div>

      <footer class="form-card__foot">
        <p class="privacy-note">
          <?= icon('lock') ?>
          <span>Az adataidat kizárólag a nyílt nap szervezéséhez használjuk, és csak az iskola munkatársai látják. <a href="#adatkezeles">Adatkezelési tájékoztató</a></span>
        </p>
        <button class="btn btn--primary btn--lg form-card__submit" type="submit" data-submit>
          <span class="btn__spinner" aria-hidden="true"></span>
          <span data-submit-label>Jelentkezés elküldése</span>
          <?= icon('arrow-right') ?>
        </button>
      </footer>
    </form>

    <aside class="next-steps" aria-labelledby="next-steps-title">
      <p class="eyebrow">Mi történik ezután?</p>
      <h2 class="next-steps__title" id="next-steps-title">Három egyszerű lépés</h2>
      <ol class="steps" role="list">
        <li class="steps__item">
          <span class="steps__num" aria-hidden="true">01</span>
          <div>
            <h3 class="steps__title">Elküldöd a jelentkezést</h3>
            <p class="steps__text">Az adataid bekerülnek a nyílt nap jelentkezői közé.</p>
          </div>
        </li>
        <li class="steps__item">
          <span class="steps__num" aria-hidden="true">02</span>
          <div>
            <h3 class="steps__title">Megkapod az azonosítódat</h3>
            <p class="steps__text">A beküldés után a képernyőn megjelenik egy rövid azonosító. Érdemes felírni vagy lefotózni.</p>
          </div>
        </li>
        <li class="steps__item">
          <span class="steps__num" aria-hidden="true">03</span>
          <div>
            <h3 class="steps__title">Találkozunk a nyílt napon</h3>
            <p class="steps__text">A jelentkezések alapján készülünk a téged érdeklő szakmák bemutatásával.</p>
          </div>
        </li>
      </ol>
      <p class="next-steps__note"><?= icon('info') ?><span>Kérdésed van? Fordulj bizalommal az iskola titkárságához.</span></p>
    </aside>
  </div>
</section>
