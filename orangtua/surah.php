<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('orang_tua');
$studentId = parent_student_id($user['id']);
$student = null;
$surahs = [];
if ($studentId) {
    $stmt = db()->prepare('SELECT id,nama_lengkap,nis,kelas,level FROM students WHERE id=?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: null;
    if ($student) {
        $stmt = db()->prepare('SELECT * FROM surah_curriculum WHERE tahun_ajaran=? AND level=? ORDER BY urutan');
        $stmt->execute([active_academic_year(), $student['level']]);
        $surahs = $stmt->fetchAll();
    }
}
$pageTitle = 'Data Surat';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">Target Hafalan Anak</p><h1 class="page-title">Data Surat</h1><p class="page-description">Orang tua hanya melihat kurikulum surat sesuai level anak yang terhubung.</p></div></div>
<section class="card"><?php if (!$student): ?><div class="empty"><strong>Belum ada anak terhubung</strong>Hubungi Administrator dengan membawa NIS anak.</div><?php else: ?><div class="page-head" style="margin:0 0 18px"><div><h2 class="card-title"><?= e(level_name($student['level'])) ?></h2><p class="muted"><?= e($student['nama_lengkap']) ?> &middot; Kelas <?= e($student['kelas']) ?> &middot; Tahun <?= e(active_academic_year()) ?></p></div><span class="badge badge-blue"><?= count($surahs) ?> surat</span></div><div class="compact-list"><?php foreach ($surahs as $surah): ?><div class="compact-item"><span><strong><?= (int) $surah['urutan'] ?>.</strong> <?= e($surah['nama_surah']) ?></span></div><?php endforeach; ?><?php if (!$surahs): ?><div class="empty">Belum ada data surat pada level ini.</div><?php endif; ?></div><?php endif; ?></section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
