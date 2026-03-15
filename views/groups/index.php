<?php $title = 'Gruppen'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card p-3">
      <h2 class="h5">Meine Gruppen</h2>
      <ul class="list-group list-group-flush">
      <?php foreach ($groups as $group): ?>
        <li class="list-group-item"><strong><?= e($group['name']) ?></strong><br><small>Invite-Code: <?= e($group['invite_code']) ?></small></li>
      <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 mb-3">
      <h3 class="h6">Neue Gruppe erstellen</h3>
      <form method="post" action="<?= e(app_base_path($config)) ?>/groups/create">
        <?= csrf_field() ?>
        <input name="name" class="form-control mb-2" placeholder="Gruppenname" required>
        <textarea name="description" class="form-control mb-2" placeholder="Beschreibung"></textarea>
        <button class="btn btn-primary">Erstellen</button>
      </form>
    </div>
    <div class="card p-3">
      <h3 class="h6">Mit Einladungscode beitreten</h3>
      <form method="post" action="<?= e(app_base_path($config)) ?>/groups/join">
        <?= csrf_field() ?>
        <input name="invite_code" class="form-control mb-2" placeholder="z. B. A1B2C3D4" required>
        <button class="btn btn-outline-primary">Beitreten</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
