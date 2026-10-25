<h1>Weekoverzicht zwemschool</h1>
<p class="muted">Per dag en uur: welke groepen er zwemmen en hoeveel leden ingedeeld zijn.
<?php if ($unassigned): ?> <a href="<?= e(url('series')) ?>#niet-ingedeeld"><?= (int) $unassigned ?> leden nog niet ingedeeld</a>.<?php endif; ?></p>
<?php if (!$hours): ?>
  <p class="muted">Er zijn nog geen zwemschoolblokken. <a href="<?= e(url('series_form')) ?>">Maak een reeks</a>.</p>
<?php else: ?>
<div class="table-wrap"><table class="table week-grid">
  <thead><tr><th>Uur</th><?php foreach (SeriesService::DAYS as $d => $name): ?><th><?= e(ucfirst($name)) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach ($hours as $h): ?>
    <tr><th scope="row"><?= e($h) ?></th>
    <?php foreach (SeriesService::DAYS as $d => $name): ?>
      <td data-label="<?= e(ucfirst($name)) ?>">
      <?php foreach ($cells[$d][$h] ?? [] as $b): ?>
        <div class="week-block">
          <?php foreach ($b['groups'] as $g): ?><?= group_chip($g['name']) ?> <?php endforeach; ?>
          <div class="small"><a href="<?= e(url('series_show', ['id' => $b['id']])) ?>#leden"><?= (int) $b['count'] ?> <?= $b['count'] === 1 ? 'lid' : 'leden' ?></a></div>
        </div>
      <?php endforeach; ?>
      </td>
    <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
