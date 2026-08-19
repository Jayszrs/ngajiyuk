<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('orang_tua');
$ranges = [['Mumtaz','Istimewa','90 - 100','A'],['Jayyid Jiddan','Sangat Bagus','80 - 89,99','A-'],['Jayyid','Bagus','65 - 79,99','B'],['Maqbul','Diterima/Lulus','50 - 64,99','C'],['Dhaif','Lemah','35 - 49,99','D'],['Dhaif Jiddan','Sangat Lemah','0 - 34,99','E']];
$pageTitle = 'Komposisi Nilai';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head"><div><p class="eyebrow">Panduan Penilaian</p><h1 class="page-title">Komposisi Nilai</h1><p class="page-description">Acuan penilaian Tahsin dan Tahfizh yang digunakan sekolah.</p></div></div>
<section class="card"><div class="table-wrap"><table class="table"><thead><tr><th>No</th><th>Kategori</th><th>Arti</th><th>Skala Nilai</th><th>Huruf</th></tr></thead><tbody><?php foreach ($ranges as $index => $range): ?><tr><td><?= $index + 1 ?></td><td class="name"><?= e($range[0]) ?></td><td><?= e($range[1]) ?></td><td><strong><?= e($range[2]) ?></strong></td><td><span class="badge badge-green"><?= e($range[3]) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
