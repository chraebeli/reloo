<?php $title = 'Neues Passwort'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row justify-content-center"><div class="col-md-6"><div class="card p-4">
<h1 class="h4 mb-3">Neues Passwort setzen</h1>
<form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/password/reset">
<?= csrf_field() ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<label class="form-label">Neues Passwort</label>
<input type="password" name="password" minlength="10" class="form-control mb-3" required>
<button class="btn btn-primary">Passwort speichern</button>
</form>
</div></div></div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
