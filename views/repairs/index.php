<?php $title = 'Reparaturen'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="card p-3">
  <h1 class="h4">Reparaturfälle</h1>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Gegenstand</th><th>Status</th><th>Problem</th><th>Aktion</th></tr></thead>
      <tbody>
      <?php foreach ($repairs as $repair): ?>
      <tr>
        <td><?= e($repair['title']) ?></td>
        <td><span class="status-pill"><?= e($repair['status']) ?></span></td>
        <td><?= e($repair['issue_description']) ?></td>
        <td>
          <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/repairs/update-status" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="repair_id" value="<?= (int)$repair['id'] ?>">
            <select name="status" class="form-select form-select-sm">
              <option value="gemeldet">Gemeldet</option>
              <option value="in_pruefung">In Prüfung</option>
              <option value="in_reparatur">In Reparatur</option>
              <option value="repariert">Repariert</option>
              <option value="nicht_reparierbar">Nicht reparierbar</option>
            </select>
            <button class="btn btn-sm btn-outline-primary">Speichern</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
