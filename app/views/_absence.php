<?php
/**
 * Afmelden voor één activiteit (ouder of lid): eerst enkel de knop, de reden pas na de tik.
 * Verwacht: $absActivityId (int), $absMemberId (int), $absent (?array met 'reason'), $absBack (?string, bv. 'dashboard'),
 * optioneel $absLabel (tekst van de knop, standaard "Afmelden") en $absWho (voornaam, voor wie meerdere kinderen heeft).
 */
$absLabel ??= 'Afmelden';
$absWho ??= '';
?>
<?php if ($absent): ?>
  <form class="absence-form" method="post" action="<?= e(url('absence')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="activity_id" value="<?= (int) $absActivityId ?>">
    <input type="hidden" name="member_id" value="<?= (int) $absMemberId ?>">
    <?php if ($absBack): ?><input type="hidden" name="back" value="<?= e($absBack) ?>"><?php endif; ?>
    <input type="hidden" name="do" value="remove">
    <span class="chip warn"><?= $absWho !== '' ? e($absWho) . ' afgemeld' : 'Afgemeld' ?><?= $absent['reason'] ? ': ' . e($absent['reason']) : '' ?></span>
    <button class="btn btn-small btn-ghost" type="submit">Toch aanwezig</button>
  </form>
<?php else: ?>
  <details class="absence-pick">
    <summary class="btn btn-small"><?= e($absLabel) ?><?= $absWho !== '' ? ' ' . e($absWho) : '' ?></summary>
    <form class="absence-form" method="post" action="<?= e(url('absence')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="activity_id" value="<?= (int) $absActivityId ?>">
      <input type="hidden" name="member_id" value="<?= (int) $absMemberId ?>">
      <?php if ($absBack): ?><input type="hidden" name="back" value="<?= e($absBack) ?>"><?php endif; ?>
      <fieldset class="reason-picks">
        <legend>Reden (optioneel)</legend>
        <?php foreach (['Ziek', 'School', 'Op reis'] as $r): ?>
          <label class="pick"><input type="radio" name="reason_pick" value="<?= e($r) ?>"> <?= e($r) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <input name="reason" maxlength="200" placeholder="Andere reden" aria-label="Andere reden">
      <button class="btn btn-small" type="submit">Afmelden bevestigen</button>
      <button class="link-btn" type="button" data-close-details hidden>Toch niet</button>
    </form>
  </details>
<?php endif; ?>
