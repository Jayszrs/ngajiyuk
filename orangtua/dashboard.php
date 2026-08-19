<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('orang_tua');
$pdo = db();
$studentId = parent_student_id($user['id']);
$student = null;
$daily = [];
$levelExam = null;
$munaqosyah = null;
if ($studentId) {
    $stmt = $pdo->prepare('SELECT s.*, u.full_name AS teacher_name FROM students s LEFT JOIN users u ON u.id=s.teacher_id WHERE s.id=?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: null;
    $stmt = $pdo->prepare('SELECT * FROM daily_student_reports WHERE student_id=? ORDER BY tanggal DESC LIMIT 12');
    $stmt->execute([$studentId]);
    $daily = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT * FROM level_promotion_exams WHERE student_id=? ORDER BY tanggal DESC LIMIT 1');
    $stmt->execute([$studentId]);
    $levelExam = $stmt->fetch() ?: null;
    $stmt = $pdo->prepare('SELECT * FROM munaqosyah_exams WHERE student_id=? ORDER BY tanggal DESC LIMIT 1');
    $stmt->execute([$studentId]);
    $munaqosyah = $stmt->fetch() ?: null;
}
$pageTitle = 'Dashboard Anak';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">Portal Orang Tua</p><h1 class="page-title">Perkembangan Anak</h1><p class="page-description">Pantau presensi, hafalan, nilai, ujian level, dan Munaqosyah anak.</p></div></div>
<?php if (!$student): ?>
<section class="card"><div class="empty"><strong>Akun belum terhubung dengan siswa</strong>Hubungi Administrator dan siapkan NIS anak untuk proses penghubungan.</div></section>
<?php else: ?>
<section class="card"><div class="page-head" style="margin:0"><div><p class="eyebrow">Kelas <?= e($student['kelas']) ?></p><h2 class="card-title"><?= e($student['nama_lengkap']) ?></h2><p class="muted">NIS <?= e($student['nis']) ?> &middot; <?= e(level_name($student['level'])) ?> &middot; Guru <?= e($student['teacher_name'] ?: '-') ?></p></div><span class="badge badge-green"><?= e(ucfirst($student['status'])) ?></span></div></section>
<section class="grid grid-3" style="margin-top:20px">
  <article class="stat green"><small>Laporan Harian</small><strong><?= count($daily) ?></strong><span>12 laporan terbaru</span></article>
  <article class="stat"><small>Ujian Level</small><strong><?= e($levelExam['nilai_rata_rata'] ?? '-') ?></strong><span><?= e($levelExam['status'] ?? 'Belum ada hasil') ?></span></article>
  <article class="stat"><small>Munaqosyah</small><strong><?= $munaqosyah ? e((string) ((json_decode($munaqosyah['hasil_ujian'], true)['nilaiRataRata'] ?? '-'))) : '-' ?></strong><span><?= e($munaqosyah['status'] ?? 'Belum ada hasil') ?></span></article>
</section>
<section class="card" style="margin-top:20px"><div class="page-head" style="margin:0 0 16px"><h2 class="card-title">Rapor Resmi</h2><div class="actions"><a class="btn btn-soft" href="<?= url('report.php?student_id=' . urlencode($student['id']) . '&type=daily') ?>">Rapor Harian</a><a class="btn btn-soft" href="<?= url('report.php?student_id=' . urlencode($student['id']) . '&type=level') ?>">Rapor Level</a><a class="btn btn-primary" href="<?= url('report.php?student_id=' . urlencode($student['id']) . '&type=munaqosyah') ?>">Rapor Munaqosyah</a></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Tanggal</th><th>Presensi</th><th>Kegiatan</th><th>Tadarus</th><th>Hafalan</th><th>Catatan</th></tr></thead><tbody><?php foreach ($daily as $row): ?><tr><td><?= e(format_date_id($row['tanggal'])) ?></td><td><span class="badge badge-green"><?= e($row['status_presensi']) ?></span></td><td><?= e($row['kegiatan']) ?></td><td><?= e($row['ringkasan_tadarus']) ?></td><td><?= e($row['ringkasan_hafalan']) ?></td><td><?= e($row['catatan_guru']) ?></td></tr><?php endforeach; ?><?php if (!$daily): ?><tr><td colspan="6"><div class="empty">Belum ada laporan harian.</div></td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
