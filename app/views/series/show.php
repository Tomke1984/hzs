<div class="row-between">
  <h1><?= e($s['title']) ?> <?php if (!(int) $s['is_active']): ?><span class="chip off">Gestopt</span><?php endif; ?>
    <span class="chip <?= (int) $s['is_public'] ? 'ok' : 'warn' ?>"><?= (int) $s['is_public'] ? 'Op website' : 'Niet op website' ?></span>
    <?php if ($s['end_date'] < today()): ?><span class="chip off">Verlopen</span><?php endif; ?></h1>
  <span class="actions-inline">
    <form method="post" action="<?= e(url('series_public')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="back" value="?r=series_show&amp;id=<?= (int) $s['id'] ?>">
      <input type="hidden" name="public" value="<?= (int) $s['is_public'] ? 0 : 1 ?>">
      <button class="btn btn-ghost" type="submit"><?= (int) $s['is_public'] ? 'Van website halen' : 'Op website tonen' ?></button>
    </form>
    <a class="btn btn-ghost" href="<?= e(url('series_bulk')) ?>">Schrappen / verplaatsen</a>
    <a class="btn" href="<?= e(url('series_form', ['id' => $s['id']])) ?>">Reeks wijzigen</a>
    <form method="post" action="<?= e(url('series_delete')) ?>" data-confirm="Reeks &quot;<?= e($s['title']) ?>&quot; definitief verwijderen? Alle trainingen van deze reeks verdwijnen uit de kalender, behalve trainingen waarvan de aanwezigheden al zijn ingevuld (die blijven als losse trainingen bestaan).">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
      <button class="btn btn-ghost" type="submit">Verwijderen</button>
    </form>
  </span>
</div>
<section class="card">
  <dl class="facts">
    <dt>Type</dt><dd><?= e($s['type_name']) ?></dd>
    <dt>Groepen</dt><dd><?= e(implode(', ', array_column($s['groups'], 'name'))) ?></dd>
    <dt>Schema</dt>
    <dd><?php foreach ($s['slots'] as $sl): ?><?= e(SeriesService::DAYS[(int) $sl['weekday']]) ?> <?= e($sl['start_time']) ?>–<?= e($sl['end_time']) ?><?= $sl['location'] ? ' (' . e($sl['location']) . ')' : '' ?><br><?php endforeach; ?></dd>
    <?php if ($s['location']): ?><dt>Locatie</dt><dd><?= e($s['location']) ?></dd><?php endif; ?>
    <dt>Periode</dt><dd><?= e(fmt_date($s['start_date'])) ?> – <?= e(fmt_date($s['end_date'])) ?></dd>
  </dl>
</section>
<h2 class="section-title">Komende trainingen</h2>
<?php if (!$occ): ?><p class="card muted">Geen komende trainingen.</p><?php endif; ?>
<?php foreach ($occ as $o): $cancelled = $o['status'] === 'CANCELLED'; ?>
  <div class="card <?= $cancelled ? 'is-off' : '' ?>">
    <div class="row-between">
      <div>
        <a href="<?= e(url('activity', ['id' => $o['id']])) ?>"><strong><?= e(fmt_date(substr($o['starts_at'], 0, 10))) ?></strong> <?= e(fmt_time($o['starts_at'])) ?>–<?= e(fmt_time($o['ends_at'])) ?></a>
        <?php if ($o['location']): ?><span class="muted"> · <?= e($o['location']) ?></span><?php endif; ?>
        <?php if ((int) $o['is_custom']): ?><span class="chip off">aangepast</span><?php endif; ?>
        <?php if ($cancelled): ?><span class="chip off">Geschrapt<?= $o['status_note'] ? ': ' . e($o['status_note']) : '' ?></span><?php endif; ?>
      </div>
      <form method="post" action="<?= e(url('occurrence')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="activity_id" value="<?= (int) $o['id'] ?>">
        <input type="hidden" name="back" value="series">
        <?php if ($cancelled): ?>
          <input type="hidden" name="do" value="restore">
          <button class="btn btn-small btn-ghost" type="submit">Herstellen</button>
        <?php else: ?>
          <input type="hidden" name="do" value="cancel">
          <input name="reason" placeholder="Reden" maxlength="200">
          <button class="btn btn-small btn-ghost" type="submit">Schrappen</button>
        <?php endif; ?>
      </form>
    </div>
  </div>
<?php endforeach; ?>
