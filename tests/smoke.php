<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$pdo = db();

$checks = [];

$assert = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException("FAIL: {$name}");
    }

    $checks[] = "PASS: {$name}";
};


/*
|--------------------------------------------------------------------------
| CEK DATABASE
|--------------------------------------------------------------------------
*/

$tableStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM information_schema.tables
     WHERE table_schema = ?'
);

$tableStatement->execute([DB_NAME]);

$tableCount = (int) $tableStatement->fetchColumn();

$assert(
    $tableCount >= 16,
    'Minimal 16 tabel schema tersedia'
);


/*
|--------------------------------------------------------------------------
| CEK MASTER DATA
|--------------------------------------------------------------------------
*/

$classCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM classes')
    ->fetchColumn();

$assert(
    $classCount === 12,
    'Master kelas 1A-6B tersedia'
);


$surahCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM surah_curriculum')
    ->fetchColumn();

$assert(
    $surahCount === 48,
    '48 surat kurikulum awal tersedia'
);


/*
|--------------------------------------------------------------------------
| CEK INDEX
|--------------------------------------------------------------------------
*/

$indexStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM information_schema.statistics
     WHERE table_schema = ?'
);

$indexStatement->execute([DB_NAME]);

$indexCount = (int) $indexStatement->fetchColumn();

$assert(
    $indexCount > 20,
    'INDEX database tersedia'
);


/*
|--------------------------------------------------------------------------
| TEST CRUD + TRANSACTION
|--------------------------------------------------------------------------
*/

$teacherId = uuidv4();
$studentId = uuidv4();

$pdo->beginTransaction();

try {
    $teacherUsername = 'smoke_guru_' . substr($teacherId, 0, 8);
    $studentNis = 'SMOKE-' . substr($studentId, 0, 8);

    $insertTeacher = $pdo->prepare(
        "INSERT INTO users (
            id,
            username,
            password_hash,
            full_name,
            role,
            approval_status,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, 'approved', 1)"
    );

    $insertTeacher->execute([
        $teacherId,
        $teacherUsername,
        password_hash('Smoke123!', PASSWORD_DEFAULT),
        'Guru Smoke',
        'guru',
    ]);


    $insertStudent = $pdo->prepare(
        'INSERT INTO students (
            id,
            teacher_id,
            nama_lengkap,
            nis,
            kelas,
            level
        )
        VALUES (?, ?, ?, ?, ?, ?)'
    );

    $insertStudent->execute([
        $studentId,
        $teacherId,
        'Siswa Smoke',
        $studentNis,
        '1A',
        1,
    ]);


    /*
    |--------------------------------------------------------------------------
    | TEST SELECT + JOIN
    |--------------------------------------------------------------------------
    */

    $join = $pdo->prepare(
        'SELECT
            s.nama_lengkap,
            u.full_name AS guru
         FROM students s
         JOIN users u
            ON u.id = s.teacher_id
         WHERE s.id = ?'
    );

    $join->execute([$studentId]);

    $assert(
        (bool) $join->fetch(),
        'INSERT, SELECT, dan JOIN'
    );


    /*
    |--------------------------------------------------------------------------
    | TEST UPDATE
    |--------------------------------------------------------------------------
    */

    $updateStudent = $pdo->prepare(
        "UPDATE students
         SET kelas = '1B'
         WHERE id = ?"
    );

    $updateStudent->execute([$studentId]);


    $checkStudent = $pdo->prepare(
        'SELECT kelas
         FROM students
         WHERE id = ?'
    );

    $checkStudent->execute([$studentId]);

    $assert(
        $checkStudent->fetchColumn() === '1B',
        'UPDATE'
    );


    /*
    |--------------------------------------------------------------------------
    | TEST DELETE
    |--------------------------------------------------------------------------
    */

    $deleteStudent = $pdo->prepare(
        'DELETE FROM students
         WHERE id = ?'
    );

    $deleteStudent->execute([$studentId]);

    $assert(
        $deleteStudent->rowCount() === 1,
        'DELETE'
    );


    /*
    |--------------------------------------------------------------------------
    | TEST ROLLBACK
    |--------------------------------------------------------------------------
    */

    $pdo->rollBack();


    $checkTeacher = $pdo->prepare(
        'SELECT COUNT(*)
         FROM users
         WHERE id = ?'
    );

    $checkTeacher->execute([$teacherId]);

    $assert(
        (int) $checkTeacher->fetchColumn() === 0,
        'TRANSACTION rollback'
    );
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}


/*
|--------------------------------------------------------------------------
| TEST FOREIGN KEY
|--------------------------------------------------------------------------
*/

$foreignKeyStudentId = uuidv4();

try {
    $foreignKeyTest = $pdo->prepare(
        'INSERT INTO students (
            id,
            teacher_id,
            nama_lengkap,
            nis,
            kelas,
            level
        )
        VALUES (?, ?, ?, ?, ?, ?)'
    );

    $foreignKeyTest->execute([
        $foreignKeyStudentId,
        'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'FK Test',
        'FK-TEST-' . substr($foreignKeyStudentId, 0, 8),
        '1A',
        1,
    ]);

    /*
     * Kalau sampai sini berarti foreign key tidak bekerja.
     * Bersihkan record kalau database mengizinkannya.
     */
    $cleanup = $pdo->prepare(
        'DELETE FROM students WHERE id = ?'
    );

    $cleanup->execute([$foreignKeyStudentId]);

    throw new RuntimeException(
        'FAIL: FOREIGN KEY test tidak memicu error'
    );
} catch (PDOException $error) {
    $assert(
        true,
        'FOREIGN KEY divalidasi'
    );
}


/*
|--------------------------------------------------------------------------
| OUTPUT
|--------------------------------------------------------------------------
*/

foreach ($checks as $check) {
    echo $check . PHP_EOL;
}

echo 'TOTAL_PASS=' . count($checks) . PHP_EOL;