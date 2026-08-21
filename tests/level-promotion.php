<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

if (($argv[1] ?? '') === '--child') {
    $studentId = (string) ($argv[2] ?? '');
    require $root . '/config/bootstrap.php';

    $teacher = db()->query("SELECT id FROM users WHERE role='guru' AND is_active=1 ORDER BY created_at LIMIT 1")->fetch();
    if (!$teacher || $studentId === '') {
        fwrite(STDERR, "Guru atau siswa uji tidak tersedia.\n");
        exit(2);
    }

    $_SESSION['user_id'] = (string) $teacher['id'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_POST = [
        'csrf_token' => csrf_token(),
        'action' => 'save_level',
        'student_id' => $studentId,
        'tanggal' => date('Y-m-d'),
        'level_tujuan' => '2',
        'tahun_ajaran' => active_academic_year(),
        'nama_surah' => 'Surah An-Nas',
        'nilai_kelancaran' => '80',
        'nilai_makhraj' => '80',
        'nilai_tajwid' => '80',
        'nilai_hafalan' => '80',
        'catatan_guru' => 'Pengujian otomatis kenaikan level',
    ];

    require $root . '/api/exams/index.php';
    exit;
}

require $root . '/config/bootstrap.php';

$pdo = db();
$studentId = uuidv4();
$nis = 'TEST-LEVEL-' . substr(str_replace('-', '', $studentId), 0, 12);
$teacherId = (string) $pdo->query("SELECT id FROM users WHERE role='guru' AND is_active=1 ORDER BY created_at LIMIT 1")->fetchColumn();

if ($teacherId === '') {
    fwrite(STDERR, "FAIL: Tidak ada Guru aktif untuk pengujian.\n");
    exit(1);
}

try {
    $pdo->prepare('INSERT INTO students(id,teacher_id,nama_lengkap,nis,kelas,level) VALUES(?,?,?,?,?,1)')
        ->execute([$studentId, $teacherId, 'Siswa Uji Kenaikan Level', $nis, '1A']);

    $command = escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__FILE__)
        . ' --child ' . escapeshellarg($studentId);
    $output = shell_exec($command);
    $response = json_decode(trim((string) $output), true);

    $statement = $pdo->prepare('SELECT level FROM students WHERE id=?');
    $statement->execute([$studentId]);
    $newLevel = (int) $statement->fetchColumn();

    if (!is_array($response) || !($response['success'] ?? false) || $newLevel !== 2) {
        throw new RuntimeException('API tidak menaikkan Level 1 menjadi Level 2. Respons: ' . trim((string) $output));
    }

    fwrite(STDOUT, "PASS: Ujian lulus otomatis mengubah Level 1 menjadi Level 2.\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    $pdo->prepare('DELETE FROM account_security_events WHERE details LIKE ?')->execute(['%' . $studentId . '%']);
    $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$studentId]);
}
