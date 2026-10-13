<?php
/**
 * @var int             $status
 * @var string          $message
 * @var \Throwable|null $debug   only set when APP_DEBUG=true
 */
use App\Core\Request;

$copy = [
    400 => ['Hibás kérés', 'A kérést nem tudtuk feldolgozni. Töltsd újra az oldalt, és próbáld meg ismét.'],
    403 => ['Hozzáférés megtagadva', 'Ehhez a művelethez nincs jogosultságod, vagy lejárt a biztonsági token. Töltsd újra az oldalt, és próbáld meg ismét.'],
    404 => ['Az oldal nem található', 'A keresett oldal nem létezik, vagy időközben megszűnt.'],
    405 => ['Nem engedélyezett művelet', 'Ez a cím nem érhető el ilyen típusú kéréssel.'],
    429 => ['Túl sok kérés', 'Rövid időn belül túl sok kérés érkezett. Várj egy kicsit, majd próbáld újra.'],
    500 => ['Váratlan hiba történt', 'Elnézést, valami hiba történt a kérés feldolgozása közben. Kérjük, próbáld újra később.'],
    503 => ['Átmenetileg nem elérhető', 'A rendszer most nem éri el az adatbázist. Kérjük, próbáld újra néhány perc múlva.'],
];
[$heading, $text] = $copy[$status] ?? $copy[500];
if ($message !== '') {
    $text = $message;
}
$inAdmin = str_starts_with(Request::path(), '/admin');
?>
<div class="error-card">
  <p class="error-card__code" aria-hidden="true"><?= e($status) ?></p>
  <div class="error-card__body">
    <p class="eyebrow eyebrow--light">Hibakód: <?= e($status) ?></p>
    <h1 class="error-card__title"><?= e($heading) ?></h1>
    <p class="error-card__text"><?= e($text) ?></p>
    <div class="error-card__actions">
      <?php if ($inAdmin): ?>
        <a class="btn btn--light" href="<?= e(url('/admin')) ?>"><?= icon('arrow-left') ?>Vissza az adminfelületre</a>
      <?php else: ?>
        <a class="btn btn--light" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?>Vissza a regisztrációhoz</a>
      <?php endif; ?>
    </div>
    <?php if ($debug !== null): ?>
      <details class="error-card__debug" open>
        <summary>Fejlesztői részletek (APP_DEBUG=true)</summary>
        <pre><?= e($debug::class . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine()) ?>
<?= e($debug->getPrevious() !== null ? 'Előzmény: ' . $debug->getPrevious()->getMessage() : '') ?></pre>
      </details>
    <?php endif; ?>
  </div>
</div>
