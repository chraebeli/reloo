<?php $title = 'Gegenstand erfassen'; require __DIR__ . '/../layouts/header.php'; ?>
<div class="card p-4">
  <h1 class="h4 mb-3">Gegenstand erfassen</h1>
  <form method="post" action="<?= e(app_base_path($config)) ?>/items/create" enctype="multipart/form-data" id="item-create-form">
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
      <div class="col-md-12">
        <label class="form-label">Tags</label>
        <input name="tags" class="form-control" placeholder="werkzeug, bohrmaschine" list="existing-tags-list">
        <datalist id="existing-tags-list"><?php foreach ($knownTags ?? [] as $tag): ?><option value="<?= e($tag) ?>"><?php endforeach; ?></datalist>
      </div>
      <div class="col-md-12">
        <label class="form-label">Bilder (JPG/PNG/WEBP, max. 5MB je Bild)</label>
        <input type="file" name="images[]" multiple class="form-control" id="item-images-input" accept="image/jpeg,image/png,image/webp">
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="analyze-image-btn">Bild analysieren</button>
          <small class="text-muted">Vorschläge sind optional und können jederzeit angepasst werden.</small>
        </div>
        <div id="analysis-feedback" class="small mt-2 text-muted" aria-live="polite"></div>
      </div>
    </div>
    <button class="btn btn-primary mt-3">Speichern</button>
  </form>
</div>
<script>
(() => {
  const form = document.getElementById('item-create-form');
  const analyzeButton = document.getElementById('analyze-image-btn');
  const fileInput = document.getElementById('item-images-input');
  const feedback = document.getElementById('analysis-feedback');

  if (!form || !analyzeButton || !fileInput || !feedback) {
    return;
  }

  const setMessage = (message, type = 'muted') => {
    feedback.className = 'small mt-2';
    if (type === 'error') {
      feedback.classList.add('text-danger');
    } else if (type === 'success') {
      feedback.classList.add('text-success');
    } else {
      feedback.classList.add('text-muted');
    }
    feedback.textContent = message;
  };

  const fillIfEmpty = (selector, value) => {
    const input = form.querySelector(selector);
    if (!input || !value) {
      return;
    }

    if ((input.value || '').trim() === '') {
      input.value = value;
    }
  };

  analyzeButton.addEventListener('click', async () => {
    const firstFile = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!firstFile) {
      setMessage('Bitte zuerst ein Bild auswählen.', 'error');
      return;
    }

    const csrfField = form.querySelector('input[name="_csrf"]');
    const csrf = csrfField ? csrfField.value : '';
    if (!csrf) {
      setMessage('Sicherheits-Token fehlt. Bitte Seite neu laden.', 'error');
      return;
    }

    analyzeButton.disabled = true;
    setMessage('Bild wird analysiert ...');

    const payload = new FormData();
    payload.append('_csrf', csrf);
    payload.append('image', firstFile);

    try {
      const response = await fetch('<?= e(app_base_path($config)) ?>/items/suggest-from-image', {
        method: 'POST',
        body: payload,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await response.json();
      if (!data || !data.ok || !data.suggestions) {
        setMessage(data && data.message ? data.message : 'Die automatische Erkennung war nicht möglich. Du kannst den Gegenstand manuell erfassen.', 'error');
        return;
      }

      fillIfEmpty('input[name="title"]', data.suggestions.title || '');
      fillIfEmpty('textarea[name="description"]', data.suggestions.description || '');

      const categorySelect = form.querySelector('select[name="category_id"]');
      if (categorySelect && data.suggestions.category_id) {
        categorySelect.value = String(data.suggestions.category_id);
      }

      if (Array.isArray(data.suggestions.tags) && data.suggestions.tags.length > 0) {
        fillIfEmpty('input[name="tags"]', data.suggestions.tags.join(', '));
      }

      setMessage(data.message || 'Vorschläge aus dem Foto übernommen.', 'success');
    } catch (error) {
      setMessage('Die automatische Erkennung war nicht möglich. Du kannst den Gegenstand manuell erfassen.', 'error');
    } finally {
      analyzeButton.disabled = false;
    }
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
