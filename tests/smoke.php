<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$pdo = db();
$checks = [];
$assert = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException("FAIL: {$name}");
    $checks[] = "PASS: {$name}";
};

$tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='ngajiyuk'")->fetchColumn();
$assert((int) $tables === 16, 'CREATE TABLE dan 16 tabel schema tersedia');
$assert((int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn() === 12, 'master kelas 1A-6B');
$assert((int) $pdo->query('SELECT COUNT(*) FROM surah_curriculum')->fetchColumn() === 48, '48 surat kurikulum awal');
$assert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='ngajiyuk'")->fetchColumn() > 20, 'INDEX tersedia');

$teacherId = uuidv4();
$studentId = uuidv4();
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO users(id,username,password_hash,full_name,role,approval_status,is_active) VALUES(?,?,?,?,?,'approved',1)")
        ->execute([$teacherId, 'smoke_guru_' . substr($teacherId, 0, 8), password_hash('Smoke123!', PASSWORD_DEFAULT), 'Guru Smoke', 'guru']);
    $pdo->prepare('INSERT INTO students(id,teacher_id,nama_lengkap,nis,kelas,level) VALUES(?,?,?,?,?,?)')
        ->execute([$studentId, $teacherId, 'Siswa Smoke', 'SMOKE-' . substr($studentId, 0, 8), '1A', 1]);
    $join = $pdo->prepare('SELECT s.nama_lengkap,u.full_name guru FROM students s JOIN users u ON u.id=s.teacher_id WHERE s.id=?');
    $join->execute([$studentId]);
    $assert((bool) $join->fetch(), 'INSERT, SELECT, dan JOIN');
    $pdo->prepare("UPDATE students SET kelas='1B' WHERE id=?")->execute([$studentId]);
    $assert($pdo->query("SELECT kelas FROM students WHERE id=" . $pdo->quote($studentId))->fetchColumn() === '1B', 'UPDATE');
    $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$studentId]);
    $assert((int) $pdo->query('SELECT ROW_COUNT()')->fetchColumn() === 1, 'DELETE');
    $pdo->rollBack();
    $assert(!(bool) $pdo->query('SELECT COUNT(*) FROM users WHERE id=' . $pdo->quote($teacherId))->fetchColumn(), 'TRANSACTION rollback');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

try {
    $pdo->exec("INSERT INTO students(id,teacher_id,nama_lengkap,nis,kelas,level) VALUES(UUID(),'ffffffff-ffff-4fff-8fff-ffffffffffff','FK Test','FK-TEST','1A',1)");
    throw new RuntimeException('FAIL: FOREIGN KEY test tidak memicu error');
} catch (PDOException $error) {
    $assert(true, 'FOREIGN KEY divalidasi');
}

foreach ($checks as $check) echo $check . PHP_EOL;
echo 'TOTAL_PASS=' . count($checks) . PHP_EOL;
