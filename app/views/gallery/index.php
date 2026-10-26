<div class="row-between"><h1>Fotogalerij</h1></div>
<p class="muted">Foto's komen pas online als het album gepubliceerd is en bij elke foto de toestemming in orde is: getagde leden moeten toestemming voor foto's hebben (op hun ledenfiche), zonder tags bevestig je zelf dat niemand herkenbaar is.</p>
<section class="card">
  <h2>Nieuw album</h2>
  <form class="form" method="post" action="<?= e(url('gallery_album_save')) ?>">
    <?= csrf_field() ?>
    <div class="grid2">
      <label>Titel *<input name="title" maxlength="150" required></label>
      <label>Omschrijving<input name="description" maxlength="300"></label>
    </div>
    <div class="actions"><button class="btn" type="submit">Album aanmaken</button></div>
  </form>
</section>
<?php if ($albums): ?>
<div class="table-wrap"><table class="table">
  <thead><tr><th>Album</th><th>Foto's</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($albums as $a): ?>
    <tr><td><a href="<?= e(url('gallery_album', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></td><td><?= (int) $a['n'] ?></td>
      <td><span class="chip <?= $a['status'] === 'PUBLISHED' ? 'ok' : 'off' ?>"><?= $a['status'] === 'PUBLISHED' ? 'Online' : 'Concept' ?></span></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
