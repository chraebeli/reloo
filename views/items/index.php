<?php $title = 'Gegenstände'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Inventar</h1>
  <a class="btn btn-primary" href="<?= e(app_base_path($config)) ?>/items/new">Neuen Gegenstand erfassen</a>
</div>
<form class="card p-3 mb-3" method="get" action="<?= e(app_base_path($config)) ?>/items">
  <div class="row g-2"><div class="col-md-10"><input class="form-control" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Suche nach Titel, Beschreibung oder Tags"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Suchen</button></div></div>
</form>
<div class="row g-3">
<?php foreach ($items as $item): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100">
      <div class="item-preview">
        <?php if (!empty($item['image'])): ?>
          <img src="<?= e(app_base_path($config) . '/' . $item['image']) ?>" class="item-preview__image" alt="<?= e($item['title']) ?>">
        <?php else: ?>
          <div class="item-preview__placeholder" aria-label="Kein Bild verfügbar">Kein Bild</div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <h2 class="h6"><a href="<?= e(app_base_path($config)) ?>/items/show?id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a></h2>
        <p class="small text-muted"><?= e($item['category_name'] ?? 'Ohne Kategorie') ?> · <?= e($item['owner_name']) ?></p>
        <span class="status-pill"><?= e($item['availability_status']) ?></span>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
