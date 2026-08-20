<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$user = $user ?? current_user();

$roleLabels = [
    'admin' => 'ADMIN',
    'guru' => 'GURU',
    'orang_tua' => 'ORANG TUA',
];

/*
|--------------------------------------------------------------------------
| User UI data
|--------------------------------------------------------------------------
*/

$photo = '';
$photoSrc = '';
$userInitial = '?';
$userIdentity = '';

if ($user) {
    $photo = trim((string) ($user['photo_url'] ?? ''));

    // Support foto berupa URL eksternal maupun path lokal.
    if ($photo !== '') {
        $photoSrc = preg_match('~^https?://~i', $photo)
            ? $photo
            : url($photo);
    }

    $fullName = trim((string) ($user['full_name'] ?? ''));

    if ($fullName !== '') {
        $userInitial = initials($fullName, 1);
    }

    $email = trim((string) ($user['email'] ?? ''));
    $username = trim((string) ($user['username'] ?? ''));

    $userIdentity = $email !== ''
        ? $email
        : $username;
}

/*
|--------------------------------------------------------------------------
| Role class
|--------------------------------------------------------------------------
*/

$role = $user
    ? strtolower(trim((string) ($user['role'] ?? '')))
    : '';

$roleClass = preg_replace(
    '/[^a-z0-9_-]/i',
    '-',
    $role
) ?: '';

/*
|--------------------------------------------------------------------------
| Asset version
|--------------------------------------------------------------------------
|
| Version berdasarkan waktu modifikasi file.
| Jadi setelah app.css diubah, browser otomatis mengambil CSS terbaru.
|
*/

$cssFile = ROOT_PATH . '/assets/css/app.css';

$cssVersion = is_file($cssFile)
    ? (string) filemtime($cssFile)
    : '1';

?>
<!doctype html>

<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta
        name="theme-color"
        content="#1b4332"
    >

    <meta
        name="color-scheme"
        content="light"
    >

    <title>
        <?= e((string) $pageTitle) ?> · <?= e(APP_NAME) ?>
    </title>

    <link
        rel="icon"
        href="<?= e(url('assets/images/favicon.ico')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(
            url('assets/css/app.css')
            . '?v='
            . rawurlencode($cssVersion)
        ) ?>"
    >

</head>

<body
    class="<?=
        $user
            ? 'dashboard-body role-' . e($roleClass)
            : ''
    ?>"
>

<?php if ($user): ?>

    <!-- Mobile navigation button -->
    <button
        class="mobile-toggle screen-only"
        type="button"
        data-sidebar-toggle
        aria-label="Buka menu navigasi"
        aria-expanded="false"
    >
        <svg
            aria-hidden="true"
            width="21"
            height="21"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
        >
            <path d="M4 6h16"></path>
            <path d="M4 12h16"></path>
            <path d="M4 18h16"></path>
        </svg>
    </button>

    <?php
    require ROOT_PATH . '/includes/sidebar.php';
    ?>

    <!-- Top navigation -->
    <header
        class="topbar screen-only"
        role="banner"
    >

        <button
            class="guide-button"
            type="button"
            data-guide-open
            aria-label="Buka panduan penggunaan"
        >
            <?= svg_icon('help', 17) ?>

            <span>Panduan</span>
        </button>

        <span
            class="topbar-divider"
            aria-hidden="true"
        ></span>

        <div class="account">

            <?php if ($photoSrc !== ''): ?>

                <img
                    class="avatar avatar-photo"
                    src="<?= e($photoSrc) ?>"
                    alt="Foto profil <?= e(
                        (string) ($user['full_name'] ?? '')
                    ) ?>"
                    loading="lazy"
                    decoding="async"
                >

            <?php else: ?>

                <div
                    class="avatar"
                    aria-hidden="true"
                >
                    <?= e($userInitial) ?>
                </div>

            <?php endif; ?>

            <div class="account-copy">

                <div class="account-name">

                    <strong>
                        <?= e(
                            (string) ($user['full_name'] ?? '')
                        ) ?>
                    </strong>

                    <span class="role-badge">
                        <?= e(
                            $roleLabels[$role]
                            ?? strtoupper(
                                str_replace('_', ' ', $role)
                            )
                        ) ?>
                    </span>

                </div>

                <?php if ($userIdentity !== ''): ?>

                    <small>
                        <?= e($userIdentity) ?>
                    </small>

                <?php endif; ?>

            </div>

        </div>

    </header>

    <main
        class="main"
        id="main-content"
    >

<?php endif; ?>


<?php foreach (pull_flashes() as $message): ?>

    <?php
    $flashType = preg_replace(
        '/[^a-z0-9_-]/i',
        '',
        (string) ($message['type'] ?? 'info')
    ) ?: 'info';
    ?>

    <div
        class="alert alert-<?= e($flashType) ?>"
        role="status"
        aria-live="polite"
    >
        <?= e(
            (string) ($message['message'] ?? '')
        ) ?>
    </div>

<?php endforeach; ?>