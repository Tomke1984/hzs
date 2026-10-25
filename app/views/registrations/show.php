<div class="row-between">
  <h1>Inschrijving</h1>
  <?php if ($reg['status'] === 'PENDING'): ?><span class="chip warn">Te beoordelen</span>
  <?php elseif ($reg['status'] === 'APPROVED'): ?><span class="chip ok">Goedgekeurd</span>
  <?php else: ?><span class="chip off">Afgewezen</span><?php endif; ?>
</div>

<section class="card">
  <h2>Contact</h2>
  <dl class="facts">
    <dt><?= $reg['applicant_type'] === 'SELF' ? 'Zwemmer (zelf)' : 'Ouder of voogd' ?></dt><dd><?= e($reg['contact_name']) ?><?= $reg['relationship'] ? ' (' . e($reg['relationship']) . ')' : '' ?></dd>
    <dt>E-mail</dt><dd><a href="mailto:<?= e($reg['contact_email']) ?>"><?= e($reg['contact_email']) ?></a> <span class="chip ok">bevestigd</span></dd>
    <dt>Telefoon</dt><dd><?= e($reg['contact_phone']) ?></dd>
    <dt>Adres</dt><dd><?= e($reg['street']) ?>, <?= e($reg['postal_code']) ?> <?= e($reg['city']) ?></dd>
    <?php if ($reg['remarks']): ?><dt>Opmerking</dt><dd><?= nl2br(e($reg['remarks'])) ?></dd><?php endif; ?>
    <dt>Ingediend</dt><dd><?= e(fmt_dt($reg['created_at'])) ?> · akkoord met reglement: <?= e(fmt_dt($reg['consent_at'])) ?></dd>
    <?php if ($reg['decided_at']): ?><dt>Beslissing</dt><dd><?= e(fmt_dt($reg['decided_at'])) ?><?= $decider ? ' door ' . e($decider) : '' ?></dd><?php endif; ?>
    <?php if ($reg['reject_reason']): ?><dt>Reden afwijzing</dt><dd><?= nl2br(e($reg['reject_reason'])) ?></dd><?php endif; ?>
  </dl>
  <?php if ($reg['status'] === 'PENDING' && $account): ?>
    <p class="alert alert-warning">Dit e-mailadres heeft al een account<?= (int) $account['is_active'] ? '. Bij goedkeuring worden de leden aan dat account gekoppeld, er wordt geen tweede account gemaakt.' : ', maar het is <strong>inactief</strong>. Activeer het eerst bij Gebruikers, anders kan deze inschrijving niet goedgekeurd worden.' ?></p>
  <?php endif; ?>
</section>

<?php if ($reg['status'] === 'PENDING'): ?>
<form method="post" action="<?= e(url('registration_approve')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
<?php endif; ?>
<section class="card">
  <h2><?= $reg['applicant_type'] === 'SELF' ? 'Zwemmer' : 'Kinderen' ?></h2>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Naam</th><th>Geboortedatum</th><th><?= $reg['status'] === 'PENDING' ? 'Groep toewijzen' : 'Lid' ?></th></tr></thead>
    <tbody>
    <?php foreach ($persons as $p): ?>
      <tr>
        <td><?= e($p['first_name']) ?> <?= e($p['last_name']) ?>
          <?php foreach ($dups[$p['id']] ?? [] as $d): ?>
            <br><span class="chip warn">Mogelijk al lid</span> <a class="small" href="<?= e(url('member', ['id' => $d['id']])) ?>">bekijk</a>
          <?php endforeach; ?></td>
        <td><?= e(fmt_date($p['birth_date'])) ?> <?php if (Guardianship::isMinor($p['birth_date'])): ?><span class="chip">&lt; 18</span><?php endif; ?></td>
        <td>
          <?php if ($reg['status'] === 'PENDING'): ?>
            <select name="group[<?= (int) $p['id'] ?>]" aria-label="Groep voor <?= e($p['first_name']) ?>">
              <option value="0">Later toewijzen</option>
              <?php $last = null; foreach ($groups as $g): ?>
                <?php if ($last !== $g['category']): if ($last !== null) { echo '</optgroup>'; } $last = $g['category']; ?><optgroup label="<?= e(group_category_label($g['category'])) ?>"><?php endif; ?>
                <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
              <?php endforeach; if ($last !== null) { echo '</optgroup>'; } ?>
            </select>
          <?php elseif ($p['member_id']): ?>
            <a href="<?= e(url('member', ['id' => $p['member_id']])) ?>">Lidprofiel</a>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php if ($reg['status'] === 'PENDING'): ?>
  <p class="muted small">Bij goedkeuring worden de leden aangemaakt<?= $reg['applicant_type'] === 'SELF' ? '' : ', de ouder als ouder of voogd gekoppeld' ?> en krijgt <?= e($reg['contact_email']) ?> een uitnodiging om een account te activeren<?= $account ? ' (bestaand account: enkel een bevestiging)' : '' ?>.</p>
  <div class="actions"><button class="btn" type="submit">Goedkeuren en uitnodigen</button></div>
<?php endif; ?>
</section>
<?php if ($reg['status'] === 'PENDING'): ?>
</form>

<section class="card">
  <h2>Afwijzen</h2>
  <form method="post" action="<?= e(url('registration_reject')) ?>" data-confirm="Deze inschrijving afwijzen? De inschrijver krijgt een mail.">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
    <label>Reden (optioneel, wordt in de mail vermeld)
      <textarea name="reason" rows="2" maxlength="500"></textarea>
    </label>
    <button class="btn btn-ghost" type="submit">Afwijzen</button>
  </form>
</section>
<?php endif; ?>

<?php if ($pendingAccounts): ?>
<section class="card">
  <h2>Account nog niet geactiveerd</h2>
  <p class="muted"><?= e(implode(', ', array_column($pendingAccounts, 'email'))) ?> heeft de uitnodiging nog niet gebruikt.</p>
  <form method="post" action="<?= e(url('registration_resend')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $reg['id'] ?>">
    <button class="btn btn-small" type="submit">Uitnodiging opnieuw versturen</button>
  </form>
</section>
<?php endif; ?>

<p><a href="<?= e(url('registrations', ['tab' => $reg['status'] === 'PENDING' ? 'new' : 'done'])) ?>">← Terug naar de lijst</a></p>
