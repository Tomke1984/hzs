<h1><?= $p['id'] ? 'Pagina wijzigen' : 'Nieuwe pagina' ?></h1>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="card form" method="post" action="<?= e(url('page_save')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
  <label>Titel *<input name="title" value="<?= e($p['title']) ?>" maxlength="190" required></label>
  <label>Adres (optioneel, anders afgeleid van de titel)<input name="slug" value="<?= e($p['slug']) ?>" maxlength="120" placeholder="bv. lidgeld"></label>
  <label>Tekst<textarea class="editor" name="body" rows="14"><?= e($p['body']) ?></textarea></label>
  <p class="muted md-help">Opmaak: <code>## Kop</code>, <code>### Subkop</code>, <code>- opsomming</code>, <code>**vet**</code>, <code>*cursief*</code>, <code>[tekst](https://adres)</code>, <code>![omschrijving](uploads/bestand.jpg)</code> (code van een geüploade afbeelding vind je bij <a href="<?= e(url('content', ['tab' => 'media'])) ?>">Afbeeldingen</a>). Een lege regel start een nieuwe alinea.</p>
  <div class="grid2">
    <label>Onder pagina
      <select name="parent_id">
        <option value="0">— (hoofdpagina)</option>
        <?php foreach ($parents as $par): ?><option value="<?= (int) $par['id'] ?>" <?= (int) $p['parent_id'] === (int) $par['id'] ? 'selected' : '' ?>><?= e($par['title']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Volgorde in het menu<input type="number" name="sort_order" value="<?= (int) $p['sort_order'] ?>"></label>
  </div>
  <label class="check"><input type="checkbox" name="in_menu" value="1" <?= !empty($p['in_menu']) ? 'checked' : '' ?>> Tonen in het menu</label>
  <label>Status
    <select name="status">
      <option value="DRAFT" <?= $p['status'] === 'DRAFT' ? 'selected' : '' ?>>Concept (nog niet publiek)</option>
      <option value="PUBLISHED" <?= $p['status'] === 'PUBLISHED' ? 'selected' : '' ?>>Gepubliceerd</option>
    </select>
  </label>
  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-ghost" href="<?= e(url('content', ['tab' => 'pages'])) ?>">Annuleren</a>
  </div>
</form>
