<h1>Auditlog</h1>
<p class="muted">Wie veranderde wat en wanneer. Enkel zichtbaar voor beheerders. <?= number_format($total, 0, ',', '.') ?> regels.</p>
<form class="filters" method="get" action="">
  <select name="entity" aria-label="Onderdeel"><option value="">Alle onderdelen</option>
    <?php foreach ($entities as $x): ?><option value="<?= e($x) ?>" <?= $x === $entity ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select>
  <select name="action" aria-label="Actie"><option value="">Alle acties</option>
    <?php foreach ($actions as $x): ?><option value="<?= e($x) ?>" <?= $x === $action ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select>
  <select name="u" aria-label="Gebruiker"><option value="0">Alle gebruikers</option>
    <?php foreach ($users as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int) $x['id'] === $uid ? 'selected' : '' ?>><?= e($x['display_name']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-small" type="submit">Filteren</button>
  <?php if ($entity !== '' || $action !== '' || $uid): ?><a href="<?= e(url('audit')) ?>">Wis filters</a><?php endif; ?>
</form>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Wanneer</th><th>Wie</th><th>Actie</th><th>Onderdeel</th><th>Nr.</th><th>Detail</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e(fmt_dt($r['created_at'])) ?></td><td><?= e($r['display_name'] ?? '—') ?></td><td><?= e($r['action']) ?></td><td><?= e($r['entity']) ?></td><td><?= $r['entity_id'] !== null ? (int) $r['entity_id'] : '' ?></td><td><?= e($r['detail'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Geen regels.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
<p class="small">Pagina <?= (int) $page ?> van <?= (int) $pages ?>
  <?php $base = array_filter(['entity' => $entity, 'action' => $action, 'u' => $uid ?: '']); ?>
  <?php if ($page > 1): ?>· <a href="<?= e(url('audit', $base + ['p' => $page - 1])) ?>">← Nieuwer</a><?php endif; ?>
  <?php if ($page < $pages): ?>· <a href="<?= e(url('audit', $base + ['p' => $page + 1])) ?>">Ouder →</a><?php endif; ?></p>
<?php endif; ?>
