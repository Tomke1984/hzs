<?php
/**
 * Eén training of wedstrijd in de trainersplanning, met trainers en acties.
 * Verwacht: $a (Coverage::enrich-rij), $pool (id => naam), $uid (ingelogde gebruiker), $back ('activity' of ''),
 * optioneel $keep (weeks/show om na een actie naar dezelfde lijst terug te keren).
 */
$keep = $keep ?? [];
$past = $a['ends_at'] <= now();
$mine = null;
foreach ($a['trainers'] as $t) {
    if ($t['id'] === $uid) {
        $mine = $t;
    }
}
$presentIds = array_map(fn($t) => $t['id'], array_filter($a['trainers'], fn($t) => !$t['absent']));
$free = array_diff_key($pool, array_flip($presentIds));
$hidden = function () use ($a, $back, $keep): string {
    $h = csrf_field() . '<input type="hidden" name="activity_id" value="' . (int) $a['id'] . '">';
    if ($back !== '') {
        $h .= '<input type="hidden" name="back" value="' . e($back) . '">';
    }
    foreach ($keep as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    return $h;
};
?>
<?php $isMine = $mine && !$mine['absent']; ?>
<article class="cover-card<?= $a['missing'] > 0 && !$past ? ' is-short' : '' ?><?= $past ? ' is-past' : '' ?><?= $isMine ? ' is-mine' : '' ?>" id="a<?= (int) $a['id'] ?>">
  <div class="cover-head">
    <div>
      <?php if ($isMine): ?><span class="chip mine-chip"><?= $a['type_code'] === 'TRAINING' ? 'Jij geeft training' : 'Jij gaat mee' ?></span><?php endif; ?>
      <span class="cover-when"><?= e(fmt_date($a['starts_at'])) ?> · <?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span>
      <?php if ($back === ''): ?>
        <a class="cover-title" href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a>
      <?php else: ?>
        <span class="cover-title"><?= e($a['title']) ?></span>
      <?php endif; ?>
      <span class="chip type-<?= e(strtolower($a['type_code'])) ?>"><?= e($a['type_name']) ?></span>
      <?php foreach ($a['groups'] as $g): ?><?= group_chip($g['name']) ?> <?php endforeach; ?>
      <?php if (!$a['groups'] && $a['audience'] === 'CLUB'): ?><span class="chip">Hele club</span><?php endif; ?>
      <?php if ($a['location']): ?><span class="muted small"><?= e($a['location']) ?></span><?php endif; ?>
    </div>
    <?php if ($a['missing'] > 0): ?>
      <span class="chip bad"><?= $a['present'] === 0 ? 'Geen trainer' : 'Trainer gezocht' ?> · <?= (int) $a['present'] ?>/<?= (int) $a['required'] ?></span>
    <?php else: ?>
      <span class="chip ok">In orde · <?= (int) $a['present'] ?>/<?= (int) $a['required'] ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$a['trainers']): ?>
    <p class="muted small">Nog niemand ingepland.</p>
  <?php else: ?>
  <ul class="cover-trainers">
    <?php foreach ($a['trainers'] as $t): ?>
      <li class="<?= $t['absent'] ? 'is-absent' : '' ?>">
        <span class="cover-name"><?= e($t['name']) ?><?= $t['id'] === $uid ? ' (jij)' : '' ?></span>
        <?php if ($t['absent'] && $t['auto']): ?>
          <span class="chip warn">Op wedstrijd</span> <span class="muted small"><?= e(substr((string) $t['note'], 14)) ?></span>
        <?php elseif ($t['absent']): ?>
          <span class="chip att-absent">Afgemeld</span><?php if ($t['note']): ?> <span class="muted small"><?= e($t['note']) ?></span><?php endif; ?>
        <?php elseif ($t['default']): ?>
          <span class="chip off">Vast</span>
        <?php else: ?>
          <span class="chip att-present">Ingepland</span>
        <?php endif; ?>
        <?php if (!$past && !$t['auto']): ?>
        <span class="cover-actions">
          <?php if ($t['absent']): ?>
            <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden() ?><input type="hidden" name="do" value="back"><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Terugzetten</button></form>
          <?php else: ?>
            <details class="cover-absent">
              <summary class="btn btn-small btn-ghost"><?= $t['id'] === $uid ? 'Ik kan niet' : 'Afmelden' ?></summary>
              <form method="post" action="<?= e(url('coverage_action')) ?>" class="inline-form cover-absent-form">
                <?= $hidden() ?><input type="hidden" name="do" value="absent"><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>">
                <?php $short = $a['present'] - 1 < $a['required']; ?>
                <label class="small">Wie neemt over?<?= $short ? ' *' : '' ?>
                  <select name="replacement"<?= $short ? ' required' : '' ?>>
                    <option value=""><?= $short ? 'Kies…' : 'Niemand nodig (er blijven genoeg trainers)' ?></option>
                    <?php foreach ($free as $pid => $pname): if ($pid === $t['id']) { continue; } ?><option value="<?= (int) $pid ?>"><?= e($pname) ?></option><?php endforeach; ?>
                    <?php if ($short): ?><option value="none">Nog geen vervanger – trainer gezocht</option><?php endif; ?>
                  </select>
                </label>
                <input type="text" name="note" maxlength="200" placeholder="Reden (optioneel)" aria-label="Reden van de afmelding">
                <button class="btn btn-small" type="submit"><?= $t['id'] === $uid ? 'Afmelden' : e($t['name']) . ' afmelden' ?></button>
              </form>
            </details>
            <?php if (!$t['default']): ?>
              <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden() ?><input type="hidden" name="do" value="remove"><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>"><button class="btn btn-small btn-ghost" type="submit" title="Uit de planning halen (bv. per vergissing ingepland)">Weghalen</button></form>
            <?php endif; ?>
          <?php endif; ?>
        </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php $canClaim = isset($pool[$uid]) && (!$mine || ($mine['absent'] && !$mine['auto'])); ?>
  <?php if (!$past && ($canClaim || $free)): ?>
  <div class="cover-foot">
    <?php if ($canClaim): ?>
      <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden() ?><input type="hidden" name="do" value="claim">
        <button class="btn btn-small<?= $a['missing'] > 0 ? '' : ' btn-ghost' ?>" type="submit"><?= $a['type_code'] === 'COMPETITION' ? 'Ik ga mee' : ($a['missing'] > 0 ? 'Ik neem over' : 'Ik help mee') ?></button></form>
    <?php endif; ?>
    <?php if ($free): ?>
      <?php $isMeet = $a['type_code'] === 'COMPETITION'; ?>
      <?php if ($a['missing'] === 0 || $isMeet): ?><details class="cover-absent"<?= $isMeet && $a['missing'] > 0 ? ' open' : '' ?>><summary class="btn btn-small btn-ghost"><?= $isMeet ? 'Trainers toevoegen' : 'Andere trainer toevoegen' ?></summary><?php endif; ?>
      <?php if ($isMeet): ?>
        <form method="post" action="<?= e(url('coverage_action')) ?>" class="cover-assign cover-multi"><?= $hidden() ?><input type="hidden" name="do" value="assign"><input type="hidden" name="multi" value="1">
          <fieldset><legend class="small muted">Vink aan wie meegaat:</legend>
            <?php foreach ($free as $pid => $pname): ?><label class="check"><input type="checkbox" name="user_ids[]" value="<?= (int) $pid ?>"> <?= e($pname) ?></label><?php endforeach; ?>
          </fieldset>
          <button class="btn btn-small" type="submit">Inplannen</button>
        </form>
      <?php else: ?>
      <form method="post" action="<?= e(url('coverage_action')) ?>" class="cover-assign"><?= $hidden() ?><input type="hidden" name="do" value="assign">
        <select name="user_id" required aria-label="Trainer inplannen voor <?= e($a['title'] . ' ' . fmt_dt($a['starts_at'])) ?>">
          <option value="">Trainer inplannen…</option>
          <?php foreach ($free as $pid => $pname): ?><option value="<?= (int) $pid ?>"><?= e($pname) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-small btn-ghost" type="submit">Inplannen</button>
      </form>
      <?php endif; ?>
      <?php if ($a['missing'] === 0 || $isMeet): ?></details><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</article>
