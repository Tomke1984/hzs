<h1><?= $p['id'] ? 'Bericht wijzigen' : 'Nieuw bericht' ?></h1>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="card form" method="post" action="<?= e(url('post_save')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
  <label>Titel *<input name="title" value="<?= e($p['title']) ?>" maxlength="190" required></label>
  <label>Korte samenvatting (voor het overzicht)<input name="summary" value="<?= e($p['summary']) ?>" maxlength="300"></label>
  <label>Tekst<textarea class="editor" name="body" rows="14"><?= e($p['body']) ?></textarea></label>
  <p class="muted md-help">Opmaak: <code>## Kop</code>, <code>### Subkop</code>, <code>- opsomming</code>, <code>**vet**</code>, <code>*cursief*</code>, <code>[tekst](https://adres)</code>, <code>![omschrijving](uploads/bestand.jpg)</code> (code van een geüploade afbeelding vind je bij <a href="<?= e(url('content', ['tab' => 'media'])) ?>">Afbeeldingen</a>). Een lege regel start een nieuwe alinea.</p>
  <label>Hoofdafbeelding (optioneel)<input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
  <?php if ($p['image']): ?>
    <p><img src="<?= e($p['image']) ?>" alt="" class="thumb-small"></p>
    <label class="check"><input type="checkbox" name="remove_image" value="1"> Afbeelding verwijderen</label>
  <?php endif; ?>
  <label>Status
    <select name="status">
      <option value="DRAFT" <?= $p['status'] === 'DRAFT' ? 'selected' : '' ?>>Concept (nog niet publiek)</option>
      <option value="PUBLISHED" <?= $p['status'] === 'PUBLISHED' ? 'selected' : '' ?>>Gepubliceerd</option>
    </select>
  </label>
  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e(url('content', ['tab' => 'news'])) ?>">Annuleren</a>
  </div>
</form>
