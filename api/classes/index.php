<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$user = require_api_user('admin', 'guru');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $year = trim((string) ($_GET['tahun_ajaran'] ?? active_academic_year()));
    if (!valid_academic_year($year)) {
        throw new RuntimeException('Tahun ajaran tidak valid.');
    }
    $statement = $pdo->prepare(
        "SELECT c.id, c.nama_kelas, c.tingkat, c.rombel, c.teacher_id, c.wali_kelas,
                c.tahun_ajaran, c.aktif, u.full_name AS teacher_name,
                COUNT(CASE WHEN s.status = 'aktif' THEN 1 END) AS student_count,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 1 THEN 1 ELSE 0 END) AS level_1,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 2 THEN 1 ELSE 0 END) AS level_2,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 3 THEN 1 ELSE 0 END) AS level_3,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 4 THEN 1 ELSE 0 END) AS level_4,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 5 THEN 1 ELSE 0 END) AS level_5,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 6 THEN 1 ELSE 0 END) AS level_6,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 7 THEN 1 ELSE 0 END) AS level_7,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 8 THEN 1 ELSE 0 END) AS level_8,
                SUM(CASE WHEN s.status = 'aktif' AND s.level = 9 THEN 1 ELSE 0 END) AS level_9
         FROM classes c
         LEFT JOIN users u ON u.id = c.teacher_id
         LEFT JOIN students s ON s.kelas = c.nama_kelas
         WHERE c.tahun_ajaran = ?
         GROUP BY c.id, c.nama_kelas, c.tingkat, c.rombel, c.teacher_id, c.wali_kelas,
                  c.tahun_ajaran, c.aktif, u.full_name
         ORDER BY c.tingkat, c.rombel"
    );
    $statement->execute([$year]);
    json_response(true, 'Data kelas berhasil dimuat.', $statement->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metode tidak diizinkan.', null, 405);
}

verify_csrf();
$data = request_data();
$action = (string) ($data['action'] ?? '');
$year = trim((string) ($data['tahun_ajaran'] ?? active_academic_year()));
if (!valid_academic_year($year)) {
    throw new RuntimeException('Tahun ajaran tidak valid.');
}

if ($action === 'prepare_all') {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO classes (id, nama_kelas, tingkat, rombel, tahun_ajaran, aktif)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE aktif = 1, updated_at = NOW()'
        );
        foreach (all_class_names() as $className) {
            $statement->execute([uuidv4(), $className, (int) $className[0], $className[1], $year]);
        }
        $pdo->commit();
        audit_event('classes_prepared', 'success', null, ['tahun_ajaran' => $year]);
        json_response(true, 'Kelas 1A sampai 6B berhasil disiapkan.');
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

$class = normalize_class_name((string) ($data['kelas'] ?? $data['nama_kelas'] ?? ''));
if (!in_array($class, all_class_names(), true)) {
    throw new RuntimeException('Nama kelas harus 1A sampai 6B.');
}

if ($action === 'assign_teacher') {
    $user = require_api_user('admin');
    $teacherId = trim((string) ($data['teacher_id'] ?? '')) ?: null;
    $teacherName = null;
    if ($teacherId !== null) {
        $statement = $pdo->prepare(
            "SELECT full_name FROM users
             WHERE id = ? AND role = 'guru' AND is_active = 1 AND approval_status = 'approved'"
        );
        $statement->execute([$teacherId]);
        $teacherName = $statement->fetchColumn() ?: null;
        if ($teacherName === null) {
            throw new RuntimeException('Guru aktif tidak ditemukan.');
        }
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'UPDATE classes SET teacher_id = ?, wali_kelas = ?, updated_at = NOW()
             WHERE nama_kelas = ? AND tahun_ajaran = ?'
        );
        $statement->execute([$teacherId, $teacherName, $class, $year]);
        $exists = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE nama_kelas = ? AND tahun_ajaran = ?');
        $exists->execute([$class, $year]);
        if (!(int) $exists->fetchColumn()) {
            throw new RuntimeException('Kelas pada tahun ajaran tersebut belum tersedia.');
        }
        $pdo->prepare('UPDATE students SET teacher_id = ?, updated_at = NOW() WHERE kelas = ?')
            ->execute([$teacherId, $class]);
        $pdo->commit();
        audit_event('class_teacher_assigned', 'success', $teacherId, [
            'kelas' => $class, 'tahun_ajaran' => $year, 'teacher' => $teacherName,
        ]);
        json_response(true, 'Wali kelas mengaji berhasil diperbarui.');
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

if ($action === 'save') {
    $teacherId = $user['role'] === 'guru'
        ? (string) $user['id']
        : (trim((string) ($data['teacher_id'] ?? '')) ?: null);
    $teacherName = $user['role'] === 'guru' ? (string) $user['full_name'] : null;
    if ($teacherId && $user['role'] === 'admin') {
        $statement = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'guru' AND is_active = 1");
        $statement->execute([$teacherId]);
        $teacherName = $statement->fetchColumn() ?: null;
        if ($teacherName === null) {
            throw new RuntimeException('Guru aktif tidak ditemukan.');
        }
    }
    $pdo->prepare(
        'INSERT INTO classes (id, teacher_id, nama_kelas, tingkat, rombel, wali_kelas, tahun_ajaran, aktif)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id), wali_kelas = VALUES(wali_kelas),
                                 aktif = 1, updated_at = NOW()'
    )->execute([uuidv4(), $teacherId, $class, (int) $class[0], $class[1], $teacherName, $year]);
    audit_event('class_saved', 'success', $teacherId, ['kelas' => $class, 'tahun_ajaran' => $year]);
    json_response(true, 'Data kelas berhasil disimpan.');
}

throw new RuntimeException('Aksi kelas tidak dikenali.');
