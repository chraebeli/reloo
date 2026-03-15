<?php $title = 'Passwort vergessen'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row justify-content-center"><div class="col-md-6"><div class="card p-4">
<h1 class="h4 mb-3">Passwort zurücksetzen</h1>
<form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/password/forgot">
<?= csrf_field() ?>
<label class="form-label">E-Mail</label>
<input type="email" name="email" class="form-control mb-3" required>
<button class="btn btn-primary">Reset-Link anfordern</button>
</form>
</div></div></div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
