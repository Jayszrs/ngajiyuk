<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require ROOT_PATH . '/includes/StyledTableXlsx.php';

$user = require_api_user('guru', 'admin');
$pdo = db();

$class = normalize_class_name((string) ($_GET['kelas'] ?? ''));
$year = trim((string) ($_GET['tahun_ajaran'] ?? active_academic_year()));
$type = trim((string) ($_GET['type'] ?? 'daily'));
$format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));

if (!in_array($class, all_class_names(), true)) {
    throw new RuntimeException('Kelas ekspor tidak valid.');
}
if (!valid_academic_year($year)) {
    throw new RuntimeException('Tahun ajaran ekspor tidak valid.');
}
if (!in_array($format, ['xlsx', 'csv'], true)) {
    throw new RuntimeException('Format ekspor tidak dikenali.');
}

$types = [
    'daily' => ['slug' => 'presensi-tadarus', 'title' => 'REKAP PRESENSI & TADARUS'],
    'surah' => ['slug' => 'nilai-per-surat', 'title' => 'REKAP NILAI PER SURAT'],
    'level' => ['slug' => 'ujian-kenaikan-level', 'title' => 'REKAP UJIAN KENAIKAN LEVEL'],
    'munaqosyah' => ['slug' => 'munaqosyah', 'title' => 'REKAP MUNAQOSYAH'],
];
if (!isset($types[$type])) {
    throw new RuntimeException('Jenis data kelas tidak dikenali.');
}

[$startYear] = array_map('intval', explode('/', $year));
$dateStart = sprintf('%04d-07-01', $startYear);
$dateEnd = sprintf('%04d-06-30', $startYear + 1);
$definition = $types[$type];
$headers = [];
$exportRows = [];

if ($type === 'daily') {
    $statement = $pdo->prepare(
        'SELECT s.nama_lengkap, s.nis, s.kelas, s.level,
                d.tanggal, d.status_presensi, d.kegiatan,
                d.ringkasan_tadarus, d.ringkasan_hafalan,
                d.catatan_guru, u.full_name AS guru
         FROM students s
         LEFT JOIN daily_student_reports d
            ON d.student_id = s.id
            AND d.tanggal BETWEEN ? AND ?
         LEFT JOIN users u ON u.id = d.teacher_id
         WHERE s.kelas = ?
         ORDER BY s.nama_lengkap, d.tanggal'
    );
    $statement->execute([$dateStart, $dateEnd, $class]);
    $rows = $statement->fetchAll();
    $headers = [
        'No', 'Nama Peserta Didik', 'NIS', 'Kelas', 'Level', 'Tanggal',
        'Presensi', 'Kegiatan', 'Ringkasan Tadarus', 'Ringkasan Hafalan',
        'Catatan Guru', 'Guru Penginput',
    ];
    foreach ($rows as $index => $row) {
        $exportRows[] = [
            $index + 1, $row['nama_lengkap'], $row['nis'], $row['kelas'],
            level_name((int) $row['level']),
            $row['tanggal'] ? format_date_id((string) $row['tanggal']) : '',
            $row['status_presensi'], $row['kegiatan'], $row['ringkasan_tadarus'],
            $row['ringkasan_hafalan'], $row['catatan_guru'], $row['guru'],
        ];
    }
}

if ($type === 'surah') {
    $statement = $pdo->prepare(
        'SELECT s.nama_lengkap, s.nis, s.kelas, s.level,
                t.tanggal, t.tahun_ajaran, t.nama_surah, t.ayat,
                t.nilai_kelancaran, t.nilai_makhraj, t.nilai_tajwid,
                t.nilai_hafalan, t.nilai_rata_rata, t.keterangan,
                u.full_name AS guru
         FROM students s
         LEFT JOIN laporan_tahsin_tahfidz t
            ON t.student_id = s.id AND t.tahun_ajaran = ?
         LEFT JOIN users u ON u.id = t.teacher_id
         WHERE s.kelas = ?
         ORDER BY s.nama_lengkap, t.tanggal, t.nama_surah'
    );
    $statement->execute([$year, $class]);
    $rows = $statement->fetchAll();
    $headers = [
        'No', 'Nama Peserta Didik', 'NIS', 'Kelas', 'Level', 'Tanggal',
        'Tahun Ajaran', 'Nama Surat', 'Ayat', 'Kelancaran',
        'Makhorijul Huruf', 'Hukum Tajwid', 'Sambung Ayat/Hafalan',
        'Jumlah', 'Rata-rata', 'Keterangan', 'Guru Penginput',
    ];
    foreach ($rows as $index => $row) {
        $hasScore = $row['tanggal'] !== null;
        $scores = [$row['nilai_kelancaran'], $row['nilai_makhraj'], $row['nilai_tajwid'], $row['nilai_hafalan']];
        $exportRows[] = [
            $index + 1, $row['nama_lengkap'], $row['nis'], $row['kelas'],
            level_name((int) $row['level']),
            $row['tanggal'] ? format_date_id((string) $row['tanggal']) : '',
            $row['tahun_ajaran'], $row['nama_surah'], $row['ayat'],
            $row['nilai_kelancaran'], $row['nilai_makhraj'], $row['nilai_tajwid'],
            $row['nilai_hafalan'], $hasScore ? array_sum(array_map('floatval', $scores)) : '',
            $row['nilai_rata_rata'],
            $hasScore ? ($row['keterangan'] ?: score_status((float) $row['nilai_rata_rata'], academic_minimum_score('level'))) : '',
            $row['guru'],
        ];
    }
}

if ($type === 'level') {
    $statement = $pdo->prepare(
        'SELECT s.nama_lengkap, s.nis, s.kelas,
                e.tanggal, e.tahun_ajaran, e.level_asal, e.level_tujuan,
                e.nama_surah, e.nilai_kelancaran, e.nilai_makhraj,
                e.nilai_tajwid, e.nilai_hafalan, e.nilai_rata_rata,
                e.status, e.catatan_guru, u.full_name AS guru
         FROM students s
         LEFT JOIN level_promotion_exams e
            ON e.student_id = s.id AND e.tahun_ajaran = ?
         LEFT JOIN users u ON u.id = e.teacher_id
         WHERE s.kelas = ?
         ORDER BY s.nama_lengkap, e.tanggal, e.level_tujuan'
    );
    $statement->execute([$year, $class]);
    $rows = $statement->fetchAll();
    $headers = [
        'No', 'Nama Peserta Didik', 'NIS', 'Kelas', 'Tanggal',
        'Tahun Ajaran', 'Level Asal', 'Level Tujuan', 'Surat Ujian',
        'Kelancaran', 'Makhorijul Huruf', 'Hukum Tajwid', 'Sambung Ayat',
        'Jumlah', 'Rata-rata', 'Hasil', 'Catatan Guru', 'Guru Penguji',
    ];
    foreach ($rows as $index => $row) {
        $hasExam = $row['tanggal'] !== null;
        $scores = [$row['nilai_kelancaran'], $row['nilai_makhraj'], $row['nilai_tajwid'], $row['nilai_hafalan']];
        $exportRows[] = [
            $index + 1, $row['nama_lengkap'], $row['nis'], $row['kelas'],
            $row['tanggal'] ? format_date_id((string) $row['tanggal']) : '',
            $row['tahun_ajaran'],
            $hasExam ? level_name((int) $row['level_asal']) : '',
            $hasExam ? level_name((int) $row['level_tujuan']) : '',
            $row['nama_surah'], $row['nilai_kelancaran'], $row['nilai_makhraj'],
            $row['nilai_tajwid'], $row['nilai_hafalan'],
            $hasExam ? array_sum(array_map('floatval', $scores)) : '',
            $row['nilai_rata_rata'], $row['status'], $row['catatan_guru'], $row['guru'],
        ];
    }
}

if ($type === 'munaqosyah') {
    $statement = $pdo->prepare(
        "SELECT s.nama_lengkap, s.nis, s.kelas, s.level,
                m.tanggal, m.hasil_ujian, m.catatan_guru,
                u.full_name AS guru,
                (SELECT sr.bulan_tahun FROM student_reports sr
                 WHERE sr.student_id = s.id AND sr.jenis_rapor = 'munaqosyah'
                 ORDER BY sr.updated_at DESC LIMIT 1) AS periode
         FROM students s
         LEFT JOIN munaqosyah_exams m
            ON m.student_id = s.id AND m.tanggal BETWEEN ? AND ?
         LEFT JOIN users u ON u.id = m.teacher_id
         WHERE s.kelas = ?
         ORDER BY s.nama_lengkap, m.tanggal"
    );
    $statement->execute([$dateStart, $dateEnd, $class]);
    $rows = $statement->fetchAll();
    $headers = [
        'No', 'Nama Peserta Didik', 'NIS', 'Kelas', 'Level', 'Tanggal',
        'Periode', 'Juz', 'Kelancaran', 'Makhorijul Huruf', 'Hukum Tajwid',
        'Sambung Ayat', 'Jumlah', 'Rata-rata', 'Predikat', 'Akhlaq',
        'Kedisiplinan', 'Kerapihan', 'Catatan Guru', 'Guru Penguji',
    ];
    foreach ($rows as $index => $row) {
        $payload = json_decode((string) ($row['hasil_ujian'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];
        $scoreRows = is_array($payload['rowsMunaqosyah'] ?? null) ? $payload['rowsMunaqosyah'] : [];
        $scoreValues = [];
        foreach ($scoreRows as $scoreRow) $scoreValues[] = $scoreRow['angka'] ?? '';
        $personality = is_array($payload['kepribadianMunaqosyah'] ?? null) ? $payload['kepribadianMunaqosyah'] : [];
        $exportRows[] = [
            $index + 1, $row['nama_lengkap'], $row['nis'], $row['kelas'],
            level_name((int) $row['level']),
            $row['tanggal'] ? format_date_id((string) $row['tanggal']) : '',
            $row['periode'], $payload['juz'] ?? '',
            $scoreValues[0] ?? '', $scoreValues[1] ?? '', $scoreValues[2] ?? '', $scoreValues[3] ?? '',
            $payload['jumlahMunaqosyah']['angka'] ?? '', $payload['nilaiRataRata'] ?? '',
            $payload['kategoriMunaqosyah']['indo'] ?? '',
            $personality['akhlaq']['nilai'] ?? '', $personality['kedisiplinan']['nilai'] ?? '',
            $personality['kerapihan']['nilai'] ?? '',
            $payload['catatanMunaqosyah'] ?? $row['catatan_guru'], $row['guru'],
        ];
    }
}

audit_event('class_data_exported', 'success', null, [
    'kelas' => $class, 'tahun_ajaran' => $year, 'type' => $type,
    'format' => $format, 'role' => $user['role'],
]);

$baseFilename = sprintf('kelas-%s-%s-%s', strtolower($class), $definition['slug'], str_replace('/', '-', $year));
$subtitle = sprintf('Kelas %s • Tahun Ajaran %s', $class, $year);
if ($format === 'xlsx') {
    StyledTableXlsx::download(
        $baseFilename . '.xlsx', $definition['title'], $subtitle,
        $headers, $exportRows, 'Kelas ' . $class
    );
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $baseFilename . '.csv"');
header('Cache-Control: private, max-age=0, must-revalidate');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'wb');
if ($output === false) throw new RuntimeException('File ekspor CSV tidak dapat dibuat.');
$safe = static function (mixed $value): string {
    $text = trim((string) ($value ?? ''));
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) return "'" . $text;
    return $text;
};
$write = static function (array $row) use ($output, $safe): void {
    fputcsv($output, array_map($safe, $row), ';', '"', '\\');
};
$write(['NGAJIYUK - ' . $definition['title']]);
$write(['Kelas ' . $class, 'Tahun Ajaran ' . $year]);
$write([]);
$write($headers);
foreach ($exportRows as $row) $write($row);
fclose($output);
exit;
