<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();

$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'aktif'")->fetchColumn();
$statement = $pdo->prepare('SELECT COUNT(DISTINCT student_id) FROM laporan_tadarus_pagi WHERE tanggal = CURDATE()');
$statement->execute();
$tadarusToday = (int) $statement->fetchColumn();
$statement = $pdo->prepare('SELECT COUNT(DISTINCT student_id) FROM laporan_tahsin_tahfidz WHERE tanggal = CURDATE()');
$statement->execute();
$tahsinToday = (int) $statement->fetchColumn();
$reportedToday = (int) $pdo->query('SELECT COUNT(DISTINCT student_id) FROM daily_student_reports WHERE tanggal = CURDATE()')->fetchColumn();
$notReported = max(0, $studentCount - $reportedToday);

$weekly = array_fill(1, 6, 0);
$statement = $pdo->prepare(
    'SELECT WEEKDAY(tanggal) + 1 weekday_number, COUNT(DISTINCT student_id) total
     FROM daily_student_reports
     WHERE tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                       AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 5 DAY)
     GROUP BY WEEKDAY(tanggal)'
);
$statement->execute();
foreach ($statement->fetchAll() as $row) {
    $weekly[(int) $row['weekday_number']] = (int) $row['total'];
}
$maxWeekly = max(1, $studentCount);
$dayLabels = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab'];

$students = $pdo->query(
    "SELECT id, nama_lengkap, nis, kelas, level, foto_url
     FROM students WHERE status = 'aktif'
     ORDER BY kelas, nama_lengkap LIMIT 6"
)->fetchAll();
$recent = $pdo->query(
    'SELECT d.tanggal, d.status_presensi, d.kegiatan, s.nama_lengkap, s.kelas
     FROM daily_student_reports d JOIN students s ON s.id = d.student_id
     ORDER BY d.updated_at DESC LIMIT 5'
)->fetchAll();

$pageTitle = 'Dashboard';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div><h1 class="page-title">Dashboard</h1><p class="page-description">Kelola laporan, pantau performa, dan bantu hafalan siswa dengan mudah.</p></div>
</div>

<section class="grid grid-4">
    <article class="stat green"><small>Total Siswa</small><strong><?= $studentCount ?></strong><span>Data siswa aktif sekolah</span></article>
    <article class="stat"><small>Tadarus Selesai</small><strong><?= $tadarusToday ?></strong><span><span class="badge badge-green"><?= $studentCount ? round($tadarusToday / $studentCount * 100) : 0 ?>%</span> dari total siswa</span></article>
    <article class="stat"><small>Tahsin Selesai</small><strong><?= $tahsinToday ?></strong><span><span class="badge badge-blue"><?= $studentCount ? round($tahsinToday / $studentCount * 100) : 0 ?>%</span> laporan hari ini</span></article>
    <article class="stat"><small>Belum Laporan</small><strong><?= $notReported ?></strong><span><span class="badge badge-yellow">Butuh Perhatian</span></span></article>
</section>

<section class="grid" style="grid-template-columns:minmax(0,1.7fr) minmax(280px,.8fr);margin-top:20px">
    <article class="card"><div class="filter-row"><h2 class="card-title" style="margin:0">Performa Mingguan</h2><span class="badge badge-gray">Mingguan</span></div><div class="bar-chart"><?php foreach ($dayLabels as $number => $label): $height = max(4, round($weekly[$number] / $maxWeekly * 100)); ?><div class="bar-item"><div class="bar <?= $number === (int) date('N') ? 'accent' : '' ?>" style="height:<?= $height ?>%" title="<?= $weekly[$number] ?> laporan"></div><span><?= $label ?></span></div><?php endforeach; ?></div></article>
    <article class="card"><h2 class="card-title">Tindakan Cepat</h2><div class="quick-list"><a class="quick-link" href="<?= url('guru/daily-reports.php') ?>"><span class="icon-box"><?= svg_icon('file') ?></span><span><strong>Presensi &amp; Harian</strong><br><small class="muted">Isi presensi dan laporan harian siswa</small></span></a><a class="quick-link" href="<?= url('guru/students.php') ?>"><span class="icon-box" style="color:#e65f00;background:#fff3e8"><?= svg_icon('users') ?></span><span><strong>Kelola Siswa</strong><br><small class="muted">Tambah atau edit data siswa</small></span></a></div></article>
</section>

<section class="grid grid-2" style="margin-top:20px">
    <article class="card"><div class="filter-row"><h2 class="card-title" style="margin:0">Daftar Siswa Kelas</h2><a class="muted" href="<?= url('guru/students.php') ?>">+ Lihat Semua</a></div><div class="compact-list"><?php foreach ($students as $student): ?><a class="compact-item" href="<?= url('guru/daily-reports.php?student_id=' . e($student['id'])) ?>"><span><strong><?= e($student['nama_lengkap']) ?></strong><br><small class="muted">NIS <?= e($student['nis']) ?> · Kelas <?= e($student['kelas']) ?></small></span><span class="badge badge-blue"><?= e(level_name($student['level'])) ?></span></a><?php endforeach; ?><?php if (!$students): ?><div class="empty"><strong>Belum ada siswa</strong>Tambahkan atau impor data siswa.</div><?php endif; ?></div></article>
    <article class="card"><h2 class="card-title">Progres Harian</h2><div class="compact-list"><?php foreach ($recent as $item): ?><div class="compact-item"><span><strong><?= e($item['nama_lengkap']) ?></strong><br><small class="muted"><?= e($item['kegiatan'] ?: 'Laporan harian') ?> · <?= e(format_date_id($item['tanggal'])) ?></small></span><span class="badge <?= $item['status_presensi'] === 'Hadir' ? 'badge-green' : 'badge-yellow' ?>"><?= e($item['status_presensi']) ?></span></div><?php endforeach; ?><?php if (!$recent): ?><div class="empty"><strong>Belum ada progres</strong>Laporan terbaru akan tampil di sini.</div><?php endif; ?></div></article>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
