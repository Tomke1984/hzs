<div class="card narrow center">
  <h1><?= e($code) ?></h1>
  <p><?= e($message ?: 'Er ging iets mis.') ?></p>
  <p><a class="btn" href="<?= e(url('dashboard')) ?>">Naar het overzicht</a></p>
</div>
