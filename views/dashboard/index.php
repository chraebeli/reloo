<?php $title = 'Dashboard'; require __DIR__ . '/../layouts/header.php'; ?>
<section class="hero p-4 mb-4">
  <h1 class="h3">Hallo <?= e($_SESSION['display_name'] ?? '') ?>, willkommen in eurer Sharing-Kommune</h1>
  <p class="mb-0">Übersicht über Verfügbarkeit, Anfragen und Reparaturen.</p>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <a class="btn btn-light btn-sm" href="<?= e(app_base_path($config)) ?>/items/new">+ Gegenstand anlegen</a>
    <a class="btn btn-outline-light btn-sm" href="<?= e(app_base_path($config)) ?>/loans">Anfragen prüfen</a>
    <a class="btn btn-outline-light btn-sm" href="<?= e(app_base_path($config)) ?>/repairs">Reparaturen verwalten</a>
  </div>
</section>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card p-3"><small>Verfügbare Gegenstände</small><div class="h3"><?= (int)($stats['total_items'] ?? 0) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><small>Aktive Ausleihen</small><div class="h3"><?= (int)($stats['active_loans'] ?? 0) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><small>Offene Anfragen</small><div class="h3"><?= (int)($stats['open_requests'] ?? 0) ?></div></div></div>
  <div class="col-md-3"><div class="card p-3"><small>In Reparatur</small><div class="h3"><?= (int)($stats['repairs_open'] ?? 0) ?></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card p-3"><h2 class="h5">Zuletzt hinzugefügt</h2>
      <?php if ($recentItems === []): ?>
      <p class="text-muted mb-0">Noch keine Gegenstände vorhanden. Lege den ersten Eintrag an.</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($recentItems as $item): ?>
        <li class="list-group-item d-flex justify-content-between"><a href="<?= e(app_base_path($config)) ?>/items/show?id=<?= (int)$item['id'] ?>"><?= e($item['title']) ?></a><span class="status-pill"><?= e($item['availability_status']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3"><h2 class="h5">Aktivitätsfeed</h2>
      <?php if ($activities === []): ?>
      <p class="text-muted mb-0">Sobald Aktivität in deiner Gruppe entsteht, wird sie hier angezeigt.</p>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($activities as $activity): ?>
        <li class="list-group-item"><strong><?= e($activity['display_name'] ?? 'System') ?>:</strong> <?= e($activity['message']) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
