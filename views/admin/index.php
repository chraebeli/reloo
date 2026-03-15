<?php $title = 'Admin'; require __DIR__ . '/../layouts/header.php'; ?>
<h1 class="h4 mb-3">Adminbereich</h1>
<div class="row g-3 mb-4">
<?php foreach ($stats as $label => $value): ?>
  <div class="col-md-4"><div class="card p-3"><small><?= e(ucfirst($label)) ?></small><div class="h4"><?= (int)$value ?></div></div></div>
<?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card p-3">
      <h2 class="h6">Benutzerverwaltung</h2>
      <ul class="list-group list-group-flush">
        <?php foreach ($users as $user): ?>
          <li class="list-group-item"><?= e($user['display_name']) ?> (<?= e($user['role']) ?>) – <?= e($user['email']) ?></li>
        <?php endforeach; ?>
      </ul>
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
    <div class="card p-3">
      <h2 class="h6">Export</h2>
      <a class="btn btn-primary" href="<?= e(app_base_path($config)) ?>/admin/export/csv">CSV-Export herunterladen</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
