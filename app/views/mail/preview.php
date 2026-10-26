<h1>Controleer je bericht</h1>
<section class="card">
  <dl class="facts">
    <dt>Aan</dt><dd><?= e($label) ?></dd>
    <dt>Ontvangers</dt><dd><strong><?= count($emails) ?></strong> e-mailadressen (<?= (int) $members ?> leden, inclusief ouders en voogden)</dd>
    <dt>Onderwerp</dt><dd><?= e($subject) ?></dd>
  </dl>
  <p><?= nl2br(e($body)) ?></p>
  <details><summary class="small">Alle adressen tonen</summary>
    <p class="small muted"><?= e(implode(', ', array_keys($emails))) ?></p>
  </details>
</section>
<form method="post" action="<?= e(url('mail_send')) ?>" class="actions">
  <?= csrf_field() ?>
  <input type="hidden" name="step" value="send">
  <input type="hidden" name="to" value="<?= e($key) ?>">
  <input type="hidden" name="subject" value="<?= e($subject) ?>">
  <input type="hidden" name="body" value="<?= e($body) ?>">
  <button class="btn" type="submit">Nu versturen naar <?= count($emails) ?> ontvangers</button>
  <button class="btn btn-ghost" type="submit" name="step" value="edit" formaction="<?= e(url('mail_edit')) ?>">Aanpassen</button>
</form>
