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
        <img class="img-fluid rounded mb-2" src="<?= e(app_base_path($config) . '/' . $img['file_path']) ?>" alt="Bild von <?= e($item['title']) ?>">
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <?php if ($canDeleteItem): ?>
      <div class="card p-3 mb-3 border-danger-subtle">
        <h2 class="h5 text-danger">Gegenstand löschen</h2>
        <?php if ($deleteBlockedByState): ?>
          <div class="alert alert-warning mb-3"><?= e($deleteBlockedByState) ?></div>
        <?php else: ?>
          <p class="text-muted small mb-3">
            <?= $requiresAdminReason
              ? 'Als Administrator kannst du diesen Gegenstand löschen. Bitte dokumentiere die Begründung verpflichtend.'
              : 'Du kannst diesen Gegenstand löschen, weil du der ursprüngliche Besitzer bist und es sich nicht um einen gemeinschaftlichen Gegenstand handelt.' ?>
          </p>
        <?php endif; ?>
        <form method="post" action="<?= e(app_base_path($config)) ?>/items/delete" onsubmit="return confirm('Soll dieser Gegenstand wirklich gelöscht werden?');">
          <?= csrf_field() ?>
          <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
          <?php if ($requiresAdminReason): ?>
            <div class="mb-3">
              <label class="form-label" for="admin_reason">Begründung für die administrative Löschung</label>
              <textarea class="form-control" id="admin_reason" name="admin_reason" rows="3" minlength="10" required placeholder="Bitte Grund angeben"></textarea>
              <div class="form-text">Pflichtfeld, mindestens 10 Zeichen.</div>
            </div>
          <?php endif; ?>
          <button class="btn btn-danger" <?= $deleteBlockedByState ? 'disabled aria-disabled="true"' : '' ?>>Löschen</button>
        </form>
      </div>
    <?php elseif ($deleteHint): ?>
      <div class="card p-3 mb-3 border-warning-subtle">
        <h2 class="h5">Löschung nicht möglich</h2>
        <p class="mb-0 text-muted"><?= e($deleteHint) ?></p>
      </div>
    <?php endif; ?>
    <div class="card p-3 mb-3">
      <h2 class="h5">Anfrage senden</h2>
      <form method="post" action="<?= e(app_base_path($config)) ?>/loans/request">
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
      <form method="post" action="<?= e(app_base_path($config)) ?>/repairs/create">
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
