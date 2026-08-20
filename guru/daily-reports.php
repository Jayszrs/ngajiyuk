<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$students = $pdo->query("SELECT id,nama_lengkap,nis,kelas,level FROM students WHERE status='aktif' ORDER BY kelas,nama_lengkap")->fetchAll();
$studentId = (string) ($_GET['student_id'] ?? ($students[0]['id'] ?? ''));
$selected = null;
foreach ($students as $student) { if ($student['id'] === $studentId) { $selected = $student; break; } }
if (!$selected && $students) { $selected = $students[0]; $studentId = $selected['id']; }
$daily = [];
$surahs = [];
if ($selected) {
    $statement = $pdo->prepare('SELECT * FROM daily_student_reports WHERE student_id=? ORDER BY tanggal DESC LIMIT 366');
    $statement->execute([$studentId]);
    $daily = $statement->fetchAll();
    $statement = $pdo->prepare('SELECT nama_surah FROM surah_curriculum WHERE tahun_ajaran=? AND level=? ORDER BY urutan');
    $statement->execute([active_academic_year(), $selected['level']]);
    $surahs = $statement->fetchAll(PDO::FETCH_COLUMN);
}
$pageTitle = 'Presensi & Laporan Harian';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Presensi &amp; Laporan Harian</h1><p class="page-description">Rekap presensi, kegiatan, tadarus, hafalan, dan catatan Guru.</p></div>
  <div class="page-actions">
    <a class="btn btn-soft" href="<?= url('api/reports/export.php?student_id=' . urlencode($studentId)) ?>"><?= svg_icon('file', 16) ?> Download Excel</a>
    <a class="btn btn-primary" href="<?= url('guru/reports.php?student_id=' . urlencode($studentId) . '&type=daily') ?>"><?= svg_icon('printer', 16) ?> Preview / Cetak Rapor Resmi</a>
  </div>
</div>
<?php if ($selected): ?>
<section class="card accent-top accent-green">
  <h2 class="card-title"><?= svg_icon('users') ?> Input Laporan Harian</h2>
  <form action="<?= url('api/reports/index.php') ?>" method="post" data-ajax class="form-grid form-grid-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_daily">
    <div class="field span-2"><label>Siswa</label><select class="select" name="student_id" required onchange="window.location.href='<?= url('guru/daily-reports.php?student_id=') ?>'+encodeURIComponent(this.value)"><?php foreach ($students as $student): ?><option value="<?= e($student['id']) ?>" <?= $student['id'] === $studentId ? 'selected' : '' ?>><?= e($student['nama_lengkap'] . ' · Kelas ' . $student['kelas'] . ' · NIS ' . $student['nis']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Tanggal</label><input class="input" type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
    <div class="field"><label>Status Presensi</label><select class="select" name="status_presensi"><?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $status): ?><option><?= e($status) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Kegiatan Harian</label><input class="input" name="kegiatan" placeholder="Contoh: Tahsin dan murojaah"></div>
    <div class="field"><label>Ringkasan Tadarus</label><input class="input" name="ringkasan_tadarus" placeholder="Contoh: Al-Baqarah ayat 1–10"></div>
    <div class="field"><label>Ringkasan Hafalan</label><input class="input" name="ringkasan_hafalan" placeholder="Contoh: Al-Mulk ayat 1–5"></div>
    <div class="field span-2"><label>Catatan Guru</label><textarea class="textarea" name="catatan_guru" placeholder="Perkembangan atau arahan untuk orang tua..."></textarea></div>
    <div class="field full"><button class="btn btn-accent" type="submit"><?= svg_icon('file', 16) ?> Simpan Laporan Harian</button></div>
  </form>
  <details class="advanced-form">
    <summary>Tambahkan nilai Tahsin &amp; Tahfidz per surat</summary>
    <form action="<?= url('api/reports/index.php') ?>" method="post" data-ajax class="form-grid form-grid-3" data-score-group>
      <?= csrf_field() ?><input type="hidden" name="action" value="save_tahsin"><input type="hidden" name="student_id" value="<?= e($studentId) ?>">
      <div class="field"><label>Tanggal</label><input class="input" type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
      <div class="field span-2"><label>Surat sesuai <?= e(level_name((int) $selected['level'])) ?></label><select class="select" name="nama_surah" required><option value="">Pilih surat</option><?php foreach ($surahs as $surah): ?><option><?= e($surah) ?></option><?php endforeach; ?></select></div>
      <?php foreach (['kelancaran' => 'Kelancaran', 'makhraj' => 'Makhorijul Huruf', 'tajwid' => 'Hukum Tajwid', 'hafalan' => 'Sambung Ayat'] as $key => $label): ?><div class="field"><label><?= e($label) ?></label><input class="input" type="number" name="nilai_<?= e($key) ?>" min="0" max="100" value="80" data-score="<?= e($key) ?>" required></div><?php endforeach; ?>
      <div class="field"><label>Rata-rata</label><output class="score-output" data-average>80.00</output></div>
      <div class="field span-2"><label>Ayat / Halaman</label><input class="input" name="ayat"></div>
      <div class="field full"><label>Keterangan</label><textarea class="textarea" name="keterangan"></textarea></div>
      <div class="field full"><button class="btn btn-soft" type="submit">Simpan Nilai Surat</button></div>
    </form>
  </details>
</section>
<section class="card" style="margin-top:20px;padding:0;overflow:hidden">
  <div class="student-report-head"><span class="avatar small"><?= e(initials($selected['nama_lengkap'])) ?></span><div><h2>Rapor Harian <?= e($selected['nama_lengkap']) ?></h2><p>NIS <?= e($selected['nis']) ?> · Kelas <?= e($selected['kelas']) ?></p></div></div>
  <div class="table-wrap borderless"><table class="table"><thead><tr><th>Tanggal</th><th>Presensi</th><th>Kegiatan</th><th>Tadarus</th><th>Hafalan</th><th>Catatan</th></tr></thead><tbody>
    <?php foreach ($daily as $row): ?><tr><td><?= e(format_date_id($row['tanggal'])) ?></td><td><span class="badge badge-green"><?= e($row['status_presensi']) ?></span></td><td><?= e($row['kegiatan'] ?: '-') ?></td><td><?= e($row['ringkasan_tadarus'] ?: '-') ?></td><td><?= e($row['ringkasan_hafalan'] ?: '-') ?></td><td><?= e($row['catatan_guru'] ?: '-') ?></td></tr><?php endforeach; ?>
    <?php if (!$daily): ?><tr><td colspan="6"><div class="empty"><?= svg_icon('file', 40) ?><strong>Belum ada laporan harian.</strong>Data akan tampil setelah laporan pertama disimpan.</div></td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php else: ?><section class="card"><div class="empty"><strong>Belum ada siswa aktif</strong>Tambahkan atau impor siswa terlebih dahulu.</div></section><?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
