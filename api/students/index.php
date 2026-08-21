<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$user = require_api_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = 'SELECT s.id, s.teacher_id, s.nama_lengkap, s.nis, s.kelas, s.level,
                   s.jenis_kelamin, s.nik, s.tempat_tanggal_lahir, s.nama_ayah,
                   s.nama_ibu, s.wali_murid, s.alamat, s.no_telp, s.foto_url,
                   s.status, s.created_at, s.updated_at, u.full_name AS teacher_name,
                   EXISTS(SELECT 1 FROM parent_student_links l
                          WHERE l.student_id = s.id AND l.status = \'active\') AS has_parent
            FROM students s
            LEFT JOIN users u ON u.id = s.teacher_id
            WHERE 1 = 1';
    $params = [];

    if ($user['role'] === 'orang_tua') {
        $studentId = parent_student_id((string) $user['id']);
        if (!$studentId) {
            json_response(true, 'Belum ada anak terhubung.', []);
        }
        $sql .= ' AND s.id = ?';
        $params[] = $studentId;
    } else {
        $class = normalize_class_name((string) ($_GET['kelas'] ?? ''));
        $level = (int) ($_GET['level'] ?? 0);
        $status = trim((string) ($_GET['status'] ?? ''));
        $search = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));

        if ($class !== '') {
            if (!in_array($class, all_class_names(), true)) {
                throw new RuntimeException('Filter kelas tidak valid.');
            }
            $sql .= ' AND s.kelas = ?';
            $params[] = $class;
        }
        if ($level !== 0) {
            if ($level < 1 || $level > 9) {
                throw new RuntimeException('Filter level tidak valid.');
            }
            $sql .= ' AND s.level = ?';
            $params[] = $level;
        }
        if ($status !== '') {
            if (!in_array($status, ['aktif', 'tidak_aktif', 'pindah', 'lulus'], true)) {
                throw new RuntimeException('Filter status tidak valid.');
            }
            $sql .= ' AND s.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (s.nama_lengkap LIKE ? OR s.nis LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
    }

    $sql .= ' ORDER BY CAST(LEFT(s.kelas, 1) AS UNSIGNED), s.kelas, s.nama_lengkap LIMIT 1000';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    json_response(true, 'Data siswa berhasil dimuat.', $statement->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metode tidak diizinkan.', null, 405);
}

$user = require_api_user('admin', 'guru');
verify_csrf();
$data = request_data();
$action = (string) ($data['action'] ?? '');
$id = trim((string) ($data['student_id'] ?? ''));

if ($action === 'create' || $action === 'update') {
    $name = trim((string) ($data['nama_lengkap'] ?? ''));
    $nis = trim((string) ($data['nis'] ?? ''));
    $kelas = normalize_class_name((string) ($data['kelas'] ?? ''));
    $level = (int) ($data['level'] ?? 1);
    $gender = trim((string) ($data['jenis_kelamin'] ?? '')) ?: null;
    $nik = preg_replace('/\D+/', '', trim((string) ($data['nik'] ?? ''))) ?: null;
    $status = trim((string) ($data['status'] ?? 'aktif'));

    if ($name === '' || mb_strlen($name) > 190 || mb_strlen($nis) > 80) {
        throw new RuntimeException('Nama lengkap wajib diisi dengan benar.');
    }
    if (!in_array($kelas, all_class_names(), true) || $level < 1 || $level > 9) {
        throw new RuntimeException('Kelas harus 1A sampai 6B dan level harus 1 sampai 9.');
    }
    if ($gender !== null && !in_array($gender, ['L', 'P'], true)) {
        throw new RuntimeException('Jenis kelamin tidak valid.');
    }
    if ($nik !== null && !preg_match('/^[0-9]{16}$/', $nik)) {
        throw new RuntimeException('NIK harus tepat 16 digit jika diisi.');
    }
    if (!in_array($status, ['aktif', 'tidak_aktif', 'pindah', 'lulus'], true)) {
        throw new RuntimeException('Status siswa tidak valid.');
    }

    $teacherId = $user['role'] === 'guru'
        ? (string) $user['id']
        : (trim((string) ($data['teacher_id'] ?? '')) ?: null);

    if ($teacherId !== null) {
        $teacher = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'guru' AND is_active = 1");
        $teacher->execute([$teacherId]);
        if (!(int) $teacher->fetchColumn()) {
            throw new RuntimeException('Guru pengampu tidak valid.');
        }
    }

    $optional = static function (array $source, string $key, int $max = 0): ?string {
        $value = trim((string) ($source[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        if ($max > 0 && mb_strlen($value) > $max) {
            throw new RuntimeException('Isian ' . str_replace('_', ' ', $key) . ' terlalu panjang.');
        }
        return $value;
    };

    if ($nis === '') {
        $lastNis = (string) $pdo->query(
            "SELECT nis FROM students WHERE nis REGEXP '^[0-9]+$' ORDER BY CHAR_LENGTH(nis) DESC, CAST(nis AS UNSIGNED) DESC LIMIT 1"
        )->fetchColumn();
        $width = max(9, strlen($lastNis));
        $nis = str_pad((string) (max(230110000, (int) $lastNis) + 1), $width, '0', STR_PAD_LEFT);
    }

    $storePhoto = static function (array $file, ?string $oldPath = null): ?string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $oldPath;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Foto siswa gagal diunggah.');
        }
        if ((int) ($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
            throw new RuntimeException('Ukuran foto maksimal 2 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Foto harus berformat JPG, PNG, atau WEBP.');
        }
        $directory = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'students';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder foto siswa tidak dapat dibuat.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('Foto siswa gagal disimpan.');
        }
        if ($oldPath && str_starts_with($oldPath, 'uploads/students/')) {
            $oldAbsolute = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
            if (is_file($oldAbsolute)) {
                @unlink($oldAbsolute);
            }
        }
        return 'uploads/students/' . $filename;
    };

    try {
        if ($action === 'create') {
            $id = uuidv4();
            $statement = $pdo->prepare(
                'INSERT INTO students
                 (id, teacher_id, nama_lengkap, nis, kelas, level, jenis_kelamin, nik,
                  tempat_tanggal_lahir, nama_ayah, nama_ibu, wali_murid, alamat, no_telp, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        } else {
            if ($id === '' || !can_access_student($user, $id, true)) {
                throw new RuntimeException('Siswa tidak ditemukan atau tidak dapat diubah.');
            }
            $exists = $pdo->prepare('SELECT teacher_id FROM students WHERE id = ? LIMIT 1');
            $exists->execute([$id]);
            $existing = $exists->fetch();
            if (!$existing) {
                throw new RuntimeException('Siswa tidak ditemukan.');
            }
            if ($user['role'] === 'guru' && $existing['teacher_id']) {
                $teacherId = (string) $existing['teacher_id'];
            }
            $statement = $pdo->prepare(
                'UPDATE students SET teacher_id = ?, nama_lengkap = ?, nis = ?, kelas = ?, level = ?,
                 jenis_kelamin = ?, nik = ?, tempat_tanggal_lahir = ?, nama_ayah = ?, nama_ibu = ?,
                 wali_murid = ?, alamat = ?, no_telp = ?, status = ?, updated_at = NOW()
                 WHERE id = ?'
            );
        }

        $values = [
            $teacherId, $name, $nis, $kelas, $level, $gender, $nik,
            $optional($data, 'tempat_tanggal_lahir', 190),
            $optional($data, 'nama_ayah', 190),
            $optional($data, 'nama_ibu', 190),
            $optional($data, 'wali_murid', 190),
            $optional($data, 'alamat'),
            $optional($data, 'no_telp'),
            $status,
        ];
        if ($action === 'create') {
            array_unshift($values, $id);
        } else {
            $values[] = $id;
        }
        $statement->execute($values);

        if (isset($_FILES['foto']) && is_array($_FILES['foto'])) {
            $oldPhoto = null;
            if ($action === 'update') {
                $photoStatement = $pdo->prepare('SELECT foto_url FROM students WHERE id = ?');
                $photoStatement->execute([$id]);
                $oldPhoto = $photoStatement->fetchColumn() ?: null;
            }
            $photoPath = $storePhoto($_FILES['foto'], $oldPhoto);
            if ($photoPath !== $oldPhoto) {
                $pdo->prepare('UPDATE students SET foto_url = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$photoPath, $id]);
            }
        }

        audit_event(
            $action === 'create' ? 'student_created' : 'student_updated',
            'success',
            $id,
            ['student' => $name, 'nis' => $nis, 'kelas' => $kelas, 'level' => $level]
        );
        json_response(
            true,
            $action === 'create' ? 'Siswa berhasil ditambahkan.' : 'Data siswa berhasil diperbarui.',
            ['id' => $id]
        );
    } catch (PDOException $error) {
        error_log('Simpan siswa gagal: ' . $error->getMessage());
        throw new RuntimeException(
            str_contains(strtolower($error->getMessage()), 'duplicate')
                ? 'NIS sudah digunakan siswa lain.'
                : 'Data siswa gagal disimpan.'
        );
    }
}

if ($id === '' || !can_access_student($user, $id, true)) {
    throw new RuntimeException('Siswa tidak ditemukan atau tidak dapat diubah.');
}
$statement = $pdo->prepare('SELECT id, nama_lengkap, nis, status FROM students WHERE id = ? LIMIT 1');
$statement->execute([$id]);
$student = $statement->fetch();
if (!$student) {
    throw new RuntimeException('Siswa tidak ditemukan.');
}

if ($action === 'status') {
    $status = (string) ($data['status'] ?? '');
    if (!in_array($status, ['aktif', 'tidak_aktif', 'pindah', 'lulus'], true)) {
        throw new RuntimeException('Status siswa tidak valid.');
    }
    $pdo->prepare('UPDATE students SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
    audit_event('student_status_changed', 'success', $id, [
        'student' => $student['nama_lengkap'], 'from' => $student['status'], 'to' => $status,
    ]);
    json_response(true, 'Status siswa diperbarui.');
}

if ($action === 'delete') {
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
        $pdo->commit();
        audit_event('student_deleted', 'success', null, [
            'student_id' => $id, 'student' => $student['nama_lengkap'], 'nis' => $student['nis'],
        ]);
        json_response(true, 'Siswa dan data terkait berhasil dihapus.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Hapus siswa gagal: ' . $error->getMessage());
        throw new RuntimeException('Siswa tidak dapat dihapus karena masih digunakan oleh data lain.');
    }
}

throw new RuntimeException('Aksi siswa tidak dikenali.');
