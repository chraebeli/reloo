<?php
$title = 'Backups';
require __DIR__ . '/../layouts/header.php';
?>
<h1 class="h4 mb-3">Backup-Verwaltung</h1>

<div class="alert alert-warning" role="alert">
  <strong>Achtung:</strong> Wiederherstellung dieses Backups überschreibt aktuelle Daten.
</div>

<div class="card p-3 mb-4">
  <h2 class="h6">Backup erstellen</h2>
  <p class="text-muted mb-3">Erstellt ein vollständiges manuelles Backup der App-Datenbank und Upload-Dateien.</p>
  <form method="post" action="<?= e(app_base_path($config)) ?>/admin/backups/create">
    <?= csrf_field() ?>
    <button class="btn btn-primary" type="submit">Backup erstellen</button>
  </form>
</div>

<div class="card p-3">
  <h2 class="h6">Vorhandene Backups</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
      <tr>
        <th>Dateiname</th>
        <th>Erstellt am</th>
        <th>Größe</th>
        <th>Typ / Inhalt</th>
        <th>Erstellt von</th>
        <th>Aktionen</th>
      </tr>
      </thead>
      <tbody>
      <?php if ($backups === []): ?>
        <tr><td colspan="6" class="text-muted">Noch keine Backups vorhanden.</td></tr>
      <?php endif; ?>
      <?php foreach ($backups as $backup): ?>
        <tr>
          <td><?= e($backup['filename']) ?></td>
          <td><?= e(date('d.m.Y H:i:s', strtotime((string) $backup['created_at']))) ?></td>
          <td><?= e(number_format(((int) $backup['size_bytes']) / 1024 / 1024, 2, ',', '.')) ?> MB</td>
          <td>
            <div><span class="badge text-bg-secondary"><?= e((string) $backup['backup_type']) ?></span></div>
            <small class="text-muted">
              DB + Uploads
            </small>
          </td>
          <td>
            <?php $createdBy = $backup['created_by']; ?>
            <?= e(is_array($createdBy) ? (string) ($createdBy['email'] ?? 'unbekannt') : 'unbekannt') ?>
          </td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <a class="btn btn-sm btn-outline-primary" href="<?= e(app_base_path($config)) ?>/admin/backups/download?file=<?= urlencode((string) $backup['filename']) ?>">Download</a>

              <form method="post" action="<?= e(app_base_path($config)) ?>/admin/backups/delete" onsubmit="return confirm('Backup wirklich löschen?');">
                <?= csrf_field() ?>
                <input type="hidden" name="file" value="<?= e((string) $backup['filename']) ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Löschen</button>
              </form>

              <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#restore-<?= md5((string) $backup['filename']) ?>">Wiederherstellen</button>
            </div>

            <div class="collapse mt-2" id="restore-<?= md5((string) $backup['filename']) ?>">
              <div class="border rounded p-2 bg-light">
                <p class="small mb-2"><strong>Bitte bestätige die Wiederherstellung ausdrücklich.</strong></p>
                <form method="post" action="<?= e(app_base_path($config)) ?>/admin/backups/restore" onsubmit="return confirm('Restore jetzt starten? Aktuelle Daten werden überschrieben.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="file" value="<?= e((string) $backup['filename']) ?>">

                  <div class="mb-2">
                    <label class="form-label form-label-sm">Bestätigungstext</label>
                    <input class="form-control form-control-sm" type="text" name="confirmation_text" required placeholder="Ich verstehe, dass die aktuellen Daten überschrieben werden">
                  </div>

                  <div class="mb-2">
                    <label class="form-label form-label-sm">Admin-Passwort</label>
                    <input class="form-control form-control-sm" type="password" name="current_password" required>
                  </div>

                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" name="create_safety_backup" id="safety-<?= md5((string) $backup['filename']) ?>" checked>
                    <label class="form-check-label" for="safety-<?= md5((string) $backup['filename']) ?>">Vorher ein Sicherheits-Backup erstellen</label>
                  </div>

                  <button class="btn btn-sm btn-danger" type="submit">Wiederherstellung starten</button>
                </form>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
