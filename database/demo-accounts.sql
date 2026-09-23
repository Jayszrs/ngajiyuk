-- Akun dan data contoh NGAJIYUK!
-- Aman dijalankan berulang kali (upsert dengan ID tetap).
-- Jalankan setelah database/ngajiyuk.sql.

USE ngajiyuk;
SET NAMES utf8mb4;

START TRANSACTION;

INSERT INTO users (
  id, username, email, password_hash, full_name, role,
  approval_status, is_active, phone, address, bio,
  approved_at, created_at, updated_at
) VALUES (
  '40000000-0000-4000-8000-000000000001',
  'guru_demo',
  'guru.demo@ngajiyuk.test',
  '$2y$10$oNOosVet31IEoUWTDThgN.AMfHO3QH4ITkmsUGvpvktMFoyY.MpRS',
  'Budi Santoso (Demo)',
  'guru',
  'approved',
  1,
  '081200000001',
  'Alamat data contoh',
  'Akun guru untuk demonstrasi dan pelatihan NGAJIYUK!.',
  NOW(),
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  username = VALUES(username),
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  full_name = VALUES(full_name),
  role = VALUES(role),
  approval_status = 'approved',
  is_active = 1,
  phone = VALUES(phone),
  address = VALUES(address),
  bio = VALUES(bio),
  updated_at = NOW();

INSERT INTO user_roles (id, user_id, email, role) VALUES (
  '40000000-0000-4000-8000-000000000002',
  '40000000-0000-4000-8000-000000000001',
  'guru.demo@ngajiyuk.test',
  'guru'
) ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  role = VALUES(role),
  updated_at = NOW();

INSERT INTO teacher_profiles (
  user_id, full_name, nip, phone, address, bio
) VALUES (
  '40000000-0000-4000-8000-000000000001',
  'Budi Santoso (Demo)',
  'NIP-DEMO-001',
  '081200000001',
  'Alamat data contoh',
  'Profil guru untuk demonstrasi dan pelatihan.'
) ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  nip = VALUES(nip),
  phone = VALUES(phone),
  address = VALUES(address),
  bio = VALUES(bio),
  updated_at = NOW();

INSERT INTO users (
  id, username, email, password_hash, full_name, role,
  approval_status, is_active, phone, address, bio,
  approved_at, created_at, updated_at
) VALUES (
  '40000000-0000-4000-8000-000000000003',
  'ortu_demo',
  'ortu.demo@ngajiyuk.test',
  '$2y$10$IsXZxCakGqp4r3pU7yPI4u3.8tKSgRhyp9TCvz4y.SxlslynlkEn6',
  'Siti Aminah (Demo)',
  'orang_tua',
  'approved',
  1,
  '081200000002',
  'Alamat data contoh',
  'Akun orang tua untuk demonstrasi dan pelatihan NGAJIYUK!.',
  NOW(),
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  username = VALUES(username),
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  full_name = VALUES(full_name),
  role = VALUES(role),
  approval_status = 'approved',
  is_active = 1,
  phone = VALUES(phone),
  address = VALUES(address),
  bio = VALUES(bio),
  updated_at = NOW();

INSERT INTO user_roles (id, user_id, email, role) VALUES (
  '40000000-0000-4000-8000-000000000004',
  '40000000-0000-4000-8000-000000000003',
  'ortu.demo@ngajiyuk.test',
  'orang_tua'
) ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  role = VALUES(role),
  updated_at = NOW();

INSERT INTO students (
  id, teacher_id, nama_lengkap, nis, kelas, level,
  jenis_kelamin, nik, tempat_tanggal_lahir,
  nama_ayah, nama_ibu, wali_murid, alamat, no_telp,
  status, created_at, updated_at
) VALUES (
  '40000000-0000-4000-8000-000000000005',
  '40000000-0000-4000-8000-000000000001',
  'Ahmad Fadhil (Demo)',
  '269900999',
  '2A',
  2,
  'L',
  NULL,
  'Bekasi, 15 Januari 2018',
  'Budi Rahman (Demo)',
  'Siti Aminah (Demo)',
  'Siti Aminah (Demo)',
  'Alamat data contoh',
  '081200000002',
  'aktif',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  teacher_id = VALUES(teacher_id),
  nama_lengkap = VALUES(nama_lengkap),
  nis = VALUES(nis),
  kelas = VALUES(kelas),
  level = VALUES(level),
  jenis_kelamin = VALUES(jenis_kelamin),
  tempat_tanggal_lahir = VALUES(tempat_tanggal_lahir),
  nama_ayah = VALUES(nama_ayah),
  nama_ibu = VALUES(nama_ibu),
  wali_murid = VALUES(wali_murid),
  alamat = VALUES(alamat),
  no_telp = VALUES(no_telp),
  status = 'aktif',
  updated_at = NOW();

INSERT INTO parent_student_links (
  parent_id, student_id, status, created_at, updated_at
) VALUES (
  '40000000-0000-4000-8000-000000000003',
  '40000000-0000-4000-8000-000000000005',
  'active',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  student_id = VALUES(student_id),
  status = 'active',
  updated_at = NOW();

INSERT INTO daily_student_reports (
  id, student_id, teacher_id, tanggal, status_presensi,
  kegiatan, ringkasan_tadarus, ringkasan_hafalan, catatan_guru
) VALUES (
  '40000000-0000-4000-8000-000000000006',
  '40000000-0000-4000-8000-000000000005',
  '40000000-0000-4000-8000-000000000001',
  '2026-09-22',
  'Hadir',
  'Tahsin dan murojaah bersama',
  'Membaca Surah Quraisy ayat 1-4',
  'Murojaah Surah Al-Fil',
  'Bacaan sudah baik; pertahankan panjang-pendek harakat.'
) ON DUPLICATE KEY UPDATE
  status_presensi = VALUES(status_presensi),
  kegiatan = VALUES(kegiatan),
  ringkasan_tadarus = VALUES(ringkasan_tadarus),
  ringkasan_hafalan = VALUES(ringkasan_hafalan),
  catatan_guru = VALUES(catatan_guru),
  updated_at = NOW();

INSERT INTO laporan_tadarus_pagi (
  id, teacher_id, student_id, tanggal,
  nama_surah, hal_ayat, keterangan, guru_paraf
) VALUES (
  '40000000-0000-4000-8000-000000000007',
  '40000000-0000-4000-8000-000000000001',
  '40000000-0000-4000-8000-000000000005',
  '2026-09-22',
  'Surah Quraisy',
  'Ayat 1-4',
  'Tadarus contoh untuk modul pelatihan.',
  1
) ON DUPLICATE KEY UPDATE
  nama_surah = VALUES(nama_surah),
  hal_ayat = VALUES(hal_ayat),
  keterangan = VALUES(keterangan),
  guru_paraf = 1,
  updated_at = NOW();

INSERT INTO laporan_tahsin_tahfidz (
  id, teacher_id, student_id, tanggal, tahun_ajaran,
  nama_surah, ayat, makhraj, murojaah, keterangan,
  nilai, nilai_kelancaran, nilai_makhraj, nilai_tajwid,
  nilai_hafalan, nilai_rata_rata
) VALUES (
  '40000000-0000-4000-8000-000000000008',
  '40000000-0000-4000-8000-000000000001',
  '40000000-0000-4000-8000-000000000005',
  '2026-09-22',
  '2026/2027',
  'Surah Quraisy',
  'Ayat 1-4',
  'Baik',
  'Surah Al-Fil',
  'Data nilai contoh untuk modul pelatihan.',
  85.00,
  86.00,
  84.00,
  85.00,
  85.00,
  85.00
) ON DUPLICATE KEY UPDATE
  tahun_ajaran = VALUES(tahun_ajaran),
  nama_surah = VALUES(nama_surah),
  ayat = VALUES(ayat),
  makhraj = VALUES(makhraj),
  murojaah = VALUES(murojaah),
  keterangan = VALUES(keterangan),
  nilai = VALUES(nilai),
  nilai_kelancaran = VALUES(nilai_kelancaran),
  nilai_makhraj = VALUES(nilai_makhraj),
  nilai_tajwid = VALUES(nilai_tajwid),
  nilai_hafalan = VALUES(nilai_hafalan),
  nilai_rata_rata = VALUES(nilai_rata_rata),
  updated_at = NOW();

INSERT INTO level_promotion_exams (
  id, student_id, teacher_id, tanggal,
  level_asal, level_tujuan, nama_surah,
  nilai_kelancaran, nilai_makhraj, nilai_tajwid,
  nilai_hafalan, nilai_rata_rata, status,
  tahun_ajaran, catatan_guru
) VALUES (
  '40000000-0000-4000-8000-000000000009',
  '40000000-0000-4000-8000-000000000005',
  '40000000-0000-4000-8000-000000000001',
  '2026-09-15',
  1,
  2,
  'Surah An-Nas s.d. Al-Maun',
  82.00,
  80.00,
  81.00,
  85.00,
  82.00,
  'Lulus',
  '2026/2027',
  'Lulus ujian contoh dan melanjutkan ke Level 2.'
) ON DUPLICATE KEY UPDATE
  tanggal = VALUES(tanggal),
  nama_surah = VALUES(nama_surah),
  nilai_kelancaran = VALUES(nilai_kelancaran),
  nilai_makhraj = VALUES(nilai_makhraj),
  nilai_tajwid = VALUES(nilai_tajwid),
  nilai_hafalan = VALUES(nilai_hafalan),
  nilai_rata_rata = VALUES(nilai_rata_rata),
  status = VALUES(status),
  catatan_guru = VALUES(catatan_guru),
  updated_at = NOW();

COMMIT;
