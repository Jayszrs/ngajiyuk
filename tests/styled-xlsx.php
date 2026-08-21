<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

if (($argv[1] ?? '') === '--child') {
    require $root . '/config/bootstrap.php';
    require_once $root . '/includes/StyledTableXlsx.php';
    StyledTableXlsx::download(
        'uji-ekspor.xlsx',
        'REKAP PRESENSI & TADARUS',
        'Kelas 1A • Tahun Ajaran 2026/2027',
        ['No', 'Nama Peserta Didik', 'NIS', 'Tanggal', 'Catatan Guru'],
        [
            ['1', 'Ahmad Zaid', '262701001', '21 Agustus 2026', 'Sangat Baik'],
            ['2', 'Hafiz', '2301110042', '22 Agustus 2026', 'Baik'],
        ],
        'Kelas 1A'
    );
}

require $root . '/config/bootstrap.php';
require_once $root . '/includes/XlsxReader.php';

$temporaryPath = tempnam(sys_get_temp_dir(), 'ngajiyuk-styled-xlsx-');
if ($temporaryPath === false) {
    fwrite(STDERR, "FAIL: File sementara tidak dapat dibuat.\n");
    exit(1);
}

try {
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--child'],
        [0 => ['pipe', 'r'], 1 => ['file', $temporaryPath, 'wb'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Proses pembuat ekspor tidak dapat dijalankan.');
    }
    fclose($pipes[0]);
    $processError = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    clearstatcache(true, $temporaryPath);
    if ($exitCode !== 0 || filesize($temporaryPath) < 1000) {
        throw new RuntimeException('Ekspor XLSX gagal dibuat. ' . trim((string) $processError));
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryPath) !== true) {
        throw new RuntimeException('Ekspor bukan arsip XLSX yang valid.');
    }
    foreach (['xl/media/logo-sekolah.png', 'xl/media/logo-tahsin.png'] as $asset) {
        if ($zip->locateName($asset) === false) {
            throw new RuntimeException('Logo ekspor tidak lengkap.');
        }
    }
    $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    $dom = new DOMDocument();
    if (!$dom->loadXML($sheetXml)) {
        throw new RuntimeException('XML worksheet tidak valid.');
    }

    $rows = XlsxReader::rows($temporaryPath, 'xlsx');
    $flattened = array_merge(...$rows);
    foreach (['Nama Peserta Didik', '262701001', '21 Agustus 2026'] as $expected) {
        if (!in_array($expected, $flattened, true)) {
            throw new RuntimeException('Nilai ' . $expected . ' tidak terbaca kembali.');
        }
    }
    fwrite(STDOUT, "PASS: Ekspor XLSX berformat, berlogo, dan data teks terbaca utuh.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    if (is_file($temporaryPath)) unlink($temporaryPath);
}
