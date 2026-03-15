<?php
$flashError = \App\Core\Session::flash('error');
$flashSuccess = \App\Core\Session::flash('success');
$basePath = rtrim($config['app']['base_path'], '/');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$currentPath = '/' . ltrim(substr($path, strlen($basePath)), '/');
$isAuthPage = in_array($title ?? '', ['Login', 'Registrierung', 'Passwort vergessen', 'Neues Passwort'], true);
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Reloo') . ' | Reloo Sharing Kommune') ?></title>
    <meta name="description" content="Reloo hilft lokalen Gemeinschaften beim Teilen, Tauschen, Verschenken und Reparieren von Gegenständen.">
    <meta name="robots" content="noindex,follow">
    <link rel="canonical" href="<?= e((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e($basePath) ?>/assets/brand/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e($basePath) ?>/styles.css" rel="stylesheet">
</head>
<body class="<?= $isAuthPage ? 'auth-page' : '' ?>">
<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <a class="brand-link" href="<?= e($basePath) ?>/dashboard" aria-label="Reloo Startseite">
            <img src="<?= e($basePath) ?>/assets/brand/logo-horizontal.svg" alt="Reloo Logo">
        </a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <div class="d-flex align-items-center main-nav">
            <a class="nav-link <?= $currentPath === '/items' ? 'is-active' : '' ?>" href="<?= e($basePath) ?>/items">Gegenstände</a>
            <a class="nav-link <?= $currentPath === '/groups' ? 'is-active' : '' ?>" href="<?= e($basePath) ?>/groups">Gruppen</a>
            <a class="nav-link <?= $currentPath === '/loans' ? 'is-active' : '' ?>" href="<?= e($basePath) ?>/loans">Ausleihen</a>
            <a class="nav-link <?= $currentPath === '/repairs' ? 'is-active' : '' ?>" href="<?= e($basePath) ?>/repairs">Reparaturen</a>
            <?php if (user_is_admin()): ?>
            <a class="nav-link <?= $currentPath === '/admin' ? 'is-active' : '' ?>" href="<?= e($basePath) ?>/admin">Admin</a>
            <?php endif; ?>
            <form method="post" action="<?= e($basePath) ?>/logout" class="ms-lg-2">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-light">Logout</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
    <?php if ($isAuthPage): ?>
      <div class="auth-brand-panel">
        <img src="<?= e($basePath) ?>/assets/brand/logo-monochrome.svg" alt="Reloo Wort-Bild-Marke">
      </div>
    <?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
