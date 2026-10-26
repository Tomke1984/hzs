<div class="row-between">
  <h1>Nieuwsbrief</h1>
  <?php if ((int) $counts['active']): ?><a class="btn btn-ghost" href="<?= e(url('newsletter', ['csv' => 1])) ?>">Exporteer abonnees (CSV)</a><?php endif; ?>
</div>
<p class="muted"><?= (int) $counts['active'] ?> actieve abonnees · <?= (int) $counts['pending'] ?> wachten op bevestiging · <?= (int) $counts['gone'] ?> afgemeld. Bezoekers melden zich aan via het formulier onderaan de website en bevestigen via mail.</p>
<section class="card">
  <h2>Een nieuwsbericht versturen</h2>
  <?php if (!$posts): ?><p class="muted">Er zijn nog geen gepubliceerde berichten.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bericht</th><th>Verstuurd</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td><a href="<?= e(url('post_form', ['id' => $p['id']])) ?>"><?= e($p['title']) ?></a></td>
        <td><?= $p['newsletter_sent_at'] ? e(fmt_dt($p['newsletter_sent_at'])) : '—' ?></td>
        <td>
          <form method="post" action="<?= e(url('newsletter_send')) ?>" data-confirm="Dit bericht naar <?= (int) $counts['active'] ?> abonnees mailen?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <?php if ($p['newsletter_sent_at']): ?><label class="check small"><input type="checkbox" name="again" value="1"> opnieuw versturen</label><?php endif; ?>
            <button class="btn btn-small" type="submit" <?= (int) $counts['active'] ? '' : 'disabled' ?>>Verstuur</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php if ($sent): ?>
<section class="card">
  <h2>Laatst verstuurd</h2>
  <ul class="list"><?php foreach ($sent as $b): ?><li><strong><?= e($b['subject']) ?></strong> <span class="muted"><?= e(fmt_dt($b['created_at'])) ?> · <?= (int) $b['recipient_count'] ?> ontvangers</span></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
