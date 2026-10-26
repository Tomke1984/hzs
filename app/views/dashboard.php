<h1>Welkom, <?= e(Auth::user()['display_name']) ?></h1>

<?php if ($noTfa): ?>
<div class="alert alert-info">Je account heeft toegang tot persoonsgegevens van minderjarigen. <a href="<?= e(url('account')) ?>#2fa">Zet tweestapsverificatie aan →</a></div>
<?php endif; ?>

<?php if ($openDues): ?>
<div class="alert alert-warning">
  <strong>Lidgeld nog te betalen</strong>
  <ul class="list">
  <?php foreach ($openDues as $d): ?>
    <li><?= e($d['first_name']) ?> (<?= e($d['season_name']) ?>): <?= Fees::money((int) $d['amount_cents']) ?> · mededeling <code><?= e(Fees::reference((int) $d['id'])) ?></code></li>
  <?php endforeach; ?>
  </ul>
  <?php if (Fees::payInfo() !== ''): ?><span class="small"><?= e(Fees::payInfo()) ?></span><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($meetTodo): ?>
<section class="card">
  <h2>Wedstrijden: laat weten of je meedoet</h2>
  <ul class="list">
  <?php foreach ($meetTodo as $t): ?>
    <li><a href="<?= e(url('activity', ['id' => $t['activity']['id']])) ?>#deelname"><strong><?= e($t['activity']['title']) ?></strong></a>
      <span class="muted"><?= e(fmt_dt($t['activity']['starts_at'])) ?> · antwoord nog nodig voor <?= e(implode(', ', $t['names'])) ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($unfilled): ?>
<section class="card">
  <div class="row-between"><h2>Aanwezigheid nog in te vullen</h2><a href="<?= e(url('attendance_report')) ?>">Rapport →</a></div>
  <ul class="list">
  <?php foreach ($unfilled as $a): ?>
    <li><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><strong><?= e($a['title']) ?></strong></a> <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($absences): ?>
<section class="card">
  <h2>Afmeldingen voor de komende 2 weken</h2>
  <ul class="list">
  <?php foreach ($absences as $r): ?>
    <li><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong> — <a href="<?= e(url('activity', ['id' => $r['activity_id']])) ?>"><?= e($r['title']) ?></a>
      <span class="muted"><?= e(fmt_dt($r['starts_at'])) ?><?= $r['reason'] ? ' · ' . e($r['reason']) : '' ?></span></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($office): ?>
<section class="card">
  <h2>Wacht op het bestuur</h2>
  <ul class="list">
    <?php if (isset($office['pending']) && $office['pending'][0]): ?><li><a href="<?= e(url('registrations')) ?>"><strong><?= (int) $office['pending'][0] ?> inschrijving(en)</strong></a> <span class="muted">oudste sinds <?= e(fmt_date((string) $office['pending'][1])) ?></span></li><?php endif; ?>
    <?php if (isset($office['messages']) && $office['messages'][0]): ?><li><a href="<?= e(url('messages')) ?>"><strong><?= (int) $office['messages'][0] ?> bericht(en)</strong></a> <span class="muted">onbeantwoord, oudste van <?= e(fmt_date((string) $office['messages'][1])) ?></span></li><?php endif; ?>
    <?php if (isset($office['swimtest']) && ($office['swimtest'][0] || $office['swimtest'][1])): ?><li><a href="<?= e(url('swimtests')) ?>"><strong><?= (int) $office['swimtest'][0] ?> op de wachtlijst voor de zwemtest</strong></a><?= $office['swimtest'][1] ? ' <span class="chip warn">' . (int) $office['swimtest'][1] . ' met opvolging</span>' : '' ?></li><?php endif; ?>
    <?php if (isset($office['dues']) && $office['dues'][0]): ?><li><a href="<?= e(url('fees')) ?>"><strong><?= (int) $office['dues'][0] ?> open rekeningen</strong></a> <span class="muted"><?= e(Fees::money($office['dues'][1])) ?> te ontvangen</span></li><?php endif; ?>
    <?php foreach ($office['places'] ?? [] as $g): ?><li><a href="<?= e(url('group', ['id' => $g['id']])) ?>#wachtlijst"><strong><?= e($g['name']) ?></strong></a> <span class="muted"><?= (int) $g['n'] ?>/<?= (int) $g['capacity'] ?> plaatsen<?= $g['waiting'] ? ', ' . (int) $g['waiting'] . ' op de wachtlijst' : '' ?></span></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!empty($newRegistrations)): ?>
<div class="alert alert-warning">
  <strong><?= (int) $newRegistrations ?> nieuwe <?= $newRegistrations === 1 ? 'inschrijving' : 'inschrijvingen' ?></strong> om te beoordelen.
  <a href="<?= e(url('registrations')) ?>">Naar de inschrijvingen →</a>
</div>
<?php endif; ?>

<?php if ($guardianMissing > 0): ?>
<div class="alert alert-warning">
  <strong><?= (int) $guardianMissing ?> <?= $guardianMissing === 1 ? 'lid' : 'leden' ?> jonger dan 18</strong> zonder ouder of voogd.
  <a href="<?= e(url('members', ['missing' => 1])) ?>">Bekijk de lijst →</a>
</div>
<?php endif; ?>

<?php if ($stats): ?>
<div class="stats">
  <div class="stat"><strong><?= $stats['members'] ?></strong><span>actieve leden</span></div>
  <div class="stat"><strong><?= $stats['groups'] ?></strong><span>actieve groepen</span></div>
  <div class="stat"><strong><?= $stats['activities'] ?></strong><span>geplande activiteiten</span></div>
</div>
<?php endif; ?>

<?php if (Auth::can('activities.read')): ?>
<section class="card">
  <div class="row-between">
    <h2>Eerstvolgende activiteiten</h2>
    <a href="<?= e(url('calendar')) ?>">Hele kalender →</a>
  </div>
  <?php if (!$upcoming): ?>
    <p class="muted">Geen geplande activiteiten.</p>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($upcoming as $a): ?>
        <li>
          <a href="<?= e(url('activity', ['id' => $a['id']])) ?>">
            <span class="chip type-<?= e(strtolower($a['type_code'])) ?>"><?= e($a['type_name']) ?></span>
            <strong><?= e($a['title']) ?></strong>
          </a>
          <span class="muted"><?= e(fmt_dt($a['starts_at'])) ?><?= $a['location'] ? ' · ' . e($a['location']) : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (Auth::can('activities.write')): ?>
  <p><a class="btn" href="<?= e(url('activity_form')) ?>">+ Nieuwe activiteit</a></p>
<?php endif; ?>
