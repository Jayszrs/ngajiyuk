<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('orang_tua');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    if ($name === '') {
        flash('error', 'Nama lengkap wajib diisi.');
    } else {
        db()->prepare('UPDATE users SET full_name=?,phone=?,address=?,bio=?,updated_at=NOW() WHERE id=?')->execute([$name,$phone ?: null,$address ?: null,$bio ?: null,$user['id']]);
        audit_event('parent_profile_updated', 'success', $user['id'], ['fields' => ['full_name','phone','address','bio']]);
        flash('success', 'Biodata Orang Tua berhasil diperbarui.');
    }
    redirect('orangtua/profile.php');
}
$user = current_user(true);
$pageTitle = 'Biodata Orang Tua';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">Profil Wali</p><h1 class="page-title">Biodata Orang Tua</h1><p class="page-description">Perbarui identitas dan kontak yang dapat digunakan sekolah.</p></div></div>
<section class="card"><form method="post" class="form-grid"><?= csrf_field() ?><div class="field"><label>Username</label><input class="input" value="<?= e($user['username']) ?>" disabled></div><div class="field"><label>Email</label><input class="input" value="<?= e($user['email'] ?: '-') ?>" disabled></div><div class="field full"><label>Nama Lengkap</label><input class="input" name="full_name" value="<?= e($user['full_name']) ?>" maxlength="190" required></div><div class="field"><label>Nomor Telepon</label><input class="input" name="phone" value="<?= e($user['phone']) ?>" maxlength="100"></div><div class="field full"><label>Alamat</label><textarea class="textarea" name="address"><?= e($user['address']) ?></textarea></div><div class="field full"><label>Keterangan</label><textarea class="textarea" name="bio"><?= e($user['bio']) ?></textarea></div><button class="btn btn-primary field">Simpan Biodata</button></form></section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
