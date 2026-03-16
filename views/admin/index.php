<?php
$title = 'Admin';
require __DIR__ . '/../layouts/header.php';

$statusLabels = [
    'pending' => 'Wartet auf Freigabe',
    'approved' => 'Freigegeben',
    'rejected' => 'Abgelehnt',
];

$statusBadgeClasses = [
    'pending' => 'bg-warning text-dark',
    'approved' => 'bg-success',
    'rejected' => 'bg-danger',
];
?>
<h1 class="h4 mb-3">Adminbereich</h1>
<div class="row g-3 mb-4">
<?php foreach ($stats as $label => $value): ?>
  <div class="col-md-4"><div class="card p-3"><small><?= e(ucfirst(str_replace('_', ' ', $label))) ?></small><div class="h4"><?= (int)$value ?></div></div></div>
<?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-12">
    <div class="card p-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h6 mb-0">Benutzerfreigaben</h2>
        <div class="btn-group" role="group" aria-label="Statusfilter">
          <a class="btn btn-sm <?= $statusFilter === null ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(app_base_path($config)) ?>/admin">Alle</a>
          <a class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(app_base_path($config)) ?>/admin?status=pending">Nur wartend</a>
          <a class="btn btn-sm <?= $statusFilter === 'approved' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(app_base_path($config)) ?>/admin?status=approved">Nur freigegeben</a>
          <a class="btn btn-sm <?= $statusFilter === 'rejected' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(app_base_path($config)) ?>/admin?status=rejected">Nur abgelehnt</a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Name</th>
              <th>E-Mail</th>
              <th>Rolle</th>
              <th>Registriert am</th>
              <th>Status</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= e($user['display_name']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td><?= e($user['role']) ?></td>
                <td><?= e(date('d.m.Y H:i', strtotime((string) $user['created_at']))) ?></td>
                <td>
                  <?php $status = $user['approval_status'] ?? 'approved'; ?>
                  <span class="badge <?= e($statusBadgeClasses[$status] ?? 'bg-secondary') ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <form method="post" action="<?= e(app_base_path($config)) ?>/admin/users/approval">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                      <input type="hidden" name="status" value="approved">
                      <button class="btn btn-sm btn-outline-success" <?= ($status ?? '') === 'approved' ? 'disabled' : '' ?>>Freigeben</button>
                    </form>
                    <form method="post" action="<?= e(app_base_path($config)) ?>/admin/users/approval">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                      <input type="hidden" name="status" value="rejected">
                      <button class="btn btn-sm btn-outline-danger" <?= ($status ?? '') === 'rejected' ? 'disabled' : '' ?>>Ablehnen</button>
                    </form>
                    <form method="post" action="<?= e(app_base_path($config)) ?>/admin/users/approval">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                      <input type="hidden" name="status" value="pending">
                      <button class="btn btn-sm btn-outline-secondary" <?= ($status ?? '') === 'pending' ? 'disabled' : '' ?>>Auf wartend</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3 mb-3">
      <h2 class="h6">Kategorien</h2>
      <ul><?php foreach ($categories as $cat): ?><li><?= e($cat['name']) ?></li><?php endforeach; ?></ul>
      <form method="post" action="<?= e(app_base_path($config)) ?>/admin/categories/create">
        <?= csrf_field() ?>
        <input class="form-control mb-2" name="name" placeholder="Neue Kategorie">
        <button class="btn btn-outline-primary">Kategorie anlegen</button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3">
      <h2 class="h6">Export</h2>
      <a class="btn btn-primary" href="<?= e(app_base_path($config)) ?>/admin/export/csv">CSV-Export herunterladen</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
