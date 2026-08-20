<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$user = $user ?? current_user();
$roleLabels = ['admin' => 'ADMIN', 'guru' => 'GURU', 'orang_tua' => 'ORANG TUA'];
$photo = $user ? trim((string) ($user['photo_url'] ?? '')) : '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b4332">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/images/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="<?= $user ? 'dashboard-body role-' . e((string) $user['role']) : '' ?>">
<?php if ($user): ?>
    <button class="mobile-toggle screen-only" type="button" data-sidebar-toggle aria-label="Buka menu navigasi">
        <?= svg_icon('dashboard', 20) ?>
    </button>
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
    <header class="topbar screen-only">
        <button class="guide-button" type="button" data-guide-open>
            <?= svg_icon('help', 17) ?>
            <span>Panduan</span>
        </button>
        <span class="topbar-divider" aria-hidden="true"></span>
        <div class="account">
            <?php if ($photo !== ''): ?>
                <img class="avatar avatar-photo" src="<?= e(url($photo)) ?>" alt="Foto <?= e($user['full_name']) ?>">
            <?php else: ?>
                <div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1))) ?></div>
            <?php endif; ?>
            <div class="account-copy">
                <div class="account-name">
                    <strong><?= e($user['full_name']) ?></strong>
                    <span class="role-badge"><?= e($roleLabels[$user['role']] ?? strtoupper((string) $user['role'])) ?></span>
                </div>
                <small><?= e($user['email'] ?: $user['username']) ?></small>
            </div>
        </div>
    </header>
    <main class="main" id="main-content">
<?php endif; ?>
<?php foreach (pull_flashes() as $message): ?>
    <div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
<?php endforeach; ?>
