<?php $title = 'Registrierung'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card p-4">
      <h1 class="h4 mb-3">Konto erstellen</h1>
      <form method="post" action="<?= e(app_base_path($config)) ?>/register">
        <?= csrf_field() ?>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
          <div class="col-md-6 mb-3"><label class="form-label">Anzeigename</label><input name="display_name" class="form-control" required></div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" required></div>
          <div class="col-md-6 mb-3"><label class="form-label">Telefon (optional)</label><input name="phone" class="form-control"></div>
        </div>
        <div class="mb-3"><label class="form-label">Standort / Quartier</label><input name="location" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Kurzbeschreibung</label><textarea name="bio" class="form-control"></textarea></div>
        <div class="mb-3"><label class="form-label">Passwort (mindestens 10 Zeichen)</label><input type="password" name="password" class="form-control" required minlength="10"></div>
        <button class="btn btn-primary">Registrieren</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
