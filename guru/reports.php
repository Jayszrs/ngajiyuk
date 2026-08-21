<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$students = $pdo->query("SELECT id,nama_lengkap,nis,kelas,level,foto_url FROM students WHERE status='aktif' ORDER BY kelas,nama_lengkap")->fetchAll();
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$selected = null;
foreach ($students as $student) { if ($student['id'] === $studentId) { $selected = $student; break; } }
$type = (string) ($_GET['type'] ?? 'daily');
if (!in_array($type, ['daily','level','munaqosyah'], true)) { $type = 'daily'; }
$groups = [];
foreach ($students as $student) { $groups[$student['kelas']][] = $student; }
$availableDates = [];
$reportStats = ['daily_dates' => 0, 'daily_surahs' => 0, 'level_results' => 0, 'munaqosyah_results' => 0];
if ($selected) {
    $statement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(DISTINCT tanggal) FROM daily_student_reports WHERE student_id=?) AS daily_dates,
            (SELECT COUNT(DISTINCT nama_surah) FROM laporan_tahsin_tahfidz WHERE student_id=?) AS daily_surahs,
            (SELECT COUNT(*) FROM level_promotion_exams WHERE student_id=?) AS level_results,
            (SELECT COUNT(*) FROM munaqosyah_exams WHERE student_id=?) AS munaqosyah_results'
    );
    $statement->execute([$studentId, $studentId, $studentId, $studentId]);
    $reportStats = array_merge($reportStats, $statement->fetch() ?: []);
}
if ($selected && $type === 'daily') {
    $statement = $pdo->prepare(
        'SELECT tanggal FROM daily_student_reports WHERE student_id=?
         UNION SELECT tanggal FROM laporan_tahsin_tahfidz WHERE student_id=?
         ORDER BY tanggal DESC'
    );
    $statement->execute([$studentId, $studentId]);
    $availableDates = $statement->fetchAll(PDO::FETCH_COLUMN);
}
$reportDate = (string) ($_GET['date'] ?? ($availableDates[0] ?? ''));
$pageTitle = 'Rapor Otomatis Siswa';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">3 Rapor Otomatis</h1><p class="page-description">Lihat keluaran nilai yang otomatis berasal dari tiga form penilaian.</p></div><div class="filter-actions"><select class="select" data-report-class><option value="">Semua Kelas</option><?php foreach (array_keys($groups) as $class): ?><option><?= e($class) ?></option><?php endforeach; ?></select><select class="select" data-report-level><option value="">Semua Jenjang</option><?php for($level=1;$level<=9;$level++): ?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor; ?></select><input class="input" placeholder="Cari siswa..." data-report-search></div></div>
<?php if (!$selected): ?>
<section class="card" style="padding:0;overflow:hidden"><div class="student-picker-title"><?= svg_icon('file') ?><h2>Pilih Siswa</h2></div><div class="report-student-groups"><?php foreach ($groups as $class=>$classStudents): ?><section data-report-group="<?= e($class) ?>"><header><div><span class="class-pill"><?= e($class) ?></span><strong>Kelas <?= e($class) ?></strong></div><span class="badge badge-gray"><?= count($classStudents) ?> Siswa</span></header><div class="report-student-grid"><?php foreach ($classStudents as $student): ?><article class="report-student" data-report-student data-class="<?= e($student['kelas']) ?>" data-level="<?= (int)$student['level'] ?>" data-search="<?= e(strtolower($student['nama_lengkap'].' '.$student['nis'])) ?>"><span class="avatar"><?= e(initials($student['nama_lengkap'],1)) ?></span><div><strong><?= e($student['nama_lengkap']) ?></strong><small>NIS: <?= e($student['nis']) ?> &nbsp; | &nbsp; Kelas <?= e($student['kelas']) ?> &nbsp; | &nbsp; <em><?= e(level_name((int)$student['level'])) ?></em></small></div><a class="btn btn-outline btn-sm" href="<?= url('guru/reports.php?student_id='.urlencode($student['id'])) ?>"><?= svg_icon('file',14) ?> Lihat 3 Rapor Otomatis</a></article><?php endforeach; ?></div></section><?php endforeach; ?><?php if(!$students): ?><div class="empty"><strong>Belum ada siswa aktif</strong>Tambahkan siswa sebelum membuka rapor.</div><?php endif; ?></div></section>
<?php else: ?>
<section class="card"><div class="filter-row"><div class="student-identity"><span class="avatar"><?= e(initials($selected['nama_lengkap'],1)) ?></span><div><p class="eyebrow">RAPOR OTOMATIS</p><h2><?= e($selected['nama_lengkap']) ?></h2><small>NIS <?= e($selected['nis']) ?> · Kelas <?= e($selected['kelas']) ?> · <?= e(level_name((int)$selected['level'])) ?></small></div></div><a class="btn btn-soft" href="<?= url('guru/reports.php') ?>">Pilih Siswa Lain</a></div>
  <div class="report-type-tabs"><?php foreach(['daily'=>['Rapor Hafalan Harian','Presensi & Laporan Harian'],'level'=>['Rapor Hafalan Level','Ujian Kenaikan Level'],'munaqosyah'=>['Rapor Munaqosyah','Form Munaqosyah']] as $key=>$meta): $connected = $key==='daily' ? ((int)$reportStats['daily_dates'].' tanggal · '.(int)$reportStats['daily_surahs'].' surat') : ($key==='level' ? ((int)$reportStats['level_results'].' hasil ujian') : ((int)$reportStats['munaqosyah_results'].' hasil ujian')); ?><a class="report-type <?= $type===$key?'active':'' ?>" href="<?= url('guru/reports.php?student_id='.urlencode($studentId).'&type='.$key) ?>"><?= svg_icon($key==='daily'?'book':'award') ?><span><strong><?= e($meta[0]) ?></strong><small>Nilai dari <?= e($meta[1]) ?></small><small class="report-connection"><?= ($key==='daily' ? (int)$reportStats['daily_dates']+(int)$reportStats['daily_surahs'] : ($key==='level' ? (int)$reportStats['level_results'] : (int)$reportStats['munaqosyah_results'])) > 0 ? 'Tersambung · ' : 'Belum ada data · ' ?><?= e($connected) ?></small></span></a><?php endforeach; ?></div>
  <div class="report-toolbar"><?php if($type==='daily'): ?><label>Tanggal Rapor <input class="input" type="date" value="<?= e($reportDate) ?>" min="<?= e($availableDates ? end($availableDates) : '') ?>" max="<?= e($availableDates[0] ?? '') ?>" onchange="window.location.href='<?= url('guru/reports.php?student_id='.urlencode($studentId).'&type=daily&date=') ?>'+this.value"></label><?php endif; ?><a class="btn btn-soft" href="<?= url('api/reports/export.php?student_id='.urlencode($studentId)) ?>">Download Excel</a><a class="btn btn-primary" target="_blank" href="<?= url('report.php?student_id='.urlencode($studentId).'&type='.$type.($reportDate?'&date='.urlencode($reportDate):'')) ?>"><?= svg_icon('printer',15) ?> Cetak / Simpan PDF</a></div>
</section>
<section class="report-preview-shell"><iframe title="Preview rapor resmi" src="<?= url('report.php?student_id='.urlencode($studentId).'&type='.$type.'&embed=1'.($reportDate?'&date='.urlencode($reportDate):'')) ?>"></iframe></section>
<?php endif; ?>
<script>document.addEventListener('DOMContentLoaded',function(){var c=document.querySelector('[data-report-class]'),l=document.querySelector('[data-report-level]'),q=document.querySelector('[data-report-search]');function filter(){document.querySelectorAll('[data-report-student]').forEach(function(x){x.hidden=!!((c.value&&x.dataset.class!==c.value)||(l.value&&x.dataset.level!==l.value)||(q.value&&!x.dataset.search.includes(q.value.toLowerCase())));});document.querySelectorAll('[data-report-group]').forEach(function(g){g.hidden=!g.querySelector('[data-report-student]:not([hidden])');});}[c,l,q].forEach(function(x){if(x)x.addEventListener(x===q?'input':'change',filter);});});</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
