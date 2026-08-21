<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';

$user = require_login();
$pdo = db();
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'daily'));
$requestedDate = trim((string) ($_GET['date'] ?? ''));

if (!in_array($type, ['daily', 'level', 'munaqosyah'], true) || !can_access_student($user, $studentId)) {
    http_response_code(403);
    exit('Akses rapor ditolak.');
}

function report_score(mixed $value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) return '-';
    $number = (float) $value;
    return floor($number) === $number ? (string) (int) $number : rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
}

function report_number_words(int $number): string
{
    $number = max(0, $number);
    $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if ($number < 12) return $number === 0 ? 'nol' : $words[$number];
    if ($number < 20) return report_number_words($number - 10) . ' belas';
    if ($number < 100) return report_number_words(intdiv($number, 10)) . ' puluh' . ($number % 10 ? ' ' . report_number_words($number % 10) : '');
    if ($number < 200) return 'seratus' . ($number > 100 ? ' ' . report_number_words($number - 100) : '');
    if ($number < 1000) return report_number_words(intdiv($number, 100)) . ' ratus' . ($number % 100 ? ' ' . report_number_words($number % 100) : '');
    return (string) $number;
}

function report_arabic_digits(int $number): string
{
    return strtr((string) max(0, $number), ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
}

function report_arabic_words(int $number): string
{
    $number = max(0, min(100, $number));
    $units = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
    $special = [0=>'صفر',10=>'عشرة',11=>'أحد عشر',12=>'اثنا عشر',13=>'ثلاثة عشر',14=>'أربعة عشر',15=>'خمسة عشر',16=>'ستة عشر',17=>'سبعة عشر',18=>'ثمانية عشر',19=>'تسعة عشر'];
    $tens = [20=>'عشرون',30=>'ثلاثون',40=>'أربعون',50=>'خمسون',60=>'ستون',70=>'سبعون',80=>'ثمانون',90=>'تسعون',100=>'مائة'];
    if ($number < 10) return $number === 0 ? $special[0] : $units[$number];
    if (isset($special[$number])) return $special[$number];
    if (isset($tens[$number])) return $tens[$number];
    $ten = intdiv($number, 10) * 10;
    return $units[$number % 10] . ' و' . $tens[$ten];
}

function report_predicate(mixed $score): string
{
    if (!is_numeric($score)) return '-';
    $score = (float) $score;
    if ($score >= 90) return 'Mumtaz';
    if ($score >= 80) return 'Jayyid Jiddan';
    if ($score >= 65) return 'Jayyid';
    if ($score >= 50) return 'Maqbul';
    if ($score >= 35) return 'Dhaif';
    return 'Dhaif Jiddan';
}

function personality_arabic(string $value): string
{
    return match (strtoupper(substr(trim($value), 0, 1))) {
        'A' => 'ممتاز', 'B' => 'جيد', 'C' => 'مقبول', 'D', 'E' => 'ضعيف', default => '-',
    };
}

$statement = $pdo->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
$statement->execute([$studentId]);
$student = $statement->fetch(PDO::FETCH_ASSOC);
if (!$student) { http_response_code(404); exit('Siswa tidak ditemukan.'); }

$title = match ($type) {
    'level' => 'RAPOR UJIAN KENAIKAN LEVEL',
    'munaqosyah' => 'LEMBAR MUNAQOSYAH',
    default => 'RAPOR HAFALAN HARIAN',
};
$dailyRows = $memorizationRows = $availableDates = $summary = $payload = [];
$reportDate = $teacherNote = '';
$period = '-';

if ($type === 'daily') {
    $statement = $pdo->prepare('SELECT tanggal FROM daily_student_reports WHERE student_id=? UNION SELECT tanggal FROM laporan_tahsin_tahfidz WHERE student_id=? ORDER BY tanggal DESC');
    $statement->execute([$studentId, $studentId]);
    $availableDates = $statement->fetchAll(PDO::FETCH_COLUMN);
    $reportDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) ? $requestedDate : (string) ($availableDates[0] ?? '');
    if ($reportDate !== '') {
        $statement = $pdo->prepare('SELECT * FROM daily_student_reports WHERE student_id=? AND tanggal=? ORDER BY created_at ASC');
        $statement->execute([$studentId, $reportDate]);
        $dailyRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement = $pdo->prepare('SELECT * FROM laporan_tahsin_tahfidz WHERE student_id=? AND tanggal=? ORDER BY created_at ASC,nama_surah ASC');
        $statement->execute([$studentId, $reportDate]);
        $memorizationRows = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    $period = $reportDate ? format_date_id($reportDate) : '-';
    $teacherNote = (string) ($dailyRows[0]['catatan_guru'] ?? $memorizationRows[0]['keterangan'] ?? '');
} elseif ($type === 'level') {
    $sql = 'SELECT * FROM level_promotion_exams WHERE student_id=?'; $params = [$studentId];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) { $sql .= ' AND tanggal=?'; $params[] = $requestedDate; }
    $statement = $pdo->prepare($sql . ' ORDER BY tanggal DESC,created_at DESC LIMIT 1');
    $statement->execute($params); $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    $reportDate = (string) ($summary['tanggal'] ?? '');
    $period = (string) ($summary['tahun_ajaran'] ?? ($reportDate ? format_date_id($reportDate) : '-'));
    $teacherNote = (string) ($summary['catatan_guru'] ?? '');
} else {
    $sql = 'SELECT * FROM munaqosyah_exams WHERE student_id=?'; $params = [$studentId];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) { $sql .= ' AND tanggal=?'; $params[] = $requestedDate; }
    $statement = $pdo->prepare($sql . ' ORDER BY tanggal DESC,created_at DESC LIMIT 1');
    $statement->execute($params); $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    $payload = json_decode((string) ($summary['hasil_ujian'] ?? '{}'), true) ?: [];
    $reportDate = (string) ($summary['tanggal'] ?? '');
    $period = (string) ($payload['bulan_tahun'] ?? ($reportDate ? format_month_year_id($reportDate) : '-'));
    $teacherNote = (string) ($summary['catatan_guru'] ?? $payload['catatanMunaqosyah'] ?? '');
}

$metaRightLabel = $type === 'munaqosyah' ? 'Juz' : 'Jenjang Tahfizh';
$metaRightValue = $type === 'munaqosyah' ? (string) ($payload['juz'] ?? '-') : level_name((int) $student['level']);
$displayDate = $reportDate ? format_date_id($reportDate) : '-';
$minimum = academic_minimum_score($type === 'munaqosyah' ? 'munaqosyah' : 'level');
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> · <?= e($student['nama_lengkap']) ?></title><link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<style>
body.official-report-body{background:#eef1f3;padding:24px;color:#111;font-family:Arial,Helvetica,sans-serif}.official-toolbar{max-width:210mm;margin:0 auto 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}.official-toolbar form{display:flex;align-items:center;gap:8px}.official-report{position:relative;width:210mm;min-height:297mm;margin:auto;padding:13mm 15mm;background:#fff;box-shadow:0 12px 35px rgba(16,24,40,.12);overflow:hidden;font-size:11px;line-height:1.35}.official-watermark{position:absolute;z-index:0;left:50%;top:51%;width:92mm;height:92mm;transform:translate(-50%,-50%);object-fit:contain;opacity:.055}.official-content{position:relative;z-index:1}.official-head{display:grid;grid-template-columns:25mm 1fr 25mm;align-items:center;text-align:center;padding-bottom:4mm;border-bottom:1.5px solid #111}.official-head-logo{width:22mm;height:22mm;object-fit:contain;justify-self:center}.official-head small{display:block;font-size:9px;font-weight:700}.official-head h1{margin:1px 0;color:#1b4332;font-size:17px;line-height:1.2;letter-spacing:.4px}.official-head h2{margin:4px 0 2px;font-size:15px;letter-spacing:1.1px}.official-contact{font-size:8px!important;line-height:1.4}.official-double-line{height:3px;border-top:1px solid #111;border-bottom:2px solid #111;margin-top:1.5mm}.official-meta{display:grid;grid-template-columns:1fr 1fr;column-gap:14mm;row-gap:1mm;margin:6mm 0;font-weight:700}.official-meta-row{display:grid;grid-template-columns:33mm 4mm 1fr}.official-table{width:100%;border-collapse:collapse;margin:0 0 4mm}.official-table th,.official-table td{border:1px solid #111;padding:2.2mm 2mm;font-size:10px;vertical-align:middle}.official-table th{background:#f1f2f4;text-align:center}.official-table .center{text-align:center}.official-table .strong{font-weight:800}.official-table .empty{height:22mm;text-align:center;color:#697386}.official-table .foot{background:#f1f2f4;font-weight:800;text-align:center}.official-section-title{margin:4mm 0 0;border:1px solid #111;border-bottom:0;background:#f1f2f4;text-align:center;padding:2mm;font-weight:800;letter-spacing:.5px}.official-note{border:1px solid #111;margin-top:5mm}.official-note-title{padding:2mm;border-bottom:1px solid #111;background:#f1f2f4;text-align:center;font-weight:800;letter-spacing:.8px}.official-note-body{min-height:22mm;padding:3mm}.official-personality{margin-top:4mm}.official-signatures{display:grid;grid-template-columns:1fr 1fr 1fr;gap:5mm;margin-top:8mm;text-align:center;font-size:10px}.official-sign-space{height:17mm}.official-sign-name{text-decoration:underline;font-weight:800}.arabic{font-family:"Traditional Arabic","Noto Naskh Arabic",Tahoma,Arial,sans-serif;font-size:13px!important;direction:rtl}.munaq-table th,.munaq-table td{padding:1.8mm 1.2mm}.report-info-grid th{width:23%}.muted-source{font-size:9px;color:#475467}@media(max-width:850px){body.official-report-body{padding:10px}.official-toolbar{width:210mm;justify-content:flex-start}.official-report{margin:0}}@media print{body.official-report-body{background:#fff;padding:0}.official-toolbar{display:none!important}.official-report{box-shadow:none;margin:0;width:210mm;min-height:297mm;padding:10mm 13mm}@page{size:A4 portrait;margin:0}}
</style></head><body class="official-report-body">
<div class="official-toolbar screen-only"><a class="btn btn-outline" href="javascript:history.back()">← Kembali</a><?php if($type==='daily'):?><form method="get"><input type="hidden" name="student_id" value="<?= e($studentId) ?>"><input type="hidden" name="type" value="daily"><?php if(isset($_GET['embed'])):?><input type="hidden" name="embed" value="1"><?php endif;?><label for="report-date"><strong>Tanggal rapor</strong></label><input id="report-date" class="input" type="date" name="date" value="<?= e($reportDate) ?>" list="available-report-dates"><datalist id="available-report-dates"><?php foreach($availableDates as $availableDate):?><option value="<?= e((string)$availableDate) ?>"><?php endforeach;?></datalist><button class="btn btn-soft">Tampilkan</button></form><?php endif;?><button class="btn btn-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
<article class="official-report"><img class="official-watermark" src="<?= url('assets/images/logo.png') ?>" alt=""><div class="official-content">
<header class="official-head"><img class="official-head-logo" src="<?= url('assets/images/logo.png') ?>" alt="Logo SD Islam Labschool Bani Saleh"><div><small>YAYASAN BANI SALEH</small><h1>SEKOLAH DASAR ISLAM LABSCHOOL<br>BANI SALEH</h1><h2><?= e($title) ?></h2><small>NPSN: 70010942 &nbsp;&nbsp;&nbsp; TERAKREDITASI: A</small><small class="official-contact">Jl. Pangeran RT 001/008 Desa Lubang Buaya Kec. Setu Kab. Bekasi · sdilabschoolbanisalehsetu@gmail.com</small></div><img class="official-head-logo" src="<?= url('assets/images/logo-tahsin.png') ?>" alt="Logo Tahsin Tahfizh"></header><div class="official-double-line"></div>
<section class="official-meta"><div class="official-meta-row"><span>Nama Peserta Didik</span><span>:</span><span><?= e(strtoupper($student['nama_lengkap'])) ?></span></div><div class="official-meta-row"><span><?= e($metaRightLabel) ?></span><span>:</span><span><?= e($metaRightValue) ?></span></div><div class="official-meta-row"><span>NIS</span><span>:</span><span><?= e($student['nis']) ?></span></div><div class="official-meta-row"><span>Periode</span><span>:</span><span><?= e($period) ?></span></div><div class="official-meta-row"><span>Kelas</span><span>:</span><span><?= e($student['kelas']) ?></span></div><div class="official-meta-row"><span>Sumber Nilai</span><span>:</span><span>Terisi Otomatis</span></div></section>
<?php if($type==='daily'):?>
<table class="official-table"><thead><tr><th style="width:7%">No</th><th style="width:15%">Tanggal</th><th style="width:12%">Presensi</th><th>Kegiatan</th><th>Tadarus</th><th>Hafalan</th></tr></thead><tbody><?php if($dailyRows):foreach($dailyRows as $index=>$row):?><tr><td class="center"><?= $index+1 ?></td><td class="center"><?= e(format_date_id($row['tanggal'])) ?></td><td class="center strong"><?= e($row['status_presensi']) ?></td><td><?= e($row['kegiatan']?:'-') ?></td><td><?= e($row['ringkasan_tadarus']?:'-') ?></td><td><?= e($row['ringkasan_hafalan']?:'-') ?></td></tr><?php endforeach;else:?><tr><td class="empty" colspan="6">Belum ada Presensi &amp; Laporan Harian pada tanggal ini.</td></tr><?php endif;?></tbody><tfoot><tr><td class="foot" colspan="6">Data otomatis dari form Presensi &amp; Laporan Harian</td></tr></tfoot></table>
<?php if($memorizationRows):?><div class="official-section-title">PENILAIAN TAHSIN &amp; TAHFIDZ</div><table class="official-table"><thead><tr><th>Surah / Ayat</th><th>Kelancaran</th><th>Makhorijul Huruf</th><th>Hukum Tajwid</th><th>Hafalan</th><th>Jumlah</th><th>Rata-rata</th><th>Ket.</th></tr></thead><tbody><?php foreach($memorizationRows as $row):$scores=[(float)($row['nilai_kelancaran']??$row['nilai']??0),(float)($row['nilai_makhraj']??$row['nilai']??0),(float)($row['nilai_tajwid']??$row['nilai']??0),(float)($row['nilai_hafalan']??$row['nilai']??0)];?><tr><td><strong><?= e($row['nama_surah']) ?></strong><?= $row['ayat']?'<br><span class="muted-source">'.e($row['ayat']).'</span>':'' ?></td><?php foreach($scores as $score):?><td class="center"><?= e(report_score($score)) ?></td><?php endforeach;?><td class="center strong"><?= e(report_score(array_sum($scores))) ?></td><td class="center strong"><?= e(report_score($row['nilai_rata_rata']??array_sum($scores)/4)) ?></td><td class="center"><?= e($row['keterangan']?:report_predicate($row['nilai_rata_rata']??null)) ?></td></tr><?php endforeach;?></tbody></table><?php endif;?>
<?php elseif($type==='level'):?>
<table class="official-table report-info-grid"><tbody><tr><th>Tanggal Ujian</th><td><?= e($displayDate) ?></td><th>Kenaikan Jenjang</th><td class="strong"><?= $summary?e(level_name((int)$summary['level_asal']).' → '.level_name((int)$summary['level_tujuan'])):'-' ?></td></tr><tr><th>Surat Ujian</th><td colspan="3" class="strong"><?= e($summary['nama_surah']??'-') ?></td></tr><tr><th>Tahun Ajaran</th><td><?= e($summary['tahun_ajaran']??'-') ?></td><th>Hasil</th><td class="strong"><?= e($summary['status']??'-') ?></td></tr></tbody></table>
<table class="official-table"><thead><tr><th style="width:8%">No</th><th>Kriteria Penilaian</th><th style="width:18%">Nilai</th><th style="width:24%">Keterangan</th></tr></thead><tbody><?php $criteria=['Kelancaran'=>'nilai_kelancaran','Makhorijul Huruf'=>'nilai_makhraj','Hukum Tajwid'=>'nilai_tajwid','Sambung Ayat'=>'nilai_hafalan'];if($summary):$number=1;foreach($criteria as $label=>$key):?><tr><td class="center"><?= $number++ ?></td><td><?= e($label) ?></td><td class="center strong"><?= e(report_score($summary[$key])) ?></td><td class="center"><?= (float)$summary[$key]>=$minimum?'Tercapai':'Perlu Bimbingan' ?></td></tr><?php endforeach;else:?><tr><td class="empty" colspan="4">Belum ada hasil ujian kenaikan level.</td></tr><?php endif;?></tbody><tfoot><tr><td class="foot" colspan="2">RATA-RATA</td><td class="foot"><?= e(report_score($summary['nilai_rata_rata']??null)) ?></td><td class="foot"><?= e($summary['status']??'-') ?></td></tr></tfoot></table>
<?php else:$munaqRows=$payload['rowsMunaqosyah']??[];$total=(int)round((float)($payload['jumlahMunaqosyah']['angka']??array_sum(array_map(static fn($row):float=>(float)($row['angka']??0),$munaqRows))));$average=$payload['nilaiRataRata']??($munaqRows?$total/count($munaqRows):null);$predicate=(string)($payload['kategoriMunaqosyah']['indo']??report_predicate($average));?>
<table class="official-table munaq-table"><thead><tr><th colspan="2">Kriteria Penilaian</th><th colspan="2">Hasil Tes</th><th colspan="2" class="arabic">الدرجات العملية</th><th rowspan="2" class="arabic">معايير التقييم</th></tr><tr><th style="width:6%">No</th><th style="width:22%">Kriteria</th><th style="width:10%">Angka</th><th style="width:22%">Huruf</th><th style="width:14%" class="arabic">كتابةً</th><th style="width:10%" class="arabic">رقماً</th></tr></thead><tbody><?php $arabLabels=['نعومة','مخارج الحروف','قانون التجويد','أكمل الآية'];if($munaqRows):foreach($munaqRows as $index=>$row):$score=(int)round((float)($row['angka']??0));?><tr><td class="center"><?= $index+1 ?></td><td><?= e($row['label']??'-') ?></td><td class="center strong"><?= e((string)$score) ?></td><td class="center"><?= e(ucfirst(report_number_words($score))) ?></td><td class="center arabic"><?= e(report_arabic_words($score)) ?></td><td class="center arabic strong"><?= e(report_arabic_digits($score)) ?></td><td class="arabic"><?= e(($arabLabels[$index]??'-').'  '.report_arabic_digits($index+1)) ?></td></tr><?php endforeach;else:?><tr><td class="empty" colspan="7">Belum ada hasil ujian Munaqosyah.</td></tr><?php endif;?><tr><th colspan="2">Jumlah</th><td class="center strong"><?= $munaqRows?e((string)$total):'-' ?></td><td class="center"><?= $munaqRows?e(ucfirst(report_number_words($total))):'-' ?></td><td colspan="2" class="center arabic strong"><?= $munaqRows?e(report_arabic_digits($total)):'-' ?></td><th class="arabic">الجملة</th></tr><tr><th colspan="2">Kategori</th><td colspan="2" class="center strong"><?= $munaqRows?e($predicate):'-' ?></td><td colspan="2" class="center arabic strong"><?= $munaqRows?e(match($predicate){'Mumtaz'=>'ممتاز','Jayyid Jiddan'=>'جيد جدا','Jayyid'=>'جيد','Maqbul'=>'مقبول',default=>'ضعيف'}):'-' ?></td><th class="arabic">فئة</th></tr></tbody></table>
<?php $personality=$payload['kepribadianMunaqosyah']??[];?><table class="official-table official-personality"><thead><tr><th colspan="2">KEPRIBADIAN</th><th colspan="2" class="arabic">أحوال الطالب</th></tr></thead><tbody><?php foreach([['akhlaq','Akhlaq','أخلاق'],['kedisiplinan','Kedisiplinan','تأديب'],['kerapihan','Kerapihan','نظافة']] as [$key,$label,$arabLabel]):$value=(string)($personality[$key]['nilai']??'-');?><tr><td><?= e($label) ?></td><td class="center strong"><?= e($value) ?></td><td class="center arabic"><?= e(personality_arabic($value)) ?></td><td class="arabic"><?= e($arabLabel) ?></td></tr><?php endforeach;?></tbody></table>
<?php endif;?>
<section class="official-note"><div class="official-note-title">CATATAN GURU</div><div class="official-note-body"><?= e($teacherNote!==''?$teacherNote:'Belum ada catatan guru.') ?></div></section>
<footer class="official-signatures"><div><strong>Orang Tua/Wali</strong><div class="official-sign-space"></div>....................................</div><div><strong>Kepala Sekolah</strong><div class="official-sign-space"></div><span class="official-sign-name">WIDI NURMARA, S.Pd.I</span></div><div>Dikeluarkan di : Bekasi<br>Tanggal : <?= e($displayDate) ?><br><strong>Koordinator Tahfizh</strong><div class="official-sign-space" style="height:12mm"></div><span class="official-sign-name">ULFA DWI HASTUTI, S.LI</span></div></footer>
</div></article></body></html>
