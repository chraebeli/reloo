<?php $title = 'Mein Konto'; require __DIR__ . '/../layouts/header.php'; ?>
<?php $basePath = app_base_path($config); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h3 mb-0">Mein Konto</h1>
  <span class="badge text-bg-light border"><?= !empty($user['email_verified_at']) ? 'E-Mail verifiziert' : 'E-Mail-Bestätigung ausstehend' ?></span>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">Profil</h2>
      <form method="post" action="<?= e($basePath) ?>/settings/display-name">
        <?= csrf_field() ?>
        <label class="form-label" for="display_name">Anzeige-Name</label>
        <input class="form-control" id="display_name" name="display_name" maxlength="120" required value="<?= e((string) $user['display_name']) ?>">
        <button class="btn btn-primary mt-3">Änderungen speichern</button>
      </form>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">E-Mail-Adresse</h2>
      <p class="small mb-2">Aktuell: <strong><?= e((string) $user['email']) ?></strong></p>
      <?php if (!empty($user['pending_email'])): ?>
        <div class="alert alert-warning py-2">Neue Adresse wartet auf Bestätigung: <strong><?= e((string) $user['pending_email']) ?></strong></div>
      <?php endif; ?>
      <form method="post" action="<?= e($basePath) ?>/settings/email" class="mb-2">
        <?= csrf_field() ?>
        <label class="form-label" for="email">Neue E-Mail-Adresse</label>
        <input type="email" class="form-control" id="email" name="email" required>
        <button class="btn btn-primary mt-3">Bestätigungslink senden</button>
      </form>
      <?php if (!empty($user['pending_email'])): ?>
      <form method="post" action="<?= e($basePath) ?>/settings/email/resend">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary btn-sm">Bestätigungslink erneut senden</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">Passwort</h2>
      <form method="post" action="<?= e($basePath) ?>/settings/password">
        <?= csrf_field() ?>
        <label class="form-label" for="current_password">Aktuelles Passwort</label>
        <input type="password" class="form-control" id="current_password" name="current_password" required>
        <label class="form-label mt-2" for="new_password">Neues Passwort</label>
        <input type="password" class="form-control" id="new_password" name="new_password" minlength="10" required>
        <label class="form-label mt-2" for="new_password_confirm">Passwort bestätigen</label>
        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" minlength="10" required>
        <button class="btn btn-primary mt-3">Passwort ändern</button>
      </form>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">Passkeys / Sicherheit</h2>
      <p class="small"><?= empty($passkeys) ? 'Kein Passkey vorhanden' : 'Passkeys eingerichtet' ?></p>
      <div class="d-flex gap-2 mb-3">
        <input class="form-control" id="passkeyLabel" maxlength="120" placeholder="Bezeichnung (z. B. MacBook Touch ID)">
        <button class="btn btn-primary" type="button" id="addPasskeyBtn">Neuen Passkey hinzufügen</button>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($passkeys as $passkey): ?>
          <li class="list-group-item d-flex align-items-center justify-content-between px-0">
            <div>
              <div class="fw-semibold"><?= e((string) $passkey['label']) ?></div>
              <div class="small text-muted">Erstellt: <?= e((string) $passkey['created_at']) ?><?php if (!empty($passkey['last_used_at'])): ?> · Letzte Nutzung: <?= e((string) $passkey['last_used_at']) ?><?php endif; ?></div>
            </div>
            <form method="post" action="<?= e($basePath) ?>/settings/passkeys/delete" onsubmit="return confirm('Diesen Passkey wirklich entfernen?');">
              <?= csrf_field() ?>
              <input type="hidden" name="passkey_id" value="<?= (int) $passkey['id'] ?>">
              <button class="btn btn-outline-danger btn-sm">Entfernen</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<script>
(async function () {
  const btn = document.getElementById('addPasskeyBtn');
  if (!btn || !window.PublicKeyCredential) return;

  const csrf = '<?= e(csrf_token()) ?>';

  const b64urlToBuffer = (input) => {
    const pad = '='.repeat((4 - input.length % 4) % 4);
    const base64 = (input + pad).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from(raw, c => c.charCodeAt(0));
  };

  const bufferToBase64 = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer)));

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    try {
      const optionsRes = await fetch('<?= e($basePath) ?>/settings/passkeys/options', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({_csrf: csrf})
      });

      const optionsData = await optionsRes.json();
      if (!optionsRes.ok) throw new Error(optionsData.error || 'Optionen konnten nicht geladen werden.');

      const publicKey = {
        challenge: b64urlToBuffer(optionsData.challenge),
        rp: {id: window.location.hostname, name: 'Reloo'},
        user: {
          id: new TextEncoder().encode('<?= (int) $user['id'] ?>'),
          name: '<?= e((string) $user['email']) ?>',
          displayName: '<?= e((string) $user['display_name']) ?>'
        },
        pubKeyCredParams: [{type: 'public-key', alg: -7}, {type: 'public-key', alg: -257}],
        timeout: 60000,
        authenticatorSelection: {residentKey: 'preferred', userVerification: 'preferred'},
        attestation: 'none',
        excludeCredentials: (optionsData.excludeCredentials || []).map(item => ({
          type: 'public-key',
          id: b64urlToBuffer(item.id)
        }))
      };

      const credential = await navigator.credentials.create({publicKey});
      const response = credential.response;
      const resultRes = await fetch('<?= e($basePath) ?>/settings/passkeys/register', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          _csrf: csrf,
          label: document.getElementById('passkeyLabel')?.value || '',
          credentialId: credential.id,
          clientDataJSON: bufferToBase64(response.clientDataJSON),
          publicKey: response.getPublicKey ? bufferToBase64(response.getPublicKey()) : '',
          transports: response.getTransports ? response.getTransports() : []
        })
      });

      const resultData = await resultRes.json();
      if (!resultRes.ok) throw new Error(resultData.error || 'Registrierung fehlgeschlagen.');
      window.location.reload();
    } catch (err) {
      alert(err.message || 'Passkey konnte nicht erstellt werden.');
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
