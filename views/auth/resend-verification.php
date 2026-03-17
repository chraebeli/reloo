<?php $title = 'Bestätigungslink erneut senden'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h1 class="h4 mb-3">Bestätigungslink erneut senden</h1>
      <p class="text-muted">Wenn dein Link abgelaufen ist oder keine E-Mail angekommen ist, senden wir dir gerne einen neuen Bestätigungslink.</p>
      <form method="post" action="<?= e(app_base_path($config)) ?>/verification/resend">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">E-Mail</label>
          <input type="email" name="email" class="form-control" required value="<?= e((string) ($prefillEmail ?? '')) ?>">
        </div>
        <button class="btn btn-primary w-100">Neuen Bestätigungslink senden</button>
      </form>
      <a class="small mt-3" href="<?= e(app_base_path($config)) ?>/login">Zurück zum Login</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
