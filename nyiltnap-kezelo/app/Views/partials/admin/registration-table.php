<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var string $returnTo  validated admin path to come back to after deleting
 * @var string $labelId   id of the heading that labels the scroll region
 */
?>
<div class="table-scroll" role="region" aria-labelledby="<?= e($labelId) ?>" tabindex="0">
  <table class="data-table">
    <thead>
      <tr>
        <th scope="col">Jelentkező</th>
        <th scope="col">Jelenlegi iskola</th>
        <th scope="col">Szakmák</th>
        <th scope="col">Kollégium</th>
        <th scope="col">Végzős</th>
        <th scope="col">Beküldve</th>
        <th scope="col" class="data-table__actions-head"><span class="visually-hidden">Műveletek</span></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <?php
        $deleteUrl = url('/admin/registrations/' . $row['id'] . '/delete');
        $reference = format_reference($row['reference_code']);
        ?>
        <tr>
          <th scope="row" data-label="Jelentkező">
            <span class="person">
              <span class="person__name"><?= e($row['full_name']) ?></span>
              <span class="person__ref"><?= e($reference) ?></span>
            </span>
          </th>
          <td data-label="Jelenlegi iskola"><span class="cell-school"><?= e($row['current_school']) ?></span></td>
          <td data-label="Szakmák">
            <ul class="program-lines" role="list">
              <?php foreach ($row['programs'] as $program): ?>
                <li><?= program_icon($program['icon']) ?><span><?= e($program['name']) ?></span></li>
              <?php endforeach; ?>
            </ul>
          </td>
          <td data-label="Kollégium">
            <?php if ($row['residency_preference'] === 'boarding'): ?>
              <span class="badge badge--boarding"><?= icon('bed') ?>Kollégista</span>
            <?php else: ?>
              <span class="badge badge--commuter"><?= icon('bus') ?>Bejárós</span>
            <?php endif; ?>
          </td>
          <td data-label="Végzős">
            <?php if ($row['is_graduating']): ?>
              <span class="badge badge--grad"><?= icon('cap') ?>Igen</span>
            <?php else: ?>
              <span class="badge badge--muted">Nem</span>
            <?php endif; ?>
          </td>
          <td data-label="Beküldve" class="cell-date">
            <time datetime="<?= e(to_datetime($row['created_at'])?->format('c')) ?>" title="<?= e(hu_datetime($row['created_at'])) ?>"><?= e(hu_relative_datetime($row['created_at'])) ?></time>
          </td>
          <td class="data-table__actions">
            <a class="btn btn--danger-ghost btn--sm data-table__delete" title="Jelentkezés törlése"
               href="<?= e($deleteUrl . '?' . http_build_query(['return' => $returnTo], '', '&', PHP_QUERY_RFC3986)) ?>"
               data-delete-trigger
               data-action="<?= e($deleteUrl) ?>"
               data-name="<?= e($row['full_name']) ?>"
               data-school="<?= e($row['current_school']) ?>"
               data-reference="<?= e($row['reference_code']) ?>"
               data-reference-label="<?= e($reference) ?>"
               data-date="<?= e(hu_datetime($row['created_at'])) ?>">
              <?= icon('trash') ?><span class="btn__label">Törlés</span><span class="visually-hidden">: <?= e($row['full_name']) ?></span>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
