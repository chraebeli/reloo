<?php $title = 'Gegenstand'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card p-3">
      <h1 class="h4"><?= e($item['title']) ?></h1>
      <p class="text-muted"><?= e($item['category_name'] ?? 'Ohne Kategorie') ?> · Besitzer: <?= e($item['owner_name']) ?></p>
      <p><?= nl2br(e($item['description'] ?? '')) ?></p>
      <p><strong>Status:</strong> <span class="status-pill"><?= e($item['availability_status']) ?></span></p>
      <p><strong>Eigentumsform:</strong> <?= e($item['ownership_type']) ?></p>
      <p><strong>Standort:</strong> <?= e($item['location_text'] ?? '-') ?></p>
      <?php foreach (($item['images'] ?? []) as $img): ?>
        <img class="img-fluid rounded mb-2" src="<?= e(rtrim($config['app']['base_path'], '/') . '/' . $img['file_path']) ?>" alt="Bild von <?= e($item['title']) ?>">
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 mb-3">
      <h2 class="h5">Anfrage senden</h2>
      <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/loans/request">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
        <div class="mb-2"><label class="form-label">Typ</label><select name="request_type" class="form-select"><option value="ausleihe">Ausleihe</option><option value="geschenk">Geschenk</option><option value="tausch">Tausch</option></select></div>
        <div class="row"><div class="col-md-6 mb-2"><label class="form-label">Start</label><input type="date" name="start_date" class="form-control"></div><div class="col-md-6 mb-2"><label class="form-label">Ende</label><input type="date" name="end_date" class="form-control"></div></div>
        <div class="mb-2"><label class="form-label">Nachricht</label><textarea name="message" class="form-control"></textarea></div>
        <button class="btn btn-primary">Anfrage senden</button>
      </form>
    </div>
    <div class="card p-3">
      <h2 class="h5">Reparatur melden</h2>
      <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/repairs/create">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
        <textarea name="issue_description" class="form-control mb-2" placeholder="Was ist defekt?" required></textarea>
        <input name="part_notes" class="form-control mb-2" placeholder="Ersatzteile / Material">
        <input name="effort_notes" class="form-control mb-2" placeholder="Aufwand / Hinweise">
        <button class="btn btn-outline-primary">Reparaturfall anlegen</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
