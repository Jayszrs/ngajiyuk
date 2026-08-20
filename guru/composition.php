<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$year = active_academic_year();
$statement = $pdo->prepare('SELECT level,nama_surah FROM surah_curriculum WHERE tahun_ajaran=? ORDER BY level,urutan');
$statement->execute([$year]);
$targets = [];
foreach ($statement->fetchAll() as $row) { $targets[(int) $row['level']][] = preg_replace('/^Surah\s+/i', '', $row['nama_surah']); }
$ranges = [
    ['Mumtaz', 'Istimewa', '90 - 100', 'A', 'green'],
    ['Jayyid Jiddan', 'Sangat Bagus', '80 - 89,99', 'A-', 'green'],
    ['Jayyid', 'Bagus', '65 - 79,99', 'B', 'blue'],
    ['Maqbul', 'Diterima/Lulus', '50 - 64,99', 'C', 'yellow'],
    ['Dhaif', 'Lemah', '35 - 49,99', 'D', 'yellow'],
    ['Dhaif Jiddan', 'Sangat Lemah', '0 - 34,99', 'E', 'red'],
];
$pageTitle = 'Komposisi Nilai';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Komposisi Nilai</h1><p class="page-description">Panduan skala penilaian harian Tahsin &amp; Tahfidz sesuai standar sekolah.</p></div></div>
<section class="composition-grid">
  <article class="card accent-top"><h2 class="card-title"><?= svg_icon('award') ?> Tabel Komposisi Nilai</h2><p class="card-subtitle">Skala nilai dan predikat yang digunakan sekolah.</p>
    <div class="table-wrap"><table class="table"><thead><tr><th>No</th><th>Kategori</th><th>Arti</th><th>Skala</th><th>Huruf</th></tr></thead><tbody><?php foreach ($ranges as $index => $range): ?><tr><td><?= $index + 1 ?></td><td class="name"><?= e($range[0]) ?></td><td><?= e($range[1]) ?></td><td><strong><?= e($range[2]) ?></strong></td><td><span class="grade grade-<?= e($range[4]) ?>"><?= e($range[3]) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
    <div class="alert alert-info" style="margin-top:14px"><strong>Panduan Penggunaan</strong><br>Gunakan skala nilai ini pada Presensi &amp; Harian, Ujian Kenaikan Level, dan Form Munaqosyah.</div>
  </article>
  <article class="card accent-top accent-green"><h2 class="card-title"><?= svg_icon('activity') ?> Target Hafalan per Level</h2><p class="card-subtitle">Ringkasan surat yang perlu dikuasai pada setiap jenjang tahun <?= e($year) ?>.</p>
    <div class="target-grid"><?php for ($level = 1; $level <= 9; $level++): ?><div class="target-item"><div><span class="level-number"><?= $level ?></span><strong><?= e(level_name($level)) ?></strong></div><p><?= e(implode(', ', $targets[$level] ?? []) ?: 'Belum ada surat') ?></p></div><?php endfor; ?></div>
  </article>
</section>
<section class="card" style="margin-top:18px"><h2 class="card-title"><?= svg_icon('book') ?> Kategori Predikat Kelulusan</h2><p class="card-subtitle">Target umum berdasarkan tingkat kemampuan siswa.</p><div class="grid grid-3"><div class="predicate-card"><span>1</span><strong>Mustawa Ibtida'i</strong><small>Tingkat Pemula</small><p>Juz 30</p></div><div class="predicate-card"><span>2</span><strong>Mustawa Mutawassit</strong><small>Tingkat Menengah</small><p>Juz 30, Ayat Kursi, Ar-Rahman, Al-Mulk, Al-Waqiah, dan surah pilihan</p></div><div class="predicate-card"><span>3</span><strong>Mustawa Mutaqoddim</strong><small>Tingkat Lanjutan</small><p>Juz 30, surah pilihan, dan hadits pilihan</p></div></div></section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
