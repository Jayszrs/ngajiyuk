<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require_guest();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$success, $message] = login_user((string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($success) { redirect(dashboard_path((string) current_user(true)['role'])); }
    $error = $message;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk ke akun · Catatan Mengaji Digital</title><link rel="icon" href="<?= url('assets/images/favicon.ico') ?>"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body>
<div class="auth-shell"><section class="auth-visual"><a class="brand auth-brand" href="<?= url() ?>"><img src="<?= url('assets/images/logo.png') ?>" alt="Logo sekolah"><span><strong>CATATAN MENGAJI DIGITAL</strong><small>SD ISLAM LABSCHOOL BANI SALEH</small></span></a><div class="auth-message"><span class="auth-feature-icon"><?= svg_icon('book',28) ?></span><p class="eyebrow">PORTAL SEKOLAH TERINTEGRASI</p><h2>Satu catatan, satu arah perkembangan.</h2><p>Masuk untuk mengelola dan memantau perjalanan Tahsin &amp; Tahfidz siswa secara berkelanjutan.</p><div class="auth-points"><span>✓ Laporan tersusun rapi</span><span>✓ Akses sesuai peran</span><span>✓ Data terhubung</span></div></div><small>© <?= date('Y') ?> SD Islam Labschool Bani Saleh</small></section>
<main class="auth-panel"><div class="auth-card"><a class="auth-back" href="<?= url() ?>">← Kembali ke beranda</a><div class="auth-label">PORTAL SEKOLAH</div><p class="eyebrow">SELAMAT DATANG KEMBALI</p><h1>Masuk ke akun</h1><p class="muted">Gunakan akun Guru, Orang Tua, atau Administrator Anda.</p><?php foreach (pull_flashes() as $flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?><?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="grid auth-form"><?= csrf_field() ?><div class="field"><label>Email atau Username</label><input class="input" name="identifier" autocomplete="username" required placeholder="nama@email.com atau username"></div><div class="field"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password" required placeholder="Masukkan password"></div><div class="auth-forgot"><a href="<?= url('forgot-password.php') ?>">Lupa password?</a></div><button class="btn btn-primary btn-block" type="submit">MASUK KE DASHBOARD →</button></form><p class="auth-links">Belum punya akun? <a href="<?= url('register.php') ?>">Buat akun</a></p></div></main></div></body></html>
