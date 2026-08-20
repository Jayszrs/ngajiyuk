<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$year = trim((string) ($_GET['tahun_ajaran'] ?? active_academic_year()));
if (!valid_academic_year($year)) $year = active_academic_year();
$selected = normalize_class_name((string) ($_GET['kelas'] ?? '1A'));
if (!in_array($selected, all_class_names(), true)) $selected = '1A';
$view = (string) ($_GET['view'] ?? 'scores');

$classStatement = $pdo->prepare(
    "SELECT c.*, u.full_name teacher_name,
            COUNT(CASE WHEN s.status='aktif' THEN 1 END) total,
            SUM(CASE WHEN s.status='aktif' AND s.level=1 THEN 1 ELSE 0 END) level_1,
            SUM(CASE WHEN s.status='aktif' AND s.level=2 THEN 1 ELSE 0 END) level_2,
            SUM(CASE WHEN s.status='aktif' AND s.level=3 THEN 1 ELSE 0 END) level_3,
            SUM(CASE WHEN s.status='aktif' AND s.level=4 THEN 1 ELSE 0 END) level_4,
            SUM(CASE WHEN s.status='aktif' AND s.level=5 THEN 1 ELSE 0 END) level_5,
            SUM(CASE WHEN s.status='aktif' AND s.level=6 THEN 1 ELSE 0 END) level_6,
            SUM(CASE WHEN s.status='aktif' AND s.level=7 THEN 1 ELSE 0 END) level_7,
            SUM(CASE WHEN s.status='aktif' AND s.level=8 THEN 1 ELSE 0 END) level_8,
            SUM(CASE WHEN s.status='aktif' AND s.level=9 THEN 1 ELSE 0 END) level_9
     FROM classes c LEFT JOIN users u ON u.id=c.teacher_id
     LEFT JOIN students s ON s.kelas=c.nama_kelas
     WHERE c.tahun_ajaran=?
     GROUP BY c.id,u.full_name ORDER BY c.tingkat,c.rombel"
);
$classStatement->execute([$year]);
$classes = $classStatement->fetchAll();
$statement = $pdo->prepare("SELECT * FROM students WHERE kelas=? AND status='aktif' ORDER BY nama_lengkap");
$statement->execute([$selected]);
$students = $statement->fetchAll();
$surahStatement = $pdo->prepare('SELECT level,nama_surah,urutan FROM surah_curriculum WHERE tahun_ajaran=? ORDER BY level,urutan');
$surahStatement->execute([$year]);
$surahs = $surahStatement->fetchAll();
$scoreStatement = $pdo->prepare(
    'SELECT t.* FROM laporan_tahsin_tahfidz t JOIN students s ON s.id=t.student_id
     WHERE s.kelas=? AND t.tahun_ajaran=? ORDER BY t.tanggal DESC,t.updated_at DESC'
);
$scoreStatement->execute([$selected,$year]);
$latestScores=[];
foreach($scoreStatement->fetchAll() as $score){if(!isset($latestScores[$score['student_id']][$score['nama_surah']]))$latestScores[$score['student_id']][$score['nama_surah']]=$score;}
$dailyStatement=$pdo->prepare('SELECT d.*,s.nama_lengkap,s.nis FROM daily_student_reports d JOIN students s ON s.id=d.student_id WHERE s.kelas=? ORDER BY d.tanggal DESC,s.nama_lengkap LIMIT 300');
$dailyStatement->execute([$selected]);
$dailyRows=$dailyStatement->fetchAll();

$pageTitle='Data Kelas & Rekap Nilai';
require ROOT_PATH.'/includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">AKADEMIK &amp; TAHFIDZ</p><h1 class="page-title">Data Kelas &amp; Rekap Nilai</h1><p class="page-description">Kelola kelas 1A–6B, impor siswa dari Excel, dan pantau nilai per surat serta riwayat Presensi–Tadarus dalam satu halaman.</p></div><div class="page-actions"><button class="btn btn-soft" type="button" data-modal-open="import-classes"><?= svg_icon('file',16) ?> Impor Excel Siswa</button><form action="<?= url('api/classes/index.php') ?>" method="post" data-ajax><?= csrf_field() ?><input type="hidden" name="action" value="prepare_all"><input type="hidden" name="tahun_ajaran" value="<?= e($year) ?>"><button class="btn btn-outline" type="submit"><?= svg_icon('school',16) ?> Siapkan 1A–6B</button></form><button class="btn btn-primary" type="button" data-modal-open="add-class">+ Tambah Kelas</button></div></div>

<section class="card" style="padding:0;overflow:hidden"><div class="filter-row" style="padding:18px 20px;margin:0"><div><h2 class="card-title" style="margin:0"><?= svg_icon('users') ?> Rekap Kelas 1A–6B</h2><small class="muted">Jumlah siswa dibagi berdasarkan sembilan level Tahfidz.</small></div><form method="get" class="field"><label>TAHUN AJARAN</label><input type="hidden" name="kelas" value="<?= e($selected) ?>"><select class="select" name="tahun_ajaran" onchange="this.form.submit()"><option><?= e($year) ?></option><option><?= e(((int)substr($year,0,4)-1).'/'.((int)substr($year,0,4))) ?></option><option><?= e(((int)substr($year,0,4)+1).'/'.((int)substr($year,0,4)+2)) ?></option></select></form></div><div class="table-wrap" style="border-radius:0;border-left:0;border-right:0;border-bottom:0"><table class="table class-recap-table"><thead><tr><th>Kelas</th><?php for($level=1;$level<=9;$level++):?><th><?= $level<=6?'Level '.$level:'Mustawa '.($level-6) ?></th><?php endfor;?><th>Total</th><th>Wali Kelas</th><th>Aksi</th></tr></thead><tbody><?php foreach($classes as $class):?><tr class="<?= $class['nama_kelas']===$selected?'selected':'' ?>"><td><a class="class-pill" href="<?= url('guru/classes.php?kelas='.$class['nama_kelas'].'&tahun_ajaran='.urlencode($year)) ?>"><?= e($class['nama_kelas']) ?></a></td><?php for($level=1;$level<=9;$level++):?><td><?= (int)$class['level_'.$level] ?: '-' ?></td><?php endfor;?><td><strong><?= (int)$class['total'] ?></strong></td><td><?= e($class['teacher_name']?:'-') ?></td><td><a class="icon-button" href="<?= url('guru/classes.php?kelas='.$class['nama_kelas'].'&tahun_ajaran='.urlencode($year)) ?>" aria-label="Lihat kelas"><?= svg_icon('file',16) ?></a></td></tr><?php endforeach; ?><?php if(!$classes):?><tr><td colspan="13"><div class="empty"><strong>Belum ada master kelas</strong>Klik Siapkan 1A–6B untuk tahun ajaran ini.</div></td></tr><?php endif;?></tbody></table></div></section>

<section class="card" style="margin-top:20px;padding:0;overflow:hidden"><div class="filter-row" style="padding:20px;margin:0"><div><p class="eyebrow">KELAS <?= e($selected) ?></p><h2 style="font-size:25px;margin:2px 0">Data Nilai &amp; Laporan Harian</h2><span class="muted"><?= count($students) ?> siswa tampil dari <?= count($students) ?> siswa.</span></div><div class="tabs"><a class="tab <?= $view==='scores'?'active':'' ?>" href="<?= url('guru/classes.php?kelas='.$selected.'&tahun_ajaran='.urlencode($year).'&view=scores') ?>"><?= svg_icon('book',15) ?> Nilai per Surat</a><a class="tab <?= $view==='daily'?'active':'' ?>" href="<?= url('guru/classes.php?kelas='.$selected.'&tahun_ajaran='.urlencode($year).'&view=daily') ?>"><?= svg_icon('clipboard',15) ?> Presensi &amp; Tadarus</a></div></div>
<?php if($view==='daily'):?><div class="table-wrap" style="border-radius:0;border-width:1px 0 0"><table class="table"><thead><tr><th>Tanggal</th><th>Nama Peserta Didik</th><th>Presensi</th><th>Kegiatan</th><th>Tadarus</th><th>Hafalan</th><th>Catatan</th></tr></thead><tbody><?php foreach($dailyRows as $row):?><tr><td><?= e(format_date_id($row['tanggal'])) ?></td><td class="name"><?= e($row['nama_lengkap']) ?><br><small>NIS <?= e($row['nis']) ?></small></td><td><?= e($row['status_presensi']) ?></td><td><?= e($row['kegiatan']?:'-') ?></td><td><?= e($row['ringkasan_tadarus']?:'-') ?></td><td><?= e($row['ringkasan_hafalan']?:'-') ?></td><td><?= e($row['catatan_guru']?:'-') ?></td></tr><?php endforeach; ?><?php if(!$dailyRows):?><tr><td colspan="7"><div class="empty"><strong>Belum ada laporan harian</strong>Data akan tampil setelah Guru mengisi Presensi &amp; Harian.</div></td></tr><?php endif;?></tbody></table></div>
<?php else:?><div class="table-wrap" style="border-radius:0;border-width:1px 0 0"><table class="table" style="min-width:<?= max(900,360+count($surahs)*720) ?>px"><thead><tr><th rowspan="2">No</th><th rowspan="2">Nama Peserta Didik</th><th rowspan="2">Level</th><?php foreach($surahs as $surah):?><th colspan="7" style="text-align:center;background:#07875d;color:#fff"><?= e($surah['nama_surah']) ?></th><?php endforeach;?></tr><tr><?php foreach($surahs as $_):?><th>Kelancaran</th><th>Makhorijul</th><th>Tajwid</th><th>Sambung</th><th>Jumlah</th><th>Rata-rata</th><th>Ket.</th><?php endforeach;?></tr></thead><tbody><?php foreach($students as $index=>$student):?><tr><td><?= $index+1 ?></td><td class="name"><?= e($student['nama_lengkap']) ?><br><small class="muted">NIS <?= e($student['nis']) ?></small></td><td><?= e(level_name($student['level'])) ?></td><?php foreach($surahs as $surah):$score=$latestScores[$student['id']][$surah['nama_surah']]??null;?><td><?= $score?e((string)$score['nilai_kelancaran']):'-' ?></td><td><?= $score?e((string)$score['nilai_makhraj']):'-' ?></td><td><?= $score?e((string)$score['nilai_tajwid']):'-' ?></td><td><?= $score?e((string)$score['nilai_hafalan']):'-' ?></td><td><?= $score?e((string)($score['nilai_kelancaran']+$score['nilai_makhraj']+$score['nilai_tajwid']+$score['nilai_hafalan'])):'-' ?></td><td><?= $score?e((string)$score['nilai_rata_rata']):'-' ?></td><td><?= $score?e(score_status((float)$score['nilai_rata_rata'],academic_minimum_score('level'))):'-' ?></td><?php endforeach;?></tr><?php endforeach;?><?php if(!$students):?><tr><td colspan="3"><div class="empty"><strong>Belum ada siswa di kelas <?= e($selected) ?></strong>Impor atau tambahkan siswa terlebih dahulu.</div></td></tr><?php endif;?></tbody></table></div><?php endif;?></section>

<div class="modal" id="import-classes"><div class="modal-card"><div class="modal-head"><h2>Impor Excel Siswa</h2><button class="modal-close" type="button" data-modal-close>&times;</button></div><form action="<?= url('api/import/students.php') ?>" method="post" enctype="multipart/form-data" data-ajax class="form-grid"><?= csrf_field() ?><div class="field full"><label>File XLSX/CSV</label><input class="input" type="file" name="file" accept=".xlsx,.csv" required></div><div class="field"><label>Kelas Default</label><select class="select" name="kelas"><?php foreach(all_class_names() as $name):?><option <?= $name===$selected?'selected':'' ?>><?= $name ?></option><?php endforeach;?></select></div><div class="field"><label>Level Default</label><select class="select" name="level"><?php for($level=1;$level<=9;$level++):?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor;?></select></div><button class="btn btn-primary field full">Impor Siswa</button></form></div></div>
<div class="modal" id="add-class"><div class="modal-card"><div class="modal-head"><h2>Tambah Kelas</h2><button class="modal-close" type="button" data-modal-close>&times;</button></div><form action="<?= url('api/classes/index.php') ?>" method="post" data-ajax class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save"><div class="field"><label>Nama Kelas</label><select class="select" name="nama_kelas"><?php foreach(all_class_names() as $name):?><option><?= $name ?></option><?php endforeach;?></select></div><div class="field"><label>Tahun Ajaran</label><input class="input" name="tahun_ajaran" value="<?= e($year) ?>" required></div><button class="btn btn-primary field full">Simpan Kelas</button></form></div></div>
<?php require ROOT_PATH.'/includes/footer.php'; ?>
