<?php $title = 'Login'; require __DIR__ . '/../layouts/header.php'; ?>
<?php $basePath = app_base_path($config); ?>
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card p-4">
      <h1 class="h4 mb-3">Willkommen zurück</h1>
      <form method="post" action="<?= e($basePath) ?>/login">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">E-Mail</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Passwort</label><input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary w-100">Einloggen</button>
      </form>
      <div class="login-divider"><span>oder</span></div>
      <button id="passkeyLoginBtn" class="btn btn-outline-primary w-100" type="button">Mit Passkey anmelden</button>
      <div class="d-flex flex-column gap-1 mt-3">
        <a class="small" href="<?= e($basePath) ?>/verification/resend">Bestätigungslink erneut senden</a>
        <a class="small" href="<?= e($basePath) ?>/register">Noch kein Konto? Jetzt registrieren</a>
      </div>
    </div>
  </div>
</div>

<script>
(async function () {
  const btn = document.getElementById('passkeyLoginBtn');
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
      const optionsRes = await fetch('<?= e($basePath) ?>/login/passkey/options', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({_csrf: csrf})
      });

      const optionsData = await optionsRes.json();
      if (!optionsRes.ok) throw new Error(optionsData.error || 'Passkey-Anmeldung konnte nicht gestartet werden.');

      const assertion = await navigator.credentials.get({
        publicKey: {
          challenge: b64urlToBuffer(optionsData.challenge),
          timeout: optionsData.timeout || 60000,
          rpId: window.location.hostname,
          userVerification: 'preferred'
        }
      });

      const verifyRes = await fetch('<?= e($basePath) ?>/login/passkey/verify', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          _csrf: csrf,
          credentialId: assertion.id,
          clientDataJSON: bufferToBase64(assertion.response.clientDataJSON),
          authenticatorData: bufferToBase64(assertion.response.authenticatorData),
          signature: bufferToBase64(assertion.response.signature)
        })
      });

      const verifyData = await verifyRes.json();
      if (!verifyRes.ok) throw new Error(verifyData.error || 'Passkey-Anmeldung fehlgeschlagen.');

      window.location.href = verifyData.redirect || '<?= e($basePath) ?>/dashboard';
    } catch (err) {
      alert(err.message || 'Passkey-Anmeldung fehlgeschlagen.');
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
