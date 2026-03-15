<?php $title = 'Login'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h1 class="h4 mb-3">Willkommen zurück</h1>
      <form method="post" action="<?= e(app_base_path($config)) ?>/login">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Passwort</label><input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary w-100">Einloggen</button>
      </form>
      <a class="small mt-3" href="<?= e(app_base_path($config)) ?>/register">Noch kein Konto? Jetzt registrieren</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
