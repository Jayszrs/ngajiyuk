<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$user = require_api_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $year = trim((string) ($_GET['tahun_ajaran'] ?? active_academic_year()));
    if (!valid_academic_year($year)) {
        throw new RuntimeException('Tahun ajaran tidak valid.');
    }
    $statement = $pdo->prepare(
        'SELECT id, tahun_ajaran, level, nama_surah, urutan
         FROM surah_curriculum WHERE tahun_ajaran = ? ORDER BY level, urutan, nama_surah'
    );
    $statement->execute([$year]);
    json_response(true, 'Data surat berhasil dimuat.', $statement->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Metode tidak diizinkan.', null, 405);
}

$user = require_api_user('guru', 'admin');
verify_csrf();
$data = request_data();
$action = (string) ($data['action'] ?? '');
$id = trim((string) ($data['surah_id'] ?? ''));

if ($action === 'copy_year') {
    $source = trim((string) ($data['source_year'] ?? ''));
    $target = trim((string) ($data['target_year'] ?? ''));
    if (!valid_academic_year($source) || !valid_academic_year($target) || $source === $target) {
        throw new RuntimeException('Tahun sumber dan tujuan wajib valid serta berbeda.');
    }
    $sourceRows = $pdo->prepare(
        'SELECT level, nama_surah, urutan FROM surah_curriculum
         WHERE tahun_ajaran = ? ORDER BY level, urutan'
    );
    $sourceRows->execute([$source]);
    $rows = $sourceRows->fetchAll();
    if (!$rows) {
        throw new RuntimeException('Data surat pada tahun sumber belum tersedia.');
    }

    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO surah_curriculum
             (id, tahun_ajaran, level, nama_surah, urutan, created_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE urutan = VALUES(urutan), updated_at = NOW()'
        );
        foreach ($rows as $row) {
            $insert->execute([
                uuidv4(), $target, (int) $row['level'], $row['nama_surah'],
                (int) $row['urutan'], $user['id'],
            ]);
        }
        $pdo->commit();
        audit_event('surah_curriculum_copied', 'success', null, [
            'source_year' => $source, 'target_year' => $target, 'total' => count($rows),
        ]);
        json_response(true, count($rows) . ' data surat berhasil disalin ke ' . $target . '.');
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

if ($action === 'save') {
    $year = trim((string) ($data['tahun_ajaran'] ?? active_academic_year()));
    $level = (int) ($data['level'] ?? 0);
    $name = trim((string) ($data['nama_surah'] ?? ''));
    $order = (int) ($data['urutan'] ?? 0);
    if (!valid_academic_year($year) || $level < 1 || $level > 9 || $name === '' ||
        mb_strlen($name) > 120 || $order < 1 || $order > 999) {
        throw new RuntimeException('Tahun, level, nama surat, dan urutan wajib valid.');
    }
    try {
        if ($id !== '') {
            $statement = $pdo->prepare(
                'UPDATE surah_curriculum SET tahun_ajaran = ?, level = ?, nama_surah = ?,
                 urutan = ?, updated_at = NOW() WHERE id = ?'
            );
            $statement->execute([$year, $level, $name, $order, $id]);
            $check = $pdo->prepare('SELECT COUNT(*) FROM surah_curriculum WHERE id = ?');
            $check->execute([$id]);
            if (!(int) $check->fetchColumn()) {
                throw new RuntimeException('Data surat tidak ditemukan.');
            }
        } else {
            $id = uuidv4();
            $pdo->prepare(
                'INSERT INTO surah_curriculum
                 (id, tahun_ajaran, level, nama_surah, urutan, created_by) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$id, $year, $level, $name, $order, $user['id']]);
        }
        audit_event('surah_curriculum_saved', 'success', $id, [
            'year' => $year, 'level' => $level, 'surah' => $name, 'order' => $order,
        ]);
        json_response(true, 'Data surat berhasil disimpan.', ['id' => $id]);
    } catch (PDOException $error) {
        error_log('Simpan surat gagal: ' . $error->getMessage());
        throw new RuntimeException('Surat yang sama sudah terdaftar pada level dan tahun ini.');
    }
}

if ($action === 'delete') {
    if ($id === '') {
        throw new RuntimeException('Data surat tidak ditemukan.');
    }
    $statement = $pdo->prepare('SELECT tahun_ajaran, level, nama_surah FROM surah_curriculum WHERE id = ?');
    $statement->execute([$id]);
    $surah = $statement->fetch();
    if (!$surah) {
        throw new RuntimeException('Data surat tidak ditemukan.');
    }
    $pdo->prepare('DELETE FROM surah_curriculum WHERE id = ?')->execute([$id]);
    audit_event('surah_curriculum_deleted', 'success', null, array_merge(['surah_id' => $id], $surah));
    json_response(true, 'Data surat berhasil dihapus.');
}

throw new RuntimeException('Aksi data surat tidak dikenali.');
