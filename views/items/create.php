<?php $title = 'Gegenstand erfassen'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="card p-4">
  <h1 class="h4 mb-3">Gegenstand erfassen</h1>
  <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/items/create" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Titel</label><input name="title" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Gruppe</label><select name="group_id" class="form-select" required><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= e($group['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Kategorie</label><select name="category_id" class="form-select"><option value="">Bitte wählen</option><?php foreach ($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Zustand</label><input name="item_condition" class="form-control" placeholder="z. B. gebraucht_gut"></div>
      <div class="col-md-6"><label class="form-label">Eigentumsform</label><select name="ownership_type" class="form-select"><option value="privat_verleihbar">Privat verleihbar</option><option value="privat_verschenken">Privat zu verschenken</option><option value="privat_tausch">Privat zum Tausch</option><option value="gemeinschaftlich">Gemeinschaftlich</option></select></div>
      <div class="col-md-6"><label class="form-label">Verfügbarkeit</label><select name="availability_status" class="form-select"><option>verfügbar</option><option>angefragt</option><option>reserviert</option><option>ausgeliehen</option><option>in_reparatur</option><option>deaktiviert</option></select></div>
      <div class="col-12"><label class="form-label">Beschreibung</label><textarea name="description" class="form-control"></textarea></div>
      <div class="col-md-6"><label class="form-label">Standort</label><input name="location_text" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Kaution / Hinweis</label><input name="deposit_note" class="form-control"></div>
      <div class="col-md-12"><label class="form-label">Tags</label><input name="tags" class="form-control" placeholder="werkzeug, bohrmaschine"></div>
      <div class="col-md-12"><label class="form-label">Bilder (JPG/PNG/WEBP, max. 5MB je Bild)</label><input type="file" name="images[]" multiple class="form-control"></div>
    </div>
    <button class="btn btn-primary mt-3">Speichern</button>
  </form>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
