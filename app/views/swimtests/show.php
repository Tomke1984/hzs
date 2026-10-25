<?php $age = (new DateTimeImmutable($r['child_birth_date']))->diff(new DateTimeImmutable(today()))->y; $locked = $r['status'] === 'ENROLLED'; ?>
<div class="row-between">
  <h1><?= e($r['child_first_name']) ?> <?= e($r['child_last_name']) ?></h1>
  <span class="chip <?= $r['status'] === 'WAITING' ? 'warn' : ($r['status'] === 'DECLINED' ? 'off' : 'ok') ?>"><?= e(SwimTestController::STATUSES[$r['status']]) ?></span>
</div>
<p class="muted"><a href="<?= e(url('swimtests', ['s' => $r['status']])) ?>">← Naar de lijst</a><?= $position ? ' · plaats ' . (int) $position . ' op de wachtlijst' : '' ?></p>

<section class="card">
  <h2>Kind</h2>
  <dl class="facts">
    <dt>Geboortedatum</dt><dd><?= e(fmt_date($r['child_birth_date'])) ?> (<?= (int) $age ?> jaar)</dd>
    <dt>Diploma</dt><dd><?= e(SwimTestController::DIPLOMAS[$r['diploma']] ?? $r['diploma']) ?></dd>
    <?php if ($r['experience']): ?><dt>Zwemervaring</dt><dd><?= e($r['experience']) ?></dd><?php endif; ?>
    <dt>Beschikbaar</dt><dd><?= $r['availability'] ? e($r['availability']) : '<span class="muted">niet opgegeven</span>' ?></dd>
    <?php if ($r['remarks']): ?><dt>Opmerking</dt><dd><?= nl2br(e($r['remarks'])) ?></dd><?php endif; ?>
  </dl>
</section>

<section class="card">
  <h2>Contact</h2>
  <dl class="facts">
    <dt>Ouder of voogd</dt><dd><?= e($r['contact_name']) ?><?= $r['relationship'] ? ' (' . e($r['relationship']) . ')' : '' ?></dd>
    <dt>E-mail</dt><dd><a href="mailto:<?= e($r['contact_email']) ?>"><?= e($r['contact_email']) ?></a> <span class="chip ok">bevestigd</span></dd>
    <dt>Telefoon</dt><dd><?= e($r['contact_phone']) ?></dd>
    <dt>Adres</dt><dd><?= e($r['street']) ?>, <?= e($r['postal_code']) ?> <?= e($r['city']) ?></dd>
    <dt>Aangemeld</dt><dd><?= e(fmt_dt($r['confirmed_at'] ?: $r['created_at'])) ?></dd>
    <?php if ($r['invite_info']): ?><dt>Uitnodiging</dt><dd><?= e($r['invite_info']) ?></dd><?php endif; ?>
    <?php if ($r['decline_reason']): ?><dt>Reden weigering</dt><dd><?= e($r['decline_reason']) ?></dd><?php endif; ?>
    <?php if ($r['status_changed_at']): ?><dt>Laatste wijziging</dt><dd><?= e(fmt_dt($r['status_changed_at'])) ?><?= $by ? ' door ' . e($by) : '' ?></dd><?php endif; ?>
  </dl>
  <?php if ($siblings): ?>
    <p class="muted small">Zelfde e-mailadres:
      <?php foreach ($siblings as $sb): ?><a href="<?= e(url('swimtest_show', ['id' => $sb['id']])) ?>"><?= e($sb['child_first_name'] . ' ' . $sb['child_last_name']) ?></a> (<?= e(mb_strtolower(SwimTestController::STATUSES[$sb['status']])) ?>) <?php endforeach; ?></p>
  <?php endif; ?>
</section>

<?php if ($locked): ?>
  <section class="card">
    <h2>Ingeschreven</h2>
    <p>Dit kind is doorgezet naar een inschrijving.<?php if ($r['registration_id']): ?> <a href="<?= e(url('registration', ['id' => $r['registration_id']])) ?>">Naar de inschrijving</a><?php endif; ?></p>
  </section>
<?php else: ?>
<section class="card">
  <h2>Status</h2>
  <form class="form" method="post" action="<?= e(url('swimtest_status')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
    <label>Nieuwe status
      <select name="status" id="st-status">
        <?php foreach (SwimTestController::STATUSES as $code => $label): if ($code === 'ENROLLED') { continue; } ?>
          <option value="<?= e($code) ?>" <?= $r['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Wanneer en waar is de zwemtest? (voor de uitnodiging)
      <input name="invite_info" maxlength="300" value="<?= e((string) $r['invite_info']) ?>" placeholder="bv. zaterdag 8 november om 9u, Zwembad Alverberg">
    </label>
    <label>Reden (bij weigeren, optioneel)
      <input name="decline_reason" maxlength="500" value="<?= e((string) $r['decline_reason']) ?>">
    </label>
    <label class="check"><input type="checkbox" name="notify" value="1"> Verwittig de ouder per mail (bij “Uitgenodigd” of “Geweigerd”)</label>
    <div class="actions"><button class="btn" type="submit">Status opslaan</button></div>
  </form>
</section>

<?php if ($r['status'] !== 'DECLINED'): ?>
<section class="card">
  <h2>Inschrijven als lid</h2>
  <p class="muted">Na de zwemtest zet je het kind door naar een gewone inschrijving. Daar kies je de groep en keur je goed; dan worden het lid en het account van de ouder aangemaakt.</p>
  <form method="post" action="<?= e(url('swimtest_enroll')) ?>" data-confirm="Dit kind doorzetten naar een inschrijving?">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
    <button class="btn" type="submit">Als lid inschrijven</button>
  </form>
</section>
<?php endif; ?>
<?php endif; ?>

<section class="card">
  <h2>Interne notitie</h2>
  <form class="form" method="post" action="<?= e(url('swimtest_note')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
    <label>Enkel zichtbaar voor het bestuur
      <textarea name="internal_note" rows="3" maxlength="500"><?= e((string) $r['internal_note']) ?></textarea>
    </label>
    <div class="actions"><button class="btn btn-ghost" type="submit">Notitie bewaren</button></div>
  </form>
</section>
