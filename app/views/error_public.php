<div class="banner on-deep"><div class="wrap"><h1><?= e($code) ?></h1></div></div>
<div class="wrap section">
  <p><?= e($message ?: 'Er ging iets mis.') ?></p>
  <p><a class="btn" href="<?= e(url('home')) ?>">Naar de startpagina</a></p>
</div>
