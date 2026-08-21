<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

if (($argv[1] ?? '') === '--child') {
    require $root . '/config/bootstrap.php';
    $teacherId = (string) db()->query("SELECT id FROM users WHERE role='guru' AND is_active=1 ORDER BY created_at LIMIT 1")->fetchColumn();
    if ($teacherId === '') {
        exit(2);
    }
    $_SESSION['user_id'] = $teacherId;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'template';
    require $root . '/api/import/students.php';
    exit;
}

require $root . '/config/bootstrap.php';
require_once $root . '/includes/XlsxReader.php';

$temporaryPath = tempnam(sys_get_temp_dir(), 'ngajiyuk-xlsx-test-');
if ($temporaryPath === false) {
    fwrite(STDERR, "FAIL: File sementara tidak dapat dibuat.\n");
    exit(1);
}

try {
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--child'],
        [
            0 => ['pipe', 'r'],
            1 => ['file', $temporaryPath, 'wb'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Proses pembuat template tidak dapat dijalankan.');
    }
    fclose($pipes[0]);
    $processError = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    clearstatcache(true, $temporaryPath);
    if ($exitCode !== 0 || !is_file($temporaryPath) || filesize($temporaryPath) < 1000) {
        $detail = trim((string) $processError);
        throw new RuntimeException('Template XLSX tidak berhasil dihasilkan.' . ($detail !== '' ? ' ' . $detail : ''));
    }

    $zip = new ZipArchive();
    if ($zip->open($temporaryPath) !== true) {
        throw new RuntimeException('Template bukan arsip XLSX yang valid.');
    }
    $hasSchoolLogo = $zip->locateName('xl/media/logo-sekolah.png') !== false;
    $hasTahsinLogo = $zip->locateName('xl/media/logo-tahsin.png') !== false;
    $zip->close();

    if (!$hasSchoolLogo || !$hasTahsinLogo) {
        throw new RuntimeException('Dua logo resmi belum tertanam pada template.');
    }

    $rows = XlsxReader::rows($temporaryPath, 'xlsx');
    $header = [];
    foreach ($rows as $row) {
        if (in_array('Nama Peserta Didik', $row, true)) {
            $header = $row;
            break;
        }
    }
    $requiredHeaders = ['Nama Peserta Didik', 'NIS', 'Kelas', 'Level'];
    foreach ($requiredHeaders as $requiredHeader) {
        if (!in_array($requiredHeader, $header, true)) {
            throw new RuntimeException('Kolom ' . $requiredHeader . ' tidak terbaca kembali.');
        }
    }

    fwrite(STDOUT, "PASS: Template XLSX berlogo valid dan dapat dibaca ulang.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
}
