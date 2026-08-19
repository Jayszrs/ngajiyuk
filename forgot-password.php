<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php'; require_guest();
$notice = null; $resetLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf(); $identifier=trim((string)($_POST['identifier']??''));
  $stmt=db()->prepare('SELECT id,email FROM users WHERE LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?) LIMIT 1');$stmt->execute([$identifier,$identifier]);$account=$stmt->fetch();
  if($account){$token=bin2hex(random_bytes(32));db()->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')->execute([$account['id'],hash('sha256',$token)]);$resetLink=url('reset-password.php?token='.$token);audit_event('password_reset_requested','success',$account['id'],['delivery'=>$account['email']?'local_link_email_not_configured':'administrator']);}
  $notice='Jika akun ditemukan, permintaan reset telah dibuat. Pada instalasi XAMPP tanpa SMTP, gunakan tautan lokal di bawah atau hubungi Administrator.';
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Lupa password · NgajiYuk</title><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>"></head><body><div class="auth-shell"><section class="auth-visual"><a class="brand" href="<?= url() ?>"><img src="<?= url('assets/images/logo.png') ?>" alt="Logo"><span><strong>NGAJIYUK</strong><small style="color:#d4f6e5">SD ISLAM LABSCHOOL BANI SALEH</small></span></a><div class="auth-message"><p class="eyebrow" style="color:#ffe477">Pemulihan Akun</p><h2>Kembali mengelola perjalanan belajar.</h2></div></section><main class="auth-panel"><div class="auth-card"><a class="auth-back" href="<?= url('login.php') ?>">← Kembali ke login</a><p class="eyebrow" style="margin-top:28px">Keamanan Akun</p><h1>Lupa password</h1><p class="muted">Masukkan email atau username yang terdaftar.</p><?php if($notice):?><div class="alert alert-success"><?= e($notice) ?></div><?php endif;?><?php if($resetLink):?><a class="btn btn-primary btn-block" href="<?= e($resetLink) ?>">Buka Form Password Baru</a><?php else:?><form method="post" class="grid" style="margin-top:26px"><?= csrf_field() ?><div class="field"><label>Email atau Username</label><input class="input" name="identifier" required></div><button class="btn btn-primary" type="submit">Buat Permintaan Reset</button></form><?php endif;?></div></main></div></body></html>

