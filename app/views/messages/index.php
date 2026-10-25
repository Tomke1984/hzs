<div class="row-between"><h1>Berichten</h1></div>
<p class="muted">Berichten uit het contactformulier van de website. Ze staan hier ook als de mail niet is aangekomen. Antwoord via je mailprogramma en markeer het bericht daarna als afgehandeld.</p>
<nav class="tabs" aria-label="Status">
  <a href="<?= e(url('messages', ['tab' => 'open'])) ?>" <?= $tab === 'open' ? 'aria-current="page"' : '' ?>>Open <span class="chip <?= $counts['open'] ? 'warn' : '' ?>"><?= (int) $counts['open'] ?></span></a>
  <a href="<?= e(url('messages', ['tab' => 'done'])) ?>" <?= $tab === 'done' ? 'aria-current="page"' : '' ?>>Afgehandeld <span class="chip"><?= (int) $counts['done'] ?></span></a>
</nav>
<?php if (!$rows): ?><p class="card muted"><?= $tab === 'open' ? 'Geen open berichten.' : 'Nog niets afgehandeld.' ?></p><?php endif; ?>
<?php foreach ($rows as $m): ?>
  <section class="card">
    <div class="row-between">
      <h2><?= e($m['first_name'] . ' ' . $m['last_name']) ?> <span class="chip"><?= e(SiteController::TOPICS[$m['topic']] ?? $m['topic']) ?></span></h2>
      <span class="muted small"><?= e(fmt_dt($m['created_at'])) ?></span>
    </div>
    <p class="muted small"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= (int) $m['mail_sent'] ? '' : ' · mail aan de club niet verstuurd' ?></p>
    <p><?= nl2br(e($m['message'])) ?></p>
    <div class="actions-inline">
      <form method="post" action="<?= e(url('message_handle')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
        <?php if ($tab === 'done'): ?><input type="hidden" name="undo" value="1"><?php endif; ?>
        <button class="btn btn-small" type="submit"><?= $tab === 'done' ? 'Opnieuw openen' : 'Afgehandeld' ?></button>
      </form>
      <form method="post" action="<?= e(url('message_delete')) ?>" data-confirm="Dit bericht definitief verwijderen?">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
        <button class="btn btn-small btn-ghost" type="submit">Verwijderen</button>
      </form>
    </div>
  </section>
<?php endforeach; ?>
