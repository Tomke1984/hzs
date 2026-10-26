<div class="row-between">
  <h1><?= group_chip($group['name']) ?> <?php if (!(int) $group['is_active']): ?><span class="chip off">Inactief</span><?php endif; ?></h1>
  <?php if (Auth::can('groups.write')): ?>
    <span class="actions-inline">
      <a class="btn btn-ghost" href="<?= e(url('group_form', ['id' => $group['id']])) ?>">Wijzigen</a>
      <form method="post" action="<?= e(url('group_delete')) ?>" data-confirm="Groep &quot;<?= e($group['name']) ?>&quot; definitief verwijderen? De leden blijven bestaan maar zitten dan in geen groep meer. Trainingsreeksen en activiteiten die enkel voor deze groep zijn, worden ook verwijderd (inclusief aanwezigheden).">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
        <button class="btn btn-ghost" type="submit">Verwijderen</button>
      </form>
    </span>
  <?php endif; ?>
</div>
<p><span class="chip cat-<?= e(strtolower($group['category'] ?? 'SWIM_SCHOOL')) ?>"><?= e(group_category_label($group['category'] ?? null)) ?></span></p>
<?php if ($group['description']): ?><p class="muted"><?= e($group['description']) ?></p><?php endif; ?>
<?php if ($group['capacity'] !== null): ?>
  <p><span class="chip <?= $memberCount > (int) $group['capacity'] ? 'warn' : ($memberCount === (int) $group['capacity'] ? 'off' : 'ok') ?>"><?= (int) $memberCount ?> / <?= (int) $group['capacity'] ?> plaatsen</span>
  <?php if ($memberCount >= (int) $group['capacity']): ?> <span class="muted small">Volzet<?= $memberCount > (int) $group['capacity'] ? ' (over capaciteit)' : '' ?></span><?php endif; ?></p>
<?php endif; ?>
<?php if (Auth::can('groups.write') && $waitlist && ($group['capacity'] === null || $memberCount < (int) $group['capacity'])): ?>
  <div class="alert alert-warning"><strong>Er is plaats vrij</strong><?= $group['capacity'] !== null ? ' (' . ((int) $group['capacity'] - $memberCount) . ' plaats(en))' : '' ?>. Eerste op de wachtlijst: <?= e($waitlist[0]['first_name'] . ' ' . $waitlist[0]['last_name']) ?>. <a href="<?= e(self_url()) ?>#wachtlijst">Naar de wachtlijst</a></div>
<?php endif; ?>

<section class="card">
  <h2>Trainers</h2>
  <?php if (!$trainers): ?>
    <p class="muted">Geen actieve trainer toegewezen.</p>
  <?php else: ?>
    <p><?= e(implode(', ', array_column($trainers, 'display_name'))) ?></p>
  <?php endif; ?>
</section>

<?php if (!empty($group['goals'])): ?>
<section class="card">
  <h2>Doelstellingen</h2>
  <div class="prose"><?= Markup::render((string) $group['goals']) ?></div>
</section>
<?php endif; ?>

<?php if (Auth::can('activities.read')): ?>
<section class="card">
  <h2>Activiteiten</h2>
  <?php if ($series): ?>
    <p class="muted small">Trainingsreeksen</p>
    <ul>
    <?php foreach ($series as $s): ?>
      <li><a href="<?= e(url('series_show', ['id' => $s['id']])) ?>"><?= e($s['title']) ?></a>
        <span class="muted small">(<?= e(fmt_date($s['start_date'])) ?> – <?= e(fmt_date($s['end_date'])) ?>)</span>
        <?php if (!(int) $s['is_active']): ?><span class="chip off">Gestopt</span><?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if (!$activities): ?>
    <p class="muted">Geen komende activiteiten voor deze groep.</p>
  <?php else: ?>
    <p class="muted small">Eerstvolgende activiteiten</p>
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Wanneer</th><th>Activiteit</th><th>Soort</th><th>Plaats</th></tr></thead>
      <tbody>
      <?php foreach ($activities as $a): ?>
        <tr class="<?= $a['status'] === 'CANCELLED' ? 'is-off' : '' ?>">
          <td><?= e(fmt_dt($a['starts_at'])) ?></td>
          <td><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a>
            <?php if ($a['status'] === 'CANCELLED'): ?><span class="chip off">Geschrapt</span><?php endif; ?></td>
          <td class="muted"><?= e($a['type_name']) ?></td>
          <td class="muted"><?= e((string) $a['location']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
  <p><a href="<?= e(url('calendar', ['g' => $group['id']])) ?>">Naar de kalender</a></p>
</section>
<?php endif; ?>

<?php if (Auth::can('activities.write') && $memberships && ($group['category'] ?? '') === 'SWIM_SCHOOL'): ?>
<section class="card" id="trainingsmoment">
  <h2>Trainingsmoment per lid</h2>
  <?php if (count($daySlots) < 2): ?>
    <p class="muted small">Zwemmen niet alle leden van deze groep op dezelfde dag? Maak eerst minstens twee reeksen voor deze groep, één per trainingsmoment (dag + uur); dan kies je hier per lid welke reeks hij volgt.</p>
  <?php else: ?>
  <p class="muted small">Zwemmen niet alle leden van deze groep op hetzelfde moment? Elke reeks is één trainingsmoment (dag en uur). Vink per lid aan welke reeks(en) hij volgt. Niets aangevinkt betekent: nog niet ingedeeld (het lid staat dan op geen aanwezigheidslijst). Aanwezigheidslijsten en de persoonlijke kalender van het lid volgen deze keuze.</p>
  <form method="post" action="<?= e(url('member_days')) ?>">
    <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
    <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Lid</th><?php foreach ($daySlots as $ds): ?><th><?= e($ds['when']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($memberships as $m): ?>
        <tr>
          <td><?= e($m['last_name'] . ' ' . $m['first_name']) ?></td>
          <?php foreach ($daySlots as $ds): ?>
            <td data-label="<?= e($ds['when']) ?>"><input type="checkbox" name="days[<?= (int) $m['id'] ?>][]" value="<?= e($ds['key']) ?>" <?= !empty($assigned[(int) $m['id']][$ds['key']]) ? 'checked' : '' ?> aria-label="<?= e($m['first_name'] . ' ' . $m['last_name'] . ': ' . $ds['when']) ?>"></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div class="actions"><button class="btn" type="submit">Trainingsmomenten opslaan</button></div>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <h2>Leden</h2>
  <?php if (!$memberships): ?>
    <p class="muted">Nog geen leden in deze groep.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><?php if (Auth::can('groups.write') && $targets): ?><th class="check-col"><input type="checkbox" id="select-all-members" title="Alles selecteren" aria-label="Alle leden selecteren"></th><?php endif; ?><th>Naam</th><?php if (Auth::can('groups.write')): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($memberships as $ms): ?>
      <tr>
        <?php if (Auth::can('groups.write') && $targets): ?>
        <td class="check-col"><?php if ($ms['status'] === 'ACTIVE'): ?><input type="checkbox" class="member-pick" form="move-form" name="membership_ids[]" value="<?= (int) $ms['membership_id'] ?>" aria-label="Selecteer <?= e($ms['first_name'] . ' ' . $ms['last_name']) ?>"><?php endif; ?></td>
        <?php endif; ?>
        <td><a href="<?= e(url('member', ['id' => $ms['id']])) ?>"><?= e($ms['last_name']) ?> <?= e($ms['first_name']) ?></a>
          <?php if ($ms['status'] !== 'ACTIVE'): ?><span class="chip off">Inactief</span><?php endif; ?></td>
        <?php if (Auth::can('groups.write')): ?>
        <td class="right">
          <form method="post" action="<?= e(url('membership_end')) ?>" data-confirm="Dit lid uit de groep halen?">
            <?= csrf_field() ?>
            <input type="hidden" name="membership_id" value="<?= (int) $ms['membership_id'] ?>">
            <input type="hidden" name="return" value="group">
            <button class="btn btn-small btn-ghost" type="submit">Uit groep halen</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if (Auth::can('groups.write') && $targets && $memberships): ?>
  <form class="inline-form move-form" id="move-form" data-confirm="De geselecteerde leden naar de gekozen groep verplaatsen?" method="post" action="<?= e(url('membership_move')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="from_group_id" value="<?= (int) $group['id'] ?>">
    <label>Geselecteerde leden verplaatsen naar
      <select name="to_group_id" required>
        <option value="">— kies een groep —</option>
        <?php $lastCat = null; foreach ($targets as $t): ?>
          <?php if ($lastCat !== $t['category']): if ($lastCat !== null) { echo '</optgroup>'; } $lastCat = $t['category']; echo '<optgroup label="' . e(group_category_label($t['category'])) . '">'; endif; ?>
          <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
        <?php endforeach; ?>
        <?php if ($lastCat !== null): ?></optgroup><?php endif; ?>
      </select>
    </label>
    <button class="btn" type="submit">Verplaatsen</button>
  </form>
  <?php endif; ?>

  <?php if ($candidates && (int) $group['is_active']): ?>
  <form class="inline-form" method="post" action="<?= e(url('membership_add')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
    <input type="hidden" name="return" value="group">
    <label>Lid toevoegen <span class="muted small">(leden zonder groep)</span>
      <select name="member_id" required>
        <option value="">— kies een lid —</option>
        <?php foreach ($candidates as $c): ?>
          <?php $blocked = in_array((int) $c['id'], $blockedIds, true); ?>
          <option value="<?= (int) $c['id'] ?>" <?= $blocked ? 'disabled' : '' ?>><?= e($c['last_name']) ?> <?= e($c['first_name']) ?><?= e(member_hint($c)) ?><?= $blocked ? ' — ouder of voogd ontbreekt' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="submit">Toevoegen</button>
  </form>
  <?php endif; ?>
</section>

<?php if (Auth::can('groups.write')): ?>
<section class="card" id="wachtlijst">
  <h2>Wachtlijst</h2>
  <?php if (!$waitlist): ?>
    <p class="muted">Niemand op de wachtlijst voor deze groep.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Lid</th><th>Sinds</th><th>Opmerking</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($waitlist as $i => $w): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><a href="<?= e(url('member', ['id' => $w['member_id']])) ?>"><?= e($w['last_name'] . ' ' . $w['first_name']) ?></a></td>
        <td><?= e(fmt_date($w['created_at'])) ?></td>
        <td class="muted"><?= e((string) $w['note']) ?></td>
        <td class="right">
          <form class="inline-form" method="post" action="<?= e(url('waitlist_enroll')) ?>" data-confirm="Dit lid nu indelen in deze groep?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <label class="check small"><input type="checkbox" name="notify" value="1" checked> verwittig</label>
            <button class="btn btn-small" type="submit">Indelen</button>
          </form>
          <form class="inline-form" method="post" action="<?= e(url('waitlist_remove')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <button class="btn btn-small btn-ghost" type="submit">Weghalen</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php if ((int) $group['is_active'] && $waitCandidates): ?>
  <form class="inline-form" method="post" action="<?= e(url('waitlist_add')) ?>">
    <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
    <label>Lid op de wachtlijst zetten
      <select name="member_id" required><option value="">— kies een lid —</option>
        <?php foreach ($waitCandidates as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['last_name'] . ' ' . $c['first_name']) ?><?= e(member_hint($c)) ?></option><?php endforeach; ?></select>
    </label>
    <label>Opmerking (optioneel) <input name="note" maxlength="200"></label>
    <button class="btn" type="submit">Toevoegen</button>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<p><a href="<?= e(url('groups')) ?>">← Terug naar de groepen</a></p>
