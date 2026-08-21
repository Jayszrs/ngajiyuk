<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

if (($argv[1] ?? '') === '--child') {
    $type = (string) ($argv[2] ?? 'daily');
    $format = (string) ($argv[3] ?? 'xlsx');
    $token = (string) ($argv[4] ?? 'missing');
    require $root . '/config/bootstrap.php';
    $teacherId = (string) db()->query(
        "SELECT id FROM users WHERE role='guru' AND is_active=1 ORDER BY created_at LIMIT 1"
    )->fetchColumn();
    if ($teacherId === '') exit(2);
    $_SESSION['user_id'] = $teacherId;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_USER_AGENT'] = 'NgajiYukClassExportTest-' . $token;
    $_GET = [
        'kelas' => '1A',
        'tahun_ajaran' => active_academic_year(),
        'type' => $type,
        'format' => $format,
    ];
    require $root . '/api/classes/export.php';
    exit;
}

require $root . '/config/bootstrap.php';
require_once $root . '/includes/XlsxReader.php';

$token = bin2hex(random_bytes(8));
$fingerprint = hash('sha256', '|NgajiYukClassExportTest-' . $token);
$temporaryFiles = [];
$expectedNis = (string) db()->query(
    "SELECT nis FROM students WHERE kelas='1A' ORDER BY nama_lengkap LIMIT 1"
)->fetchColumn();

try {
    foreach (['daily', 'surah', 'level', 'munaqosyah'] as $type) {
        $path = tempnam(sys_get_temp_dir(), 'ngajiyuk-export-test-');
        if ($path === false) throw new RuntimeException('File sementara gagal dibuat.');
        $temporaryFiles[] = $path;
        $process = proc_open(
            [PHP_BINARY, __FILE__, '--child', $type, 'xlsx', $token],
            [0 => ['pipe', 'r'], 1 => ['file', $path, 'wb'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );
        if (!is_resource($process)) throw new RuntimeException('Endpoint ekspor gagal dijalankan.');
        fclose($pipes[0]);
        $errorText = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        clearstatcache(true, $path);
        if ($exitCode !== 0 || filesize($path) < 1000) {
            throw new RuntimeException("Ekspor $type gagal. " . trim((string) $errorText));
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException("XLSX $type tidak valid.");
        if ($zip->locateName('xl/media/logo-sekolah.png') === false
            || $zip->locateName('xl/media/logo-tahsin.png') === false) {
            throw new RuntimeException("Logo XLSX $type tidak lengkap.");
        }
        $zip->close();
        $rows = XlsxReader::rows($path, 'xlsx');
        $flattened = $rows === [] ? [] : array_merge(...$rows);
        if ($rows === [] || !in_array('No', $flattened, true)) {
            throw new RuntimeException("Header XLSX $type tidak terbaca.");
        }
        if ($type === 'daily' && $expectedNis !== '' && !in_array($expectedNis, $flattened, true)) {
            throw new RuntimeException('NIS siswa nyata tidak tersimpan sebagai teks utuh.');
        }
    }

    $csvPath = tempnam(sys_get_temp_dir(), 'ngajiyuk-export-csv-test-');
    if ($csvPath === false) throw new RuntimeException('File CSV sementara gagal dibuat.');
    $temporaryFiles[] = $csvPath;
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--child', 'daily', 'csv', $token],
        [0 => ['pipe', 'r'], 1 => ['file', $csvPath, 'wb'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );
    if (!is_resource($process)) throw new RuntimeException('Endpoint CSV gagal dijalankan.');
    fclose($pipes[0]);
    $errorText = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $csv = (string) file_get_contents($csvPath);
    if ($exitCode !== 0 || !str_starts_with($csv, "\xEF\xBB\xBF") || !str_contains($csv, ';')) {
        throw new RuntimeException('Fallback CSV tidak valid. ' . trim((string) $errorText));
    }

    fwrite(STDOUT, "PASS: Empat ekspor XLSX dan fallback CSV berhasil melalui endpoint nyata.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    db()->prepare('DELETE FROM account_security_events WHERE request_fingerprint = ?')
        ->execute([$fingerprint]);
    foreach ($temporaryFiles as $path) {
        if (is_file($path)) unlink($path);
    }
}
