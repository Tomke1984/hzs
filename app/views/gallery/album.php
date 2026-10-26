<div class="row-between">
  <h1><?= e($a['title']) ?> <span class="chip <?= $a['status'] === 'PUBLISHED' ? 'ok' : 'off' ?>"><?= $a['status'] === 'PUBLISHED' ? 'Online' : 'Concept' ?></span></h1>
  <div>
    <form method="post" action="<?= e(url('gallery_publish')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="publish" value="<?= $a['status'] === 'PUBLISHED' ? '0' : '1' ?>">
      <button class="btn" type="submit"><?= $a['status'] === 'PUBLISHED' ? 'Offline halen' : 'Publiceren' ?></button></form>
    <a class="btn btn-ghost" href="<?= e(url('gallery_admin')) ?>">Alle albums</a>
  </div>
</div>
<section class="card">
  <form class="form" method="post" action="<?= e(url('gallery_album_save')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
    <div class="grid2">
      <label>Titel *<input name="title" value="<?= e($a['title']) ?>" maxlength="150" required></label>
      <label>Omschrijving<input name="description" value="<?= e((string) $a['description']) ?>" maxlength="300"></label>
    </div>
    <div class="actions"><button class="btn btn-ghost" type="submit">Opslaan</button></div>
  </form>
  <form class="form" method="post" action="<?= e(url('gallery_photo_upload')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="album_id" value="<?= (int) $a['id'] ?>">
    <label>Foto toevoegen (jpg, png, gif of webp, max. 5 MB)<input type="file" name="file" accept="image/*" required></label>
    <div class="actions"><button class="btn" type="submit">Uploaden</button></div>
  </form>
</section>
<?php foreach ($photos as $p): $t = $tags[(int) $p['id']] ?? []; $noConsent = array_filter($t, fn($x) => !(int) $x['photo_consent']); $ok = (int) $p['consent_ok'] && !$noConsent; ?>
  <section class="card" id="foto-<?= (int) $p['id'] ?>">
    <div class="photo-edit">
      <img src="<?= e($p['path']) ?>" alt="<?= e((string) ($p['caption'] ?? '')) ?>" loading="lazy">
      <form class="form" method="post" action="<?= e(url('gallery_photo_save')) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <p><span class="chip <?= $ok ? 'ok' : 'off' ?>"><?= $ok ? 'Mag online' : 'Verborgen: toestemming niet in orde' ?></span></p>
        <?php foreach ($noConsent as $x): ?><p class="alert alert-warning"><?= e($x['first_name'] . ' ' . $x['last_name']) ?> heeft geen toestemming voor foto's.</p><?php endforeach; ?>
        <label>Bijschrift<input name="caption" value="<?= e((string) $p['caption']) ?>" maxlength="200"></label>
        <label>Leden op de foto (Ctrl/Cmd-klik voor meerdere)
          <select name="members[]" multiple size="6">
            <?php $tagged = array_map(fn($x) => (int) $x['id'], $t); foreach ($members as $m): ?>
              <option value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $tagged, true) ? 'selected' : '' ?>><?= e($m['first_name'] . ' ' . $m['last_name']) ?><?= (int) $m['photo_consent'] ? '' : ' (geen toestemming)' ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="check"><input type="checkbox" name="no_one" value="1" <?= !$t && (int) $p['consent_ok'] ? 'checked' : '' ?>> Zonder getagde leden: er staat niemand herkenbaar op, of de toestemming is op een andere manier geregeld</label>
        <div class="actions"><button class="btn btn-small" type="submit">Opslaan</button></div>
      </form>
      <form method="post" action="<?= e(url('gallery_photo_delete')) ?>" data-confirm="Deze foto verwijderen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-small btn-ghost" type="submit">Foto verwijderen</button></form>
    </div>
  </section>
<?php endforeach; ?>
<form method="post" action="<?= e(url('gallery_album_delete')) ?>" data-confirm="Het hele album en alle foto's verwijderen?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-ghost" type="submit">Album verwijderen</button></form>
