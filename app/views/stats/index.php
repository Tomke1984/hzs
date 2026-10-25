<?php
$names = ['SITE' => 'Website', 'APP' => 'App (ingelogd)'];
$devices = ['DESKTOP' => 'Computer', 'MOBILE' => 'Gsm', 'TABLET' => 'Tablet'];
$fmt = fn($n) => number_format((int) $n, 0, ',', '.');
$dagnaam = fn($d) => date('j/n', strtotime($d));
?>
<div class="row-between"><h1>Statistieken</h1></div>
<nav class="tabs" aria-label="Periode">
  <?php foreach ([7 => '7 dagen', 30 => '30 dagen', 90 => '90 dagen', 365 => '1 jaar'] as $d => $label): ?>
    <a href="<?= e(url('stats', ['days' => $d])) ?>" <?= $days === $d ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
<p class="muted small">Periode: <?= e(fmt_date($r['from'])) ?> tot en met vandaag.
  <strong>Bezoeken</strong> = unieke bezoekers per dag, opgeteld (dezelfde persoon op twee dagen telt twee keer).
  <strong>Paginaweergaves</strong> = alle bekeken pagina's.
  Er worden geen cookies gebruikt en geen IP-adressen of namen bewaard; bots, beheerders op de website en bezoekers met "Do Not Track" tellen niet mee.</p>

<?php if ($why): ?>
<div class="alert alert-warning" role="status"><strong>Let op bij het testen:</strong>
  <ul><?php foreach ($why as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
  Test door de website te openen in een <strong>privévenster zonder aan te melden</strong> (of op je gsm), en vernieuw daarna dit scherm.</div>
<?php endif; ?>
<p class="muted small"><?= $r['last'] !== '' ? 'Laatste meting: ' . e(fmt_dt($r['last'])) . '.' : 'Er is nog niets geteld sinds de teller actief is.' ?></p>
<?php if ($r['error'] !== ''): ?>
<div class="alert alert-error" role="alert"><strong>De teller kon een bezoek niet wegschrijven.</strong> Laatste fout: <code><?= e($r['error']) ?></code>. Controleer of de database beschrijfbaar is.</div>
<?php endif; ?>
<?php if ($r['skipped']): $lbl = ['DNT' => 'Do Not Track / Global Privacy Control', 'BOT' => 'bots en automatische programma\'s', 'STAFF' => 'beheerders en bestuur op de website']; ?>
<p class="muted small"><strong>Niet meegeteld in deze periode:</strong>
  <?php $parts = []; foreach ($r['skipped'] as $k => $n) { $parts[] = $fmt($n) . '× ' . ($lbl[$k] ?? $k); } echo e(implode(' · ', $parts)); ?>.</p>
<?php endif; ?>
<?php foreach ($r['areas'] as $area => $a):
    $max = max(1, max(array_column($a['series'], 'views')));
    $totalDev = max(1, array_sum(array_column($a['devices'], 'views')));
    $totalRef = $a['direct'] + array_sum(array_column($a['referrers'], 'views')); ?>
<section class="card stats-card" aria-labelledby="st-<?= e(strtolower($area)) ?>">
  <h2 id="st-<?= e(strtolower($area)) ?>"><?= e($names[$area]) ?></h2>
  <?php if (!$a['views']): ?>
    <p class="muted">Nog geen bezoeken in deze periode<?= $r['first'] === '' ? ' (er is nog niets geteld; de teller loopt vanaf nu mee)' : '' ?>.</p>
  <?php else: ?>
  <div class="stat-row">
    <div class="stat-tile"><span class="stat-num"><?= $fmt($a['visitors']) ?></span><span class="muted small">bezoeken</span></div>
    <div class="stat-tile"><span class="stat-num"><?= $fmt($a['views']) ?></span><span class="muted small">paginaweergaves</span></div>
    <div class="stat-tile"><span class="stat-num"><?= e(number_format($a['avg'], 1, ',', '.')) ?></span><span class="muted small">bezoeken per dag</span></div>
    <div class="stat-tile"><span class="stat-num"><?= $fmt($a['today']['visitors']) ?></span><span class="muted small">bezoekers vandaag</span></div>
  </div>
  <?php $n = count($a['series']); $i = 0; ?>
  <svg class="chart-bars" viewBox="0 0 <?= $n * 4 ?> 100" preserveAspectRatio="none" role="img" aria-label="Staafdiagram van de paginaweergaves per dag">
    <?php foreach ($a['series'] as $d => $v): $h = $v['views'] ? max(3, (int) round($v['views'] / $max * 96)) : 1; ?>
      <rect class="chart-bar" x="<?= $i * 4 ?>" y="<?= 100 - $h ?>" width="3" height="<?= $h ?>"><title><?= e($dagnaam($d) . ': ' . $v['views'] . ' weergaven, ' . $v['visitors'] . ' bezoekers') ?></title></rect>
    <?php $i++; endforeach; ?>
  </svg>
  <div class="chart-axis muted small"><span><?= e($dagnaam($r['from'])) ?></span><span>vandaag</span></div>

  <div class="stat-cols">
    <div>
      <h3>Meest bezocht</h3>
      <table class="table">
        <thead><tr><th><?= $area === 'SITE' ? 'Pagina' : 'Scherm' ?></th><th class="num">Weergaven</th></tr></thead>
        <tbody>
        <?php foreach ($a['top'] as $t): ?>
          <tr><td><?= e($area === 'SITE' ? $t['path'] : Stats::appLabel($t['route'])) ?></td><td class="num"><?= $fmt($t['views']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h3>Apparaat</h3>
      <ul class="share-list">
        <?php foreach ($a['devices'] as $dv): $pct = round($dv['views'] / $totalDev * 100); ?>
          <li><span><?= e($devices[$dv['device']] ?? $dv['device']) ?></span><progress class="share-track" max="100" value="<?= $pct ?>"></progress><b><?= $pct ?>%</b></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($area === 'SITE'): ?>
        <h3>Herkomst</h3>
        <ul class="share-list">
          <li><span>Rechtstreeks / intern</span><progress class="share-track" max="100" value="<?= round($a['direct'] / max(1, $totalRef) * 100) ?>"></progress><b><?= $fmt($a['direct']) ?></b></li>
          <?php foreach ($a['referrers'] as $rf): ?>
            <li><span><?= e($rf['referrer']) ?></span><progress class="share-track" max="100" value="<?= round($rf['views'] / max(1, $totalRef) * 100) ?>"></progress><b><?= $fmt($rf['views']) ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
  <details class="stat-days"><summary>Cijfers per dag</summary>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Dag</th><th class="num">Bezoeken</th><th class="num">Weergaven</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($a['series'], true) as $d => $v): ?>
        <tr><td><?= e(fmt_date($d)) ?></td><td class="num"><?= $fmt($v['visitors']) ?></td><td class="num"><?= $fmt($v['views']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </details>
  <?php endif; ?>
</section>
<?php endforeach; ?>
