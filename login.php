<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require_guest();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$success, $message] = login_user((string) ($_POST['identifier'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($success) redirect(dashboard_path((string) current_user(true)['role']));
    $error = $message;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk ke akun · NgajiYuk</title><link rel="icon" href="<?= url('assets/images/favicon.ico') ?>"><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><div class="auth-shell"><section class="auth-visual"><a class="brand" href="<?= url() ?>"><img src="<?= url('assets/images/logo.png') ?>" alt="Logo"><span><strong>NGAJIYUK</strong><small style="color:#d4f6e5">SD ISLAM LABSCHOOL BANI SALEH</small></span></a><div class="auth-message"><div class="icon-box">▤</div><p class="eyebrow" style="color:#ffe477;margin-top:28px">Portal Sekolah Terintegrasi</p><h2>Satu catatan, satu arah perkembangan.</h2><p style="font-size:18px">Masuk untuk mengelola dan memantau perjalanan Tahsin & Tahfizh siswa secara berkelanjutan.</p></div><small>© <?= date('Y') ?> SD Islam Labschool Bani Saleh</small></section><main class="auth-panel"><div class="auth-card"><a class="auth-back" href="<?= url() ?>">← Kembali ke beranda</a><p class="eyebrow" style="margin-top:28px">Selamat Datang Kembali</p><h1>Masuk ke akun</h1><p class="muted">Gunakan akun Guru, Orang Tua, atau Administrator Anda.</p><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="grid" style="margin-top:30px"><?= csrf_field() ?><div class="field"><label>Email atau Username</label><input class="input" name="identifier" autocomplete="username" required placeholder="nama@email.com atau username"></div><div class="field"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password" required placeholder="Masukkan password"></div><div style="text-align:right"><a href="<?= url('forgot-password.php') ?>" style="color:var(--green);font-weight:800">Lupa password?</a></div><button class="btn btn-primary btn-block" type="submit">↪ Masuk ke Dashboard</button></form><p class="auth-links">Belum punya akun? <a href="<?= url('register.php') ?>">Buat akun</a></p><div class="alert alert-info" style="margin-top:28px;font-size:12px"><strong>Login awal instalasi:</strong> username <code>admin</code>, password <code>Admin123!</code>. Ganti segera melalui Admin.</div></div></main></div></body></html>

