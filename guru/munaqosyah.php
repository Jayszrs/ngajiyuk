<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$students = $pdo->query("SELECT id,nama_lengkap,nis,kelas,level FROM students WHERE status='aktif' ORDER BY kelas,nama_lengkap")->fetchAll();
$studentId = (string) ($_GET['student_id'] ?? ($students[0]['id'] ?? ''));
$selected = null;
foreach ($students as $row) { if ($row['id'] === $studentId) { $selected = $row; break; } }
if (!$selected && $students) { $selected = $students[0]; $studentId = $selected['id']; }
$pageTitle = 'Form Munaqosyah';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Form Munaqosyah</h1><p class="page-description">Input mengikuti komponen rekap Excel. Template rapor resmi tetap menggunakan format sekolah.</p></div><a class="btn btn-soft" href="<?= url('guru/reports.php?student_id=' . urlencode($studentId) . '&type=munaqosyah') ?>"><?= svg_icon('printer',16) ?> Preview / Cetak Rapor Resmi</a></div>
<?php if ($selected): ?>
<section class="munaqosyah-grid" data-preview-scope>
  <article class="card">
    <h2 class="card-title"><?= svg_icon('award') ?> Input Nilai Ujian</h2><p class="card-subtitle">Kolom huruf dan Arab dihitung otomatis.</p>
    <form action="<?= url('api/exams/index.php') ?>" method="post" data-ajax class="form-grid" data-score-group>
      <?= csrf_field() ?><input type="hidden" name="action" value="save_munaqosyah">
      <div class="field full"><label>Nama Peserta Didik</label><select class="select" name="student_id" onchange="window.location.href='<?= url('guru/munaqosyah.php?student_id=') ?>'+encodeURIComponent(this.value)"><?php foreach ($students as $student): ?><option value="<?= e($student['id']) ?>" <?= $student['id'] === $studentId ? 'selected' : '' ?>><?= e($student['nama_lengkap'] . ' · ' . $student['nis'] . ' · Kelas ' . $student['kelas']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Tanggal Ujian</label><input class="input" type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div><div class="field"><label>Juz</label><input class="input" name="juz" value="30" required></div>
      <div class="field full"><label>Periode Rapor</label><input class="input" name="bulan_tahun" value="<?= e(format_month_year_id(date('Y-m-d'))) ?>" required></div>
      <div class="section-label full">KATEGORI NILAI</div>
      <?php foreach (['kelancaran'=>'Kelancaran','makhraj'=>'Makhorijul Huruf','tajwid'=>'Hukum Tajwid','hafalan'=>'Sambung Ayat'] as $key=>$label): ?><div class="field"><label><?= e($label) ?></label><input class="input" type="number" name="nilai_<?= e($key) ?>" min="0" max="100" value="80" data-score="<?= e($key) ?>" required></div><?php endforeach; ?>
      <div class="section-label full"><?= svg_icon('users',16) ?> KEPRIBADIAN</div>
      <?php foreach (['akhlaq'=>'Akhlaq','kedisiplinan'=>'Kedisiplinan','kerapihan'=>'Kerapihan'] as $key=>$label): ?><div class="field"><label><?= e($label) ?></label><select class="select" name="<?= e($key) ?>"><option>A - Sangat Baik</option><option selected>B - Baik</option><option>C - Cukup</option><option>D - Perlu Bimbingan</option></select></div><?php endforeach; ?>
      <div class="field full"><label>Catatan Guru</label><textarea class="textarea" name="catatan_guru" placeholder="Evaluasi dan arahan untuk siswa..."></textarea></div>
      <div class="field full"><button class="btn btn-primary" type="submit">Simpan Nilai Munaqosyah</button></div>
    </form>
  </article>
  <article class="card">
    <p class="eyebrow">PREVIEW DATA MUNAQOSYAH</p><h2 style="font-size:21px;margin:2px 0"><?= e($selected['nama_lengkap']) ?></h2><p class="muted" style="font-size:12px"><?= e($selected['kelas']) ?> · NIS <?= e($selected['nis']) ?> · Juz 30</p>
    <div class="score-hero"><div><small>Nilai Rata-rata Otomatis</small><strong data-average>80.00</strong></div><span class="predicate-pill"><span data-predicate>Jayyid Jiddan</span> · جيد جداً</span></div>
    <div class="score-preview"><table class="table"><thead><tr><th>Kategori Nilai</th><th>Angka</th><th>Huruf</th><th>Angka Arab</th><th>Huruf Arab</th></tr></thead><tbody>
      <?php foreach (['kelancaran'=>'Kelancaran','makhraj'=>'Makhorijul Huruf','tajwid'=>'Hukum Tajwid','hafalan'=>'Sambung Ayat'] as $key=>$label): ?><tr><td class="name"><?= e($label) ?></td><td><strong data-score-value="<?= e($key) ?>">80</strong></td><td data-score-words="<?= e($key) ?>">Delapan puluh</td><td data-score-arabic="<?= e($key) ?>">٨٠</td><td dir="rtl" data-score-arabic-words="<?= e($key) ?>">ثمانون</td></tr><?php endforeach; ?>
      <tr class="summary-row"><td class="name">Jumlah</td><td><strong data-total>320</strong></td><td data-total-words>Tiga ratus dua puluh</td><td data-total-arabic>٣٢٠</td><td>-</td></tr>
    </tbody></table></div>
    <div class="personality-preview"><div><small>AKHLAQ</small><strong>B · Baik</strong><span>جيد</span></div><div><small>KEDISIPLINAN</small><strong>B · Baik</strong><span>جيد</span></div><div><small>KERAPIHAN</small><strong>B · Baik</strong><span>جيد</span></div></div>
    <div class="alert alert-success" style="margin-top:16px">Data ini masuk ke template rapor Munaqosyah resmi yang sudah ada.</div>
  </article>
</section>
<?php else: ?><section class="card"><div class="empty"><strong>Belum ada siswa aktif</strong>Tambahkan siswa sebelum mengisi Munaqosyah.</div></section><?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
