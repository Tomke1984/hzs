<h1>Welkom, <?= e(Auth::user()['display_name']) ?></h1>

<?php if (!empty($dbExposed)): ?>
<div class="alert alert-error"><strong>Beveiliging:</strong> het databasebestand staat binnen de webmap van de server. Als de webserver <code>.htaccess</code> negeert, kan iemand de hele ledendatabase downloaden. Verplaats het bestand naar een map buiten de webmap en pas <code>db.path</code> in <code>config.php</code> aan (zie README, “Database buiten de webmap”).</div>
<?php endif; ?>
<?php if (!empty($backupOverdue)): ?>
<div class="alert alert-error"><strong>Back-up:</strong> de laatste geslaagde back-up van de database is ouder dan 2 dagen (of er is er nog geen). Controleer of de map <code><?= e(Backup::dir()) ?></code> schrijfbaar is, of draai <code>php tools/backup.php</code>.</div>
<?php endif; ?>
<?php if (!empty($mailOff)): ?>
<div class="alert alert-error"><strong>Mail staat in testmodus:</strong> uitnodigingen, wachtwoordlinks en herinneringen worden niet verstuurd. <a href="<?= e(url('mail_settings')) ?>">Mail instellen →</a></div>
<?php endif; ?>
<?php if (!empty($keyInFile)): ?>
<div class="alert alert-info"><strong>Sleutel:</strong> medische opmerkingen worden versleuteld met de sleutel in <code>storage/app.key</code>. Zet die sleutel als <code>'app_key'</code> in <code>config.php</code> en bewaar een kopie veilig, apart van de back-ups: zonder sleutel zijn die gegevens onleesbaar (zie README, “Versleuteling”).</div>
<?php endif; ?>

<?php if ($family): ?>
<section class="next-trainings" aria-label="Volgende training">
  <?php foreach ($nextTrainings as $nt): $a = $nt['activity']; $m = $nt['member']; ?>
    <article class="card next-card<?= $nt['absent'] ? ' is-absent' : '' ?>">
      <div class="row-between"><p class="next-label">Volgende training<?= count($nextTrainings) > 1 ? ' · ' . e($m['first_name']) : '' ?></p>
        <a class="small" href="<?= e(url('member', ['id' => $m['id']])) ?>">Gegevens<?= count($nextTrainings) > 1 ? '' : ' van ' . e($m['first_name']) ?> →</a></div>
      <?php if (!$a): ?>
        <p class="muted">Geen training gepland.</p>
      <?php else: ?>
        <a class="next-when" href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e(ucfirst(fmt_date($a['starts_at']))) ?> · <?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></a>
        <p class="next-what"><?= e($a['title']) ?><?= $a['location'] ? ' · ' . e($a['location']) : '' ?></p>
        <div class="next-actions">
          <?php $absActivityId = (int) $a['id']; $absMemberId = (int) $m['id']; $absent = $nt['absent']; $absBack = 'dashboard'; $absWho = ''; $absLabel = 'Afmelden';
              require __DIR__ . '/_absence.php'; ?>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
<?php if ($missingEmergency): ?>
  <p class="alert alert-info reminder">
    <?php foreach ($missingEmergency as $i => $me): ?><?= $i ? ' · ' : '' ?><a href="<?= e(url('child_form', ['id' => $me['id']])) ?>#nood">Noodcontact voor <?= e($me['first_name']) ?> toevoegen →</a><?php endforeach; ?>
    <span class="muted small">Zo weet de trainer wie te bellen bij een ongeval.</span>
  </p>
<?php endif; ?>
<?php endif; ?>

<?php if (Auth::can('activities.read')): ?>
<section class="card">
  <div class="row-between">
    <h2><?= $mode === 'week' ? 'Activiteiten' : 'Komende activiteiten' ?></h2>
    <span class="actions-inline">
      <span class="segmented" role="group" aria-label="Weergave">
        <a data-keep-scroll href="<?= e(url('dashboard', ['view' => 'week'])) ?>"<?= $mode === 'week' ? ' aria-current="page"' : '' ?>>Week</a>
        <a data-keep-scroll href="<?= e(url('dashboard', ['view' => 'list'])) ?>"<?= $mode === 'list' ? ' aria-current="page"' : '' ?>>Lijst</a>
      </span>
      <a class="hide-mobile" href="<?= e(url('calendar')) ?>">Hele kalender →</a>
    </span>
  </div>
  <?php if ($mode === 'week'): ?>
    <?php $weekUrl = fn(int $o) => url('dashboard', array_filter(['view' => 'week', 'w' => $o ?: ''])); require __DIR__ . '/_weeknav.php'; ?>
    <div class="week-grid week-plain">
      <?php for ($i = 0; $i < 7; $i++): $day = date('Y-m-d', strtotime("$monday +$i days")); $items = $byDay[$day] ?? []; ?>
        <section class="week-day<?= $day === today() ? ' is-today' : '' ?><?= $items ? ' has-events' : '' ?>">
          <h3 class="week-head"><?= e(fmt_day($day)) ?></h3>
          <?php if (!$items): ?><p class="muted small week-empty">—</p><?php endif; ?>
          <?php foreach ($items as $a): $isShort = $a['short'] !== null && $a['ends_at'] > now(); ?>
            <a class="week-item type-<?= e(strtolower($a['type_code'])) ?><?= $a['ends_at'] <= now() ? ' is-past' : '' ?><?= $a['mine'] ? ' is-mine' : '' ?><?= $isShort ? ' is-short' : '' ?>" href="<?= e(url('activity', ['id' => $a['id']])) ?><?= $a['mine'] || $a['short'] !== null ? '#trainers' : '' ?>">
              <?php if ($a['mine']): ?><span class="chip mine-chip"><?= $a['type_code'] === 'TRAINING' ? 'Jij geeft training' : 'Jij gaat mee' ?></span><?php endif; ?>
              <span class="week-time"><?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?></span>
              <span class="week-title"><?= $a['type_code'] === 'COMPETITION' ? '<span class="meet-icon" aria-label="Wedstrijd" title="Wedstrijd">🏆</span> ' : '' ?><?= e($a['title']) ?></span>
              <?php if ($isShort): ?><span class="short-badge">! <?= $a['short'] === 0 ? 'Geen trainer' : 'Trainer gezocht' ?></span><?php endif; ?>
              <?php if ($a['groups']): ?><span class="week-groups"><?php foreach ($a['groups'] as $g): ?><span><?= group_chip($g) ?></span><?php endforeach; ?></span><?php endif; ?>
              <?php if ($a['location']): ?><span class="week-loc"><?= e($a['location']) ?></span><?php endif; ?>
              <?php foreach ($own[(int) $a['id']] ?? [] as $o): if ($o['absent']): ?><span class="chip warn"><?= e($o['member']['first_name']) ?> afgemeld</span><?php endif; endforeach; ?>
            </a>
          <?php endforeach; ?>
        </section>
      <?php endfor; ?>
    </div>
    <?php if (!$byDay): ?><p class="muted">Geen activiteiten deze week.</p><?php endif; ?>
  <?php elseif (!$upcoming): ?>
    <p class="muted">Geen activiteiten in de komende twee weken.</p>
  <?php else: ?>
    <ul class="list up-list">
      <?php $prevDay = ''; foreach ($upcoming as $a): $d = substr($a['starts_at'], 0, 10); ?>
        <li>
          <span class="up-when"><?= $d !== $prevDay ? e(ucfirst(fmt_date($d))) : '' ?><span class="up-time"><?= e(fmt_time($a['starts_at'])) ?></span></span>
          <a href="<?= e(url('activity', ['id' => $a['id']])) ?>">
            <?php if (!empty($a['mine'])): ?><span class="chip mine-chip"><?= $a['type_code'] === 'TRAINING' ? 'Jij geeft training' : 'Jij gaat mee' ?></span><?php endif; ?>
            <?= $a['type_code'] === 'COMPETITION' ? '<span class="meet-icon" aria-label="Wedstrijd" title="Wedstrijd">🏆</span> ' : '' ?><strong><?= e($a['title']) ?></strong>
          </a>
          <?php if ($a['location']): ?><span class="muted"><?= e($a['location']) ?></span><?php endif; ?>
          <?php if (!empty($own[(int) $a['id']])): $multi = count($own[(int) $a['id']]) > 1; ?>
            <span class="up-absence">
            <?php foreach ($own[(int) $a['id']] as $o): $absActivityId = (int) $a['id']; $absMemberId = (int) $o['member']['id']; $absent = $o['absent']; $absBack = 'dashboard'; $absWho = $multi ? $o['member']['first_name'] : ''; $absLabel = 'Afmelden';
                require __DIR__ . '/_absence.php'; endforeach; ?>
            </span>
          <?php endif; ?>
        </li>
      <?php $prevDay = $d; endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if (Auth::can('activities.write')): ?><p class="card-foot"><a class="btn btn-small btn-ghost" href="<?= e(url('activity_form')) ?>">+ Nieuwe activiteit</a></p><?php endif; ?>
</section>
<?php endif; ?>

<?php $hasAction = $coverShort || $absences || $unfilled || $pendingInvites || $guardianMissing; ?>
<?php if ($coverView || $hasAction): ?>
<section class="card action-card">
  <h2>Waar is actie nodig</h2>
  <?php if ($coverView && !$coverShort): ?>
    <p class="ok-line">✓ Geen tekorten aan trainers in de komende <?= Coverage::SIGNAL_DAYS ?> dagen.<?php if ($coverNext): ?>
      Volgend tekort: <a href="<?= e(url('activity', ['id' => $coverNext['id']])) ?>#trainers"><?= e(fmt_date($coverNext['starts_at'])) ?> – <?= e($coverNext['title']) ?></a>.<?php endif; ?></p>
  <?php endif; ?>

  <?php if ($coverShort): ?>
  <div class="action-block">
    <div class="row-between"><h3><span class="short-badge">!</span> Trainer gezocht <span class="badge"><?= count($coverShort) ?></span></h3><a href="<?= e(url('coverage', ['show' => 'problems', 'view' => 'list'])) ?>">Trainersplanning →</a></div>
    <ul class="list">
    <?php foreach ($coverShort as $c): ?>
      <li><a href="<?= e(url('activity', ['id' => $c['id']])) ?>#trainers"><strong><?= e(fmt_date($c['starts_at'])) ?> <?= e(fmt_time($c['starts_at'])) ?></strong> · <?= e($c['title']) ?></a>
        <span class="muted"><?= e($c['gnames']) ?> · <?= (int) $c['present'] ?> van <?= (int) $c['required'] ?> trainer(s)</span></li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($absences): ?>
  <div class="action-block">
    <h3>Afmeldingen van zwemmers <span class="badge badge-soft"><?= count($absences) ?></span></h3>
    <ul class="list">
    <?php foreach ($absences as $r): ?>
      <li><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> — <a href="<?= e(url('activity', ['id' => $r['activity_id']])) ?>"><?= e($r['title']) ?></a>
        <span class="muted"><?= e(fmt_dt($r['starts_at'])) ?><?= $r['reason'] ? ' · ' . e($r['reason']) : '' ?></span></li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($unfilled): ?>
  <div class="action-block">
    <div class="row-between"><h3>Aanwezigheid nog in te vullen</h3><a href="<?= e(url('attendance_report')) ?>">Rapport →</a></div>
    <ul class="list">
    <?php foreach ($unfilled as $a): ?>
      <li><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><strong><?= e($a['title']) ?></strong></a> <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?></span></li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if ($pendingInvites): ?>
  <p class="action-line"><a href="<?= e(url('users')) ?>"><strong><?= (int) $pendingInvites ?></strong> <?= $pendingInvites === 1 ? 'uitnodiging is' : 'uitnodigingen zijn' ?> nog niet geactiveerd →</a></p>
  <?php endif; ?>
  <?php if ($guardianMissing > 0): ?>
  <p class="action-line"><a href="<?= e(url('members', ['missing' => 1])) ?>"><strong><?= (int) $guardianMissing ?></strong> <?= $guardianMissing === 1 ? 'lid' : 'leden' ?> jonger dan 18 zonder ouder of voogd →</a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($coverView && $coverMine): ?>
<section class="card">
  <div class="row-between"><h2>Jij geeft training (komende 7 dagen)</h2><a href="<?= e(url('coverage', ['show' => 'mine'])) ?>">Mijn planning →</a></div>
  <ul class="list">
  <?php foreach ($coverMine as $c): ?>
    <li><a href="<?= e(url('activity', ['id' => $c['id']])) ?>#trainers"><strong><?= e(fmt_date($c['starts_at'])) ?> <?= e(fmt_time($c['starts_at'])) ?></strong> · <?= e($c['title']) ?></a>
      <span class="muted"><?= e($c['gnames']) ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
