<div class="row-between">
  <h1><?= e($member['first_name']) ?> <?= e($member['last_name']) ?></h1>
  <?php if (Auth::can('members.write')): ?>
    <a class="btn btn-ghost" href="<?= e(url('member_form', ['id' => $member['id']])) ?>">Wijzigen</a>
  <?php endif; ?>
</div>

<?php if ($guardianMissing): ?>
  <div class="alert alert-warning">
    <strong>Ouder of voogd ontbreekt.</strong> Dit lid is jonger dan 18 en moet minstens één ouder of voogd hebben.
    Zolang die ontbreekt, kan het lid niet aan een groep gekoppeld worden.
  </div>
<?php endif; ?>

<section class="card">
  <dl class="facts">
    <dt>Status</dt><dd><span class="chip <?= $member['status'] === 'ACTIVE' ? 'ok' : 'off' ?>"><?= $member['status'] === 'ACTIVE' ? 'Actief' : 'Inactief' ?></span>
      <?php if ($member['status'] !== 'ACTIVE' && ($member['status_reason'] || $member['status_date'])): ?> <span class="muted"><?= e(trim((string) $member['status_reason'] . ($member['status_date'] ? ' sinds ' . fmt_date($member['status_date']) : ''))) ?></span><?php endif; ?></dd>
    <?php if ($member['birth_date']): ?><dt>Geboortedatum</dt><dd><?= e(fmt_date($member['birth_date'])) ?></dd><?php endif; ?>
    <?php if ($showContact): ?>
      <dt>E-mail</dt><dd><?= e($member['email']) ?: '—' ?></dd>
      <dt>Telefoon</dt><dd><?= e($member['phone']) ?: '—' ?></dd>
      <dt>Adres</dt><dd><?= e(trim(($member['street'] ?? '') . ', ' . trim(($member['postal_code'] ?? '') . ' ' . ($member['city'] ?? '')), ', ')) ?: '—' ?></dd>
      <dt>Foto’s op website</dt><dd><?= !empty($member['photo_consent']) ? '<span class="chip ok">Toestemming</span>' : '<span class="chip off">Geen toestemming</span>' ?></dd>
    <?php endif; ?>
    <?php if ($showMedical && ($member['emergency_name'] || $member['emergency_phone'] || $member['medical_note'])): ?>
      <dt>Noodcontact</dt><dd><?= e(trim((string) $member['emergency_name'] . ' ' . (string) $member['emergency_phone'])) ?: '—' ?></dd>
      <dt>Medisch</dt><dd><?= e((string) $member['medical_note']) ?: '—' ?></dd>
    <?php endif; ?>
  </dl>
</section>

<?php if ($dues): ?>
<section class="card" id="lidgeld">
  <h2>Lidgeld</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Seizoen</th><th>Bedrag</th><th>Status</th><th>Mededeling</th></tr></thead>
    <tbody>
    <?php foreach ($dues as $d): ?>
      <tr><td><?= e($d['season_name']) ?></td><td><?= Fees::money((int) $d['amount_cents']) ?></td>
        <td><span class="chip <?= $d['status'] === 'PAID' ? 'ok' : ($d['status'] === 'OPEN' ? 'warn' : 'off') ?>"><?= e(Fees::statusLabel($d['status'])) ?></span><?= $d['paid_at'] ? ' <span class="muted small">' . e(fmt_date($d['paid_at'])) . '</span>' : '' ?></td>
        <td><?php if ($d['status'] === 'OPEN'): ?><code><?= e(Fees::reference((int) $d['id'])) ?></code><?= $d['due_date'] ? ' <span class="muted small">voor ' . e(fmt_date($d['due_date'])) . '</span>' : '' ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (Fees::payInfo() !== '' && array_filter($dues, fn($d) => $d['status'] === 'OPEN')): ?><p class="small muted"><?= e(Fees::payInfo()) ?>. Vermeld de gestructureerde mededeling bij je betaling.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($bests || $canResults): ?>
<section class="card" id="resultaten">
  <h2>Wedstrijdresultaten</h2>
  <?php if (!$bests): ?>
    <p class="muted">Nog geen resultaten.</p>
  <?php else: ?>
    <p class="muted small">Persoonlijke records</p>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Afstand</th><th>Beste tijd</th><th>Gezwommen op</th></tr></thead>
      <tbody>
      <?php foreach ($bests as $ev => $b): ?><tr><td><?= e($ev) ?></td><td><strong><?= e(Results::fmt((int) $b['time_cs'])) ?></strong></td><td><?= e(fmt_date($b['swum_on'])) ?></td></tr><?php endforeach; ?>
      </tbody>
    </table></div>
    <details><summary class="small">Alle resultaten (<?= count($results) ?>)</summary>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Datum</th><th>Afstand</th><th>Tijd</th><th>Wedstrijd</th><?php if ($canResults): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr><td><?= e(fmt_date($r['swum_on'])) ?></td><td><?= e($r['event']) ?></td><td><?= e(Results::fmt((int) $r['time_cs'])) ?><?= (int) $r['time_cs'] === (int) ($bests[$r['event']]['time_cs'] ?? -1) ? ' <span class="chip ok">PR</span>' : '' ?></td>
            <td class="muted small"><?= $r['activity_id'] ? '<a href="' . e(url('activity', ['id' => $r['activity_id']])) . '">' . e((string) $r['activity_title']) . '</a>' : e((string) $r['note']) ?></td>
            <?php if ($canResults): ?><td class="right"><form method="post" action="<?= e(url('result_delete')) ?>" data-confirm="Dit resultaat verwijderen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Verwijderen</button></form></td><?php endif; ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </details>
  <?php endif; ?>
  <?php if ($canResults): ?>
  <form class="inline-form" method="post" action="<?= e(url('result_save')) ?>">
    <?= csrf_field() ?><input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
    <label>Afstand <input name="event" list="events" maxlength="40" required placeholder="bv. 100 vrij"></label>
    <label>Tijd <input name="time" required placeholder="1:05,32" pattern="[0-9:.,]+" maxlength="9"></label>
    <label>Datum <input type="date" name="swum_on" value="<?= e(today()) ?>" max="<?= e(today()) ?>" required></label>
    <label>Opmerking <input name="note" maxlength="120" placeholder="bv. clubkampioenschap"></label>
    <button class="btn" type="submit">Resultaat toevoegen</button>
    <datalist id="events"><?php foreach (Results::EVENTS as $ev): ?><option value="<?= e($ev) ?>"><?php endforeach; ?></datalist>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($showContact): ?>
<?php $collapseParents = !$isMinor && !$parents; ?>
<?php if ($collapseParents): ?>
<details class="card collapsible">
  <summary class="btn btn-ghost btn-small">+ Ouder of voogd toevoegen</summary>
  <p class="muted small">Dit lid is 18 of ouder. Een ouder of voogd is niet verplicht.</p>
  <div class="row-between">
    <h2>Ouders / voogden</h2>
    <?php if (Auth::can('users.manage')): ?>
      <a class="btn btn-small btn-ghost" href="<?= e(url('users')) ?>">Gebruikers beheren</a>
    <?php endif; ?>
  </div>
  <?php if (!$parents): ?>
    <p class="muted">Nog geen ouder of voogd gekoppeld aan dit lid.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Naam</th><th>Relatie</th><th>E-mail</th><th>Account</th></tr></thead>
    <tbody>
    <?php foreach ($parents as $p): ?>
      <tr>
        <td><?= Auth::can('users.manage')
              ? '<a href="' . e(url('user_form', ['id' => $p['user_id']])) . '">' . e($p['display_name']) . '</a>'
              : e($p['display_name']) ?></td>
        <td><?php if (Auth::can('users.manage')): ?>
          <div class="rel-edit">
          <span class="rel-text"><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?></span>
          <button type="button" class="btn btn-small btn-ghost rel-edit-btn" hidden>Bewerken</button>
          <form class="inline-form rel-form" method="post" action="<?= e(url('user_child_relation')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="link_id" value="<?= (int) $p['link_id'] ?>">
            <input type="hidden" name="return" value="member">
            <input name="relationship" value="<?= e($p['relationship']) ?>" maxlength="40" placeholder="bv. moeder, vader, voogd" aria-label="Relatie">
            <button class="btn btn-small" type="submit">Opslaan</button>
            <button type="button" class="btn btn-small btn-ghost rel-cancel" hidden>Annuleren</button>
          </form>
          </div>
          <?php else: ?><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?><?php endif; ?></td>
        <td><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></td>
        <td><span class="chip <?= (int) $p['is_active'] ? 'ok' : 'off' ?>"><?= (int) $p['is_active'] ? 'Actief' : 'Inactief' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('users.manage')): ?>
    <?php if ($parentAccounts): ?>
    <form class="inline-form" method="post" action="<?= e(url('user_child_add')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <input type="hidden" name="return" value="member">
      <label>Ouder of voogd koppelen
        <select name="user_id" required>
          <option value="">— kies een ouderaccount —</option>
          <?php foreach ($parentAccounts as $pa): ?>
            <option value="<?= (int) $pa['id'] ?>"><?= e($pa['display_name']) ?> (<?= e($pa['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Koppelen</button>
    </form>
    <?php endif; ?>
    <form class="inline-form" method="post" action="<?= e(url('invite_parent')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <label>Of nodig een ouder of voogd uit per e-mail
        <input type="email" name="email" required maxlength="190" placeholder="e-mailadres van de ouder">
      </label>
      <label>Naam (optioneel)
        <input name="display_name" maxlength="120" placeholder="de ouder kan dit zelf aanpassen">
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Uitnodigen</button>
    </form>
    <p class="muted small">De ouder krijgt een mail met een persoonlijke link om zelf een wachtwoord te kiezen en is meteen gekoppeld aan dit lid.</p>
  <?php endif; ?>
</details>
<?php else: ?>
<section class="card">
  <div class="row-between">
    <h2>Ouders / voogden</h2>
    <?php if (Auth::can('users.manage')): ?>
      <a class="btn btn-small btn-ghost" href="<?= e(url('users')) ?>">Gebruikers beheren</a>
    <?php endif; ?>
  </div>
  <?php if (!$parents): ?>
    <p class="muted">Nog geen ouder of voogd gekoppeld aan dit lid.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>Naam</th><th>Relatie</th><th>E-mail</th><th>Account</th></tr></thead>
    <tbody>
    <?php foreach ($parents as $p): ?>
      <tr>
        <td><?= Auth::can('users.manage')
              ? '<a href="' . e(url('user_form', ['id' => $p['user_id']])) . '">' . e($p['display_name']) . '</a>'
              : e($p['display_name']) ?></td>
        <td><?php if (Auth::can('users.manage')): ?>
          <div class="rel-edit">
          <span class="rel-text"><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?></span>
          <button type="button" class="btn btn-small btn-ghost rel-edit-btn" hidden>Bewerken</button>
          <form class="inline-form rel-form" method="post" action="<?= e(url('user_child_relation')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="link_id" value="<?= (int) $p['link_id'] ?>">
            <input type="hidden" name="return" value="member">
            <input name="relationship" value="<?= e($p['relationship']) ?>" maxlength="40" placeholder="bv. moeder, vader, voogd" aria-label="Relatie">
            <button class="btn btn-small" type="submit">Opslaan</button>
            <button type="button" class="btn btn-small btn-ghost rel-cancel" hidden>Annuleren</button>
          </form>
          </div>
          <?php else: ?><?= e($p['relationship']) ?: '<span class="muted">—</span>' ?><?php endif; ?></td>
        <td><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></td>
        <td><span class="chip <?= (int) $p['is_active'] ? 'ok' : 'off' ?>"><?= (int) $p['is_active'] ? 'Actief' : 'Inactief' ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('users.manage')): ?>
    <?php if ($parentAccounts): ?>
    <form class="inline-form" method="post" action="<?= e(url('user_child_add')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <input type="hidden" name="return" value="member">
      <label>Ouder of voogd koppelen
        <select name="user_id" required>
          <option value="">— kies een ouderaccount —</option>
          <?php foreach ($parentAccounts as $pa): ?>
            <option value="<?= (int) $pa['id'] ?>"><?= e($pa['display_name']) ?> (<?= e($pa['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Koppelen</button>
    </form>
    <?php endif; ?>
    <form class="inline-form" method="post" action="<?= e(url('invite_parent')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
      <label>Of nodig een ouder of voogd uit per e-mail
        <input type="email" name="email" required maxlength="190" placeholder="e-mailadres van de ouder">
      </label>
      <label>Naam (optioneel)
        <input name="display_name" maxlength="120" placeholder="de ouder kan dit zelf aanpassen">
      </label>
      <label>Relatie (optioneel)
        <input name="relationship" maxlength="40" placeholder="bv. moeder, vader, voogd">
      </label>
      <button class="btn" type="submit">Uitnodigen</button>
    </form>
    <p class="muted small">De ouder krijgt een mail met een persoonlijke link om zelf een wachtwoord te kiezen en is meteen gekoppeld aan dit lid.</p>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if (Auth::can('users.manage') && !Guardianship::isMinor($member['birth_date'] ?? null) || (Auth::can('users.manage') && $account)): ?>
<section class="card">
  <h2>Eigen account</h2>
  <?php if ($account): ?>
    <p>Dit lid kan aanmelden met <strong><?= e($account['email']) ?></strong>
      <span class="chip <?= (int) $account['is_active'] ? 'ok' : 'off' ?>"><?= (int) $account['is_active'] ? 'Actief' : 'Inactief' ?></span></p>
    <p><a class="btn btn-small btn-ghost" href="<?= e(url('user_form', ['id' => $account['id']])) ?>">Account beheren</a></p>
  <?php else: ?>
    <p class="muted">Dit lid heeft geen eigen login. Maak een account aan als het lid zelf wil aanmelden om de eigen kalender en groepen te zien.</p>
    <div class="actions">
      <?php if (filter_var((string) $member['email'], FILTER_VALIDATE_EMAIL)): ?>
      <form method="post" action="<?= e(url('invite_member')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
        <button class="btn btn-small" type="submit">Uitnodigen per e-mail (<?= e($member['email']) ?>)</button>
      </form>
      <?php endif; ?>
      <a class="btn btn-small btn-ghost" href="<?= e(url('user_form', ['member_id' => $member['id']])) ?>">Account zelf aanmaken</a>
    </div>
  <?php endif; ?>
</section>
<?php elseif (Auth::can('users.manage')): ?>
<p class="muted small">Dit lid is jonger dan 18: de ouder of voogd meldt aan. Een eigen account kan later, via de ouder.</p>
<?php endif; ?>

<section class="card">
  <h2>Groep</h2>
  <?php foreach ($waitlists as $wl): ?><p class="small"><span class="chip warn">Wachtlijst</span> <a href="<?= e(url('group', ['id' => $wl['id']])) ?>#wachtlijst"><?= e($wl['name']) ?></a></p><?php endforeach; ?>
  <?php $cur = $memberships[0] ?? null; ?>
  <?php if (!$cur): ?>
    <p class="muted">Nog niet in een groep.</p>
  <?php else: ?>
    <p><?= Auth::can('groups.read') && Scope::canSeeGroup((int) $cur['group_id'])
          ? '<a href="' . e(url('group', ['id' => $cur['group_id']])) . '">' . e($cur['group_name']) . '</a>'
          : e($cur['group_name']) ?></p>
    <p class="muted small">Trainingsmoment: <?= $myDays ? e(implode('; ', $myDays)) : (($cur['group_category'] ?? '') === 'SWIM_SCHOOL' ? 'nog niet ingedeeld' : 'alle trainingen van de groep') ?>
      <?php if (Auth::can('activities.write')): ?>· <a href="<?= e(url('group', ['id' => $cur['group_id']])) ?>#trainingsmoment">aanpassen</a><?php endif; ?></p>
  <?php endif; ?>

  <?php if ($availableGroups && $member['status'] === 'ACTIVE'): ?>
  <form class="inline-form" method="post" action="<?= e(url('member_group')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
    <label><?= $cur ? 'Groep wijzigen' : 'In groep plaatsen' ?>
      <select name="group_id">
        <option value="0">— geen groep —</option>
        <?php foreach ($availableGroups as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= $cur && (int) $cur['group_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="submit">Opslaan</button>
  </form>
  <?php endif; ?>
</section>

<p><a href="<?= e(url('members')) ?>">← Terug naar de lijst</a></p>
