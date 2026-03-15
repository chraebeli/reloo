<?php $flashError = \App\Core\Session::flash('error'); $flashSuccess = \App\Core\Session::flash('success'); ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Reloo') . ' | Reloo Sharing Kommune') ?></title>
    <meta name="description" content="Reloo hilft lokalen Gemeinschaften beim Teilen, Tauschen, Verschenken und Reparieren von Gegenständen.">
    <meta name="robots" content="noindex,follow">
    <link rel="canonical" href="<?= e((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/dashboard">Reloo</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <div class="d-flex gap-3 align-items-center">
            <a class="nav-link" href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/items">Gegenstände</a>
            <a class="nav-link" href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/groups">Gruppen</a>
            <a class="nav-link" href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/loans">Ausleihen</a>
            <a class="nav-link" href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/repairs">Reparaturen</a>
            <?php if (user_is_admin()): ?>
            <a class="nav-link" href="<?= e(rtrim($config['app']['base_path'], '/')) ?>/admin">Admin</a>
            <?php endif; ?>
            <form method="post" action="<?= e(rtrim($config['app']['base_path'], '/')) ?>/logout">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-light">Logout</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
    <?php if ($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
