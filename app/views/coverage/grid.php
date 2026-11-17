<?php
$mode = 'grid';
require __DIR__ . '/_head.php';
$cellUrl = fn(int $id) => url('coverage', array_filter(['view' => 'grid', 'w' => $offset ?: '', 'sel' => $id]));
$weekUrl = fn(int $o) => url('coverage', array_filter(['view' => 'grid', 'w' => $o ?: '']));
$state = function (array $a): string {
    if ($a['ends_at'] <= now()) {
        return 'past';
    }
    return $a['missing'] === 0 ? 'ok' : ($a['present'] === 0 ? 'none' : 'short');
};
$rows = array_map(fn($g) => ['key' => (int) $g['id'], 'name' => $g['name'], 'chip' => group_chip($g['name'])], $groups);
$rows[] = ['key' => 'meet', 'name' => 'Wedstrijden', 'chip' => '<span class="meet-icon" aria-hidden="true">🏆</span> Wedstrijden'];
?>
<?php require __DIR__ . '/_signal.php'; ?>

<div class="grid-top">
  <div class="week-nav">
    <a class="week-arrow" data-keep-scroll href="<?= e($weekUrl($offset - 1)) ?>" aria-label="Week vroeger" title="Week vroeger">‹</a>
    <strong class="week-range"><?= e(fmt_week_range($weeks[0])) ?> – <?= e(fmt_week_range(end($weeks))) ?></strong>
    <a class="week-arrow" data-keep-scroll href="<?= e($weekUrl($offset + 1)) ?>" aria-label="Week later" title="Week later">›</a>
    <?php if ($offset !== 0): ?><a class="week-now" data-keep-scroll href="<?= e($weekUrl(0)) ?>">Naar nu</a><?php endif; ?>
  </div>
  <div class="grid-legend small">
    <span><i class="lg lg-ok"></i>Genoeg</span><span><i class="lg lg-short"></i>Te weinig</span><span><i class="lg lg-none"></i>Niemand</span>
  </div>
</div>
<p class="grid-sum"><?= $short ? '<strong class="text-bad">' . (int) $short . ' training(en) of wedstrijd(en)</strong> zonder genoeg trainers in deze ' . count($weeks) . ' weken.' : '<span class="ok-text">✓ Overal genoeg trainers in deze ' . count($weeks) . ' weken.</span>' ?></p>

<div class="grid-layout">
  <div class="grid-main">
    <div class="grid-scroll">
      <table class="plan-grid">
        <thead><tr><th scope="col">Groep</th><?php foreach ($weeks as $wk): ?><th scope="col"<?= $wk === week_monday(0) ? ' class="is-now"' : '' ?>><?= e(fmt_week_range($wk)) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <th scope="row"><?= $r['chip'] ?></th>
            <?php foreach ($weeks as $wk): $items = $cells[$r['key']][$wk] ?? []; ?>
              <td>
                <?php if (!$items): ?><span class="cell-empty" aria-label="niets gepland">—</span><?php endif; ?>
                <?php foreach ($items as $a): $st = $state($a); $names = array_map(fn($t) => explode(' ', $t['name'])[0], array_filter($a['trainers'], fn($t) => !$t['absent'])); ?>
                  <a class="cell cell-<?= $st ?><?= $sel && (int) $sel['id'] === (int) $a['id'] ? ' is-sel' : '' ?>" href="<?= e($cellUrl((int) $a['id'])) ?>" title="<?= e($a['title'] . ' · ' . fmt_dt($a['starts_at'])) ?>">
                    <span class="cell-when"><?= e(fmt_day($a['starts_at'])) ?> <?= e(fmt_time($a['starts_at'])) ?><?= $r['key'] === 'meet' ? ' · ' . e($a['title']) : '' ?></span>
                    <span class="cell-who"><?= $st === 'none' ? 'Niemand' : (int) $a['present'] . '/' . (int) $a['required'] . ($names ? ' · ' . e(implode(', ', $names)) : '') ?></span>
                  </a>
                <?php endforeach; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted">Klik op een training of wedstrijd om trainers in te plannen. Clubactiviteiten staan hier niet: daar zijn geen trainers voor nodig.</p>
  </div>

  <aside class="grid-panel" id="paneel">
    <?php if (!$sel): ?>
      <div class="grid-hint">Kies een training of wedstrijd in het raster. Rood en oranje hebben nog een trainer nodig.</div>
    <?php else: $a = $sel; $past = $a['ends_at'] <= now(); ?>
      <div class="card grid-sel">
        <p class="small muted"><?= e(fmt_day_month($a['starts_at'])) ?> · <?= e(fmt_time($a['starts_at'])) ?>–<?= e(fmt_time($a['ends_at'])) ?><?= $a['gnames'] !== '' ? ' · ' . e($a['gnames']) : '' ?></p>
        <h2><?= $a['type_code'] === 'COMPETITION' ? '🏆 ' : '' ?><a href="<?= e(url('activity', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></h2>
        <p class="<?= $a['missing'] > 0 ? 'text-bad' : 'ok-text' ?>"><strong><?= $a['missing'] > 0 ? 'Nog ' . (int) $a['missing'] . ' nodig' : 'Genoeg trainers' ?></strong> · <?= (int) $a['present'] ?> van <?= (int) $a['required'] ?></p>
        <?php $hidden = csrf_field() . '<input type="hidden" name="activity_id" value="' . (int) $a['id'] . '"><input type="hidden" name="back" value="grid"><input type="hidden" name="w" value="' . (int) $offset . '">'; ?>
        <h3 class="panel-sub">Ingepland</h3>
        <?php if (!$a['trainers']): ?><p class="small muted">Nog niemand.</p><?php endif; ?>
        <ul class="panel-list">
          <?php foreach ($a['trainers'] as $t): ?>
            <li class="<?= $t['absent'] ? 'is-absent' : '' ?>">
              <span><?= e($t['name']) ?><?php if ($t['absent']): ?> <span class="chip <?= $t['auto'] ? 'warn' : 'att-absent' ?>"><?= $t['auto'] ? 'Op wedstrijd' : 'Afgemeld' ?></span><?php if ($t['note'] && !$t['auto']): ?> <span class="small muted"><?= e($t['note']) ?></span><?php endif; ?><?php elseif ($t['default']): ?> <span class="chip off">Vast</span><?php endif; ?></span>
              <?php if (!$past && !$t['auto']): ?>
                <?php if ($t['absent']): ?>
                  <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden ?><input type="hidden" name="do" value="back"><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>"><button class="link-btn" type="submit">Terugzetten</button></form>
                <?php elseif ($t['default']): ?>
                  <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden ?><input type="hidden" name="do" value="absent"><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="replacement" value="none"><button class="link-btn text-bad" type="submit" title="Afmelden; is er dan een trainer te weinig, dan vragen we het aan wie vrij is">Afmelden</button></form>
                <?php else: ?>
                  <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden ?><input type="hidden" name="do" value="remove"><input type="hidden" name="user_id" value="<?= (int) $t['id'] ?>"><button class="link-btn text-bad" type="submit">Weghalen</button></form>
                <?php endif; ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$past): ?>
          <h3 class="panel-sub">Vrij op dat moment</h3>
          <?php if (!$selFree): ?><p class="small muted">Niemand anders is vrij.</p><?php endif; ?>
          <?php foreach ($selFree as $pid => $pname): ?>
            <form method="post" action="<?= e(url('coverage_action')) ?>"><?= $hidden ?><input type="hidden" name="do" value="assign"><input type="hidden" name="user_id" value="<?= (int) $pid ?>">
              <button class="panel-add" type="submit"><span><?= e($pname) ?></span><span class="panel-plus">+ Toevoegen</span></button></form>
          <?php endforeach; ?>
          <?php if ($selBusy): ?><p class="small muted">Niet vrij: <?= e(implode(', ', $selBusy)) ?>.</p><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </aside>
</div>
