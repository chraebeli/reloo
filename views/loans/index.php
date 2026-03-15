<?php $title = 'Ausleihen & Anfragen'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card p-3">
      <h2 class="h5">Offene Anfragen</h2>
      <?php foreach ($pending as $req): ?>
      <div class="border rounded p-2 mb-2">
        <strong><?= e($req['title']) ?></strong><br>
        <small><?= e($req['requester_name']) ?> möchte anfragen</small>
        <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/loans/approve" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
          <button class="btn btn-sm btn-primary">Bestätigen</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3">
      <h2 class="h5">Ausleihhistorie</h2>
      <?php foreach ($loans as $loan): ?>
      <div class="border rounded p-2 mb-2 d-flex justify-content-between">
        <div><strong><?= e($loan['title']) ?></strong><br><small><?= e($loan['borrower_name']) ?></small></div>
        <div>
          <span class="status-pill"><?= e($loan['status']) ?></span>
          <?php if ($loan['status'] !== 'zurückgegeben'): ?>
          <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/loans/return" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="loan_id" value="<?= (int)$loan['id'] ?>">
            <button class="btn btn-sm btn-outline-primary">Als zurückgegeben markieren</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
