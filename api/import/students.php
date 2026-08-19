<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require ROOT_PATH . '/includes/XlsxReader.php';

$user = require_api_user('guru', 'admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf();
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('File Excel belum dipilih atau gagal diunggah.');
$file = $_FILES['file'];
if ((int) $file['size'] > 8 * 1024 * 1024) throw new RuntimeException('Ukuran file maksimal 8 MB.');
$extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'csv'], true)) throw new RuntimeException('Gunakan file XLSX atau CSV.');
$rows = XlsxReader::rows($file['tmp_name']);
if (!$rows) throw new RuntimeException('File tidak berisi data.');

$normalize = static function (string $value): string {
    $value = strtoupper(trim($value));
    return trim(preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value);
};
$headerIndex = null;
$headers = [];
foreach ($rows as $index => $row) {
    $candidate = array_map(fn($value) => $normalize((string) $value), $row);
    $joined = ' ' . implode(' ', $candidate);
    if (str_contains($joined, 'NIS') && (str_contains($joined, 'NAMA') || str_contains($joined, 'PESERTA DIDIK'))) {
        $headerIndex = $index;
        $headers = $candidate;
        break;
    }
}
if ($headerIndex === null) throw new RuntimeException('Kolom NIS dan Nama Peserta Didik tidak ditemukan.');

$aliases = [
    'nama_lengkap' => ['NAMA LENGKAP', 'NAMA PESERTA DIDIK', 'NAMA SISWA', 'NAMA'],
    'nis' => ['NIS', 'NOMOR INDUK SISWA'], 'kelas' => ['KELAS', 'ROMBEL'],
    'jenis_kelamin' => ['JENIS KELAMIN', 'L P', 'JK'], 'nik' => ['NIK'],
    'tempat_tanggal_lahir' => ['TEMPAT TANGGAL LAHIR', 'TEMPAT DAN TANGGAL LAHIR', 'TTL'],
    'nama_ayah' => ['NAMA AYAH', 'AYAH'], 'nama_ibu' => ['NAMA IBU', 'IBU'],
    'wali_murid' => ['WALI MURID', 'NAMA WALI'], 'alamat' => ['ALAMAT'],
    'no_telp' => ['NO TELP', 'NO TELEPON', 'NOMOR TELEPON', 'HP'],
];
$map = [];
foreach ($aliases as $key => $names) foreach ($headers as $index => $header) {
    if (in_array($header, $names, true)) { $map[$key] = $index; break; }
}
if (!isset($map['nama_lengkap'], $map['nis'])) throw new RuntimeException('Kolom NIS atau Nama tidak dapat dipetakan.');

$defaultClass = normalize_class_name((string) ($_POST['kelas'] ?? '1A'));
$defaultLevel = (int) ($_POST['level'] ?? 1);
if (!in_array($defaultClass, all_class_names(), true) || $defaultLevel < 1 || $defaultLevel > 9) throw new RuntimeException('Kelas atau level bawaan tidak valid.');
$teacherId = $user['role'] === 'guru' ? $user['id'] : (trim((string) ($_POST['teacher_id'] ?? '')) ?: null);
$pdo = db();
$statement = $pdo->prepare(
    "INSERT INTO students
     (id,teacher_id,nama_lengkap,nis,kelas,level,jenis_kelamin,nik,tempat_tanggal_lahir,nama_ayah,nama_ibu,wali_murid,alamat,no_telp)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE
       teacher_id=VALUES(teacher_id), nama_lengkap=VALUES(nama_lengkap), kelas=VALUES(kelas), level=VALUES(level),
       jenis_kelamin=COALESCE(VALUES(jenis_kelamin),jenis_kelamin), nik=COALESCE(VALUES(nik),nik),
       tempat_tanggal_lahir=COALESCE(NULLIF(VALUES(tempat_tanggal_lahir),''),tempat_tanggal_lahir),
       nama_ayah=COALESCE(NULLIF(VALUES(nama_ayah),''),nama_ayah), nama_ibu=COALESCE(NULLIF(VALUES(nama_ibu),''),nama_ibu),
       wali_murid=COALESCE(NULLIF(VALUES(wali_murid),''),wali_murid), alamat=COALESCE(NULLIF(VALUES(alamat),''),alamat),
       no_telp=COALESCE(NULLIF(VALUES(no_telp),''),no_telp), updated_at=NOW()"
);

$pdo->beginTransaction();
$saved = 0;
$skipped = [];
try {
    foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
        $get = static fn(string $key): string => trim((string) ($row[$map[$key] ?? -1] ?? ''));
        $name = $get('nama_lengkap');
        $nis = preg_replace('/\.0$/', '', $get('nis')) ?? $get('nis');
        if ($name === '' && $nis === '') continue;
        if ($name === '' || $nis === '') { $skipped[] = 'Baris ' . ($headerIndex + $offset + 2) . ' tidak memiliki nama/NIS'; continue; }
        $className = normalize_class_name($get('kelas') ?: $defaultClass);
        if (!in_array($className, all_class_names(), true)) $className = $defaultClass;
        $genderRaw = strtoupper($get('jenis_kelamin'));
        $gender = str_starts_with($genderRaw, 'L') ? 'L' : (str_starts_with($genderRaw, 'P') ? 'P' : null);
        $nik = preg_replace('/\D/', '', $get('nik')) ?: null;
        if ($nik && strlen($nik) !== 16) $nik = null;
        $statement->execute([
            uuidv4(), $teacherId, $name, $nis, $className, $defaultLevel, $gender, $nik,
            $get('tempat_tanggal_lahir') ?: null, $get('nama_ayah') ?: null, $get('nama_ibu') ?: null,
            $get('wali_murid') ?: null, $get('alamat') ?: null, $get('no_telp') ?: null,
        ]);
        $saved++;
    }
    $pdo->commit();
    audit_event('students_imported', 'success', $teacherId, ['file'=>$file['name'],'saved'=>$saved,'skipped'=>count($skipped),'class'=>$defaultClass]);
    json_response(true, "Impor selesai: {$saved} siswa disimpan.", ['saved'=>$saved,'skipped'=>$skipped]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
