<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$user = $user ?? current_user();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#184d3a">
  <title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
  <link rel="icon" href="<?= url('assets/images/favicon.ico') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body>
<?php if ($user): ?>
  <button class="mobile-toggle screen-only" type="button" data-sidebar-toggle aria-label="Buka menu">☰</button>
  <?php require ROOT_PATH . '/includes/sidebar.php'; ?>
  <header class="topbar screen-only">
    <div class="account">
      <div class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
      <div class="account-copy"><strong><?= e($user['full_name']) ?></strong><br><small class="muted"><?= e($user['username']) ?></small></div>
      <span class="role-badge badge-green"><?= e(str_replace('_', ' ', $user['role'])) ?></span>
    </div>
  </header>
  <main class="main">
<?php endif; ?>
<?php foreach (pull_flashes() as $message): ?>
  <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
<?php endforeach; ?>

