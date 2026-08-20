<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$year = active_academic_year();
$minimum = academic_minimum_score('level');
$students = $pdo->query("SELECT id,nama_lengkap,nis,kelas,level FROM students WHERE status='aktif' AND level<9 ORDER BY kelas,nama_lengkap")->fetchAll();
$studentId = (string) ($_GET['student_id'] ?? ($students[0]['id'] ?? ''));
$selected = null;
foreach ($students as $row) { if ($row['id'] === $studentId) { $selected = $row; break; } }
if (!$selected && $students) { $selected = $students[0]; $studentId = $selected['id']; }
$surahs = [];
if ($selected) {
    $statement = $pdo->prepare('SELECT nama_surah FROM surah_curriculum WHERE tahun_ajaran=? AND level=? ORDER BY urutan');
    $statement->execute([$year, $selected['level']]);
    $surahs = $statement->fetchAll(PDO::FETCH_COLUMN);
}
$pageTitle = 'Ujian Kenaikan Level';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Ujian Kenaikan Level</h1><p class="page-description">Form rekap nilai, terbilang, keterangan, jumlah, rata-rata, dan hasil kenaikan level. KKM <?= e(number_format($minimum, 0, ',', '.')) ?>.</p></div><div class="page-actions"><a class="btn btn-soft" href="<?= url('report.php?student_id=' . urlencode($studentId) . '&type=level') ?>"><?= svg_icon('file',16) ?> Download Rapor</a><a class="btn btn-primary" href="<?= url('guru/reports.php?student_id=' . urlencode($studentId) . '&type=level') ?>"><?= svg_icon('printer',16) ?> Preview / Cetak Rapor</a></div></div>
<?php if ($selected): ?>
<section class="card accent-top accent-purple" data-preview-scope>
  <form action="<?= url('api/exams/index.php') ?>" method="post" data-ajax class="form-grid form-grid-3" data-score-group>
    <?= csrf_field() ?><input type="hidden" name="action" value="save_level"><input type="hidden" name="tahun_ajaran" value="<?= e($year) ?>">
    <div class="form-section-title span-2"><h2><?= svg_icon('award') ?> Input Nilai Ujian</h2></div><div class="prediction"><small>PREDIKSI HASIL</small><strong><span data-average>80.00</span> · <span data-pass-label data-minimum="<?= e((string) $minimum) ?>">Naik</span></strong></div>
    <div class="field span-2"><label>Siswa</label><select class="select" name="student_id" onchange="window.location.href='<?= url('guru/level-exams.php?student_id=') ?>'+encodeURIComponent(this.value)"><?php foreach ($students as $student): ?><option value="<?= e($student['id']) ?>" <?= $student['id'] === $studentId ? 'selected' : '' ?>><?= e($student['nama_lengkap'] . ' · ' . level_name((int) $student['level'])) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Tanggal Ujian</label><input class="input" type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
    <div class="field"><label>Jenjang Asal</label><input class="input" value="<?= e(level_name((int) $selected['level'])) ?>" readonly></div>
    <div class="field"><label>Jenjang Tujuan</label><select class="select" name="level_tujuan"><?php for ($level=(int)$selected['level']+1; $level<=9; $level++): ?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor; ?></select></div>
    <div class="field"><label>Tahun Ajaran</label><input class="input" value="<?= e($year) ?>" readonly></div>
    <div class="field full"><label>Surat Ujian (<?= e(level_name((int) $selected['level'])) ?>)</label><select class="select" name="nama_surah" required><option value="">Pilih surat</option><?php foreach ($surahs as $surah): ?><option><?= e($surah) ?></option><?php endforeach; ?></select></div>
    <?php foreach (['kelancaran'=>'Kelancaran','makhraj'=>'Makhorijul Huruf','tajwid'=>'Hukum Tajwid','hafalan'=>'Sambung Ayat'] as $key=>$label): ?><div class="field"><label><?= e($label) ?></label><input class="input" type="number" name="nilai_<?= e($key) ?>" min="0" max="100" value="80" data-score="<?= e($key) ?>" required></div><?php endforeach; ?>
    <div class="score-live-table full"><table class="table"><thead><tr><th>Komponen</th><th>Nilai</th><th>Terbilang</th><th>Keterangan</th></tr></thead><tbody>
      <?php foreach (['kelancaran'=>'Kelancaran','makhraj'=>'Makhorijul Huruf','tajwid'=>'Hukum Tajwid','hafalan'=>'Sambung Ayat'] as $key=>$label): ?><tr><td class="name"><?= e($label) ?></td><td><strong data-score-value="<?= e($key) ?>">80</strong></td><td data-score-words="<?= e($key) ?>">Delapan puluh</td><td data-score-status="<?= e($key) ?>">Tercapai</td></tr><?php endforeach; ?>
      <tr class="summary-row"><td class="name">Jumlah</td><td><strong data-total>320</strong></td><td colspan="2">Rata-rata <strong data-average>80.00</strong></td></tr>
      <tr class="summary-row"><td class="name">Kategori</td><td colspan="2"><strong data-pass-label data-minimum="<?= e((string) $minimum) ?>">Naik</strong></td><td>Naik ke <?= e(level_name((int) $selected['level'] + 1)) ?></td></tr>
    </tbody></table></div>
    <div class="field full"><label>Catatan Guru</label><textarea class="textarea" name="catatan_guru" placeholder="Evaluasi dan rekomendasi setelah ujian..."></textarea></div>
    <div class="field full"><button class="btn btn-primary" type="submit">Simpan Hasil Ujian</button></div>
  </form>
</section>
<?php else: ?><section class="card"><div class="empty"><strong>Tidak ada siswa yang dapat diuji</strong>Siswa Level 9 sudah berada pada level terakhir.</div></section><?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
