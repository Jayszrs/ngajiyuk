<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require_guest();
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$record = null;
if ($token !== '') {
    $statement = db()->prepare('SELECT id,user_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
    $statement->execute([hash('sha256', $token)]);
    $record = $statement->fetch() ?: null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $record) {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 8 || $password !== (string) ($_POST['password_confirmation'] ?? '')) {
        $error = 'Password minimal 8 karakter dan konfirmasi harus sama.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $record['user_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$record['user_id']]);
            $pdo->commit();
            audit_event('password_changed', 'success', $record['user_id'], ['source' => 'forgot_password', 'result' => 'Password baru berhasil disimpan']);
            flash('success', 'Password berhasil diganti. Silakan masuk menggunakan password baru.');
            redirect('login.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('Reset password gagal: ' . $exception->getMessage());
            $error = 'Password belum dapat disimpan. Silakan coba kembali.';
        }
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password baru · Catatan Mengaji Digital</title><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><div class="auth-shell"><section class="auth-visual"><a class="brand auth-brand" href="<?= url() ?>"><img src="<?= url('assets/images/logo.png') ?>" alt="Logo sekolah"><span><strong>CATATAN MENGAJI DIGITAL</strong><small>SD ISLAM LABSCHOOL BANI SALEH</small></span></a><div class="auth-message"><span class="auth-feature-icon"><?= svg_icon('shield',28) ?></span><p class="eyebrow">PEMULIHAN AMAN</p><h2>Buat password baru untuk akun Anda.</h2><p>Tautan hanya berlaku satu kali dan akan kedaluwarsa secara otomatis.</p></div></section><main class="auth-panel"><div class="auth-card"><a class="auth-back" href="<?= url('login.php') ?>">← Kembali ke login</a><div class="auth-label">PORTAL SEKOLAH</div><p class="eyebrow">KEAMANAN AKUN</p><h1>Atur password baru</h1><?php if(!$record): ?><div class="alert alert-error">Tautan reset tidak valid, sudah digunakan, atau kedaluwarsa.</div><a class="btn btn-primary" href="<?= url('forgot-password.php') ?>">Buat Permintaan Baru</a><?php else: ?><?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="post" class="grid auth-form"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><div class="field"><label>Password Baru</label><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required></div><div class="field"><label>Konfirmasi Password</label><input class="input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></div><button class="btn btn-primary" type="submit">Simpan Password Baru</button></form><?php endif; ?></div></main></div></body></html>
