-- NgajiYuk - schema MySQL/MariaDB untuk XAMPP
-- Sumber: delapan migrasi PostgreSQL/Supabase Catatan Mengaji Digital.
-- Kompatibel dengan MariaDB 10.4+ dan MySQL 8.0+.

CREATE DATABASE IF NOT EXISTS ngajiyuk
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE ngajiyuk;

SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL,
  username VARCHAR(80) NOT NULL,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  role ENUM('admin','guru','orang_tua') NOT NULL,
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  phone VARCHAR(100) NULL,
  address TEXT NULL,
  bio TEXT NULL,
  photo_url VARCHAR(500) NULL,
  last_login_at DATETIME NULL,
  approved_at DATETIME NULL,
  approved_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY users_username_unique (username),
  UNIQUE KEY users_email_unique (email),
  KEY users_role_status_idx (role, approval_status, is_active),
  KEY users_approved_by_idx (approved_by),
  CONSTRAINT users_approved_by_fk FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_roles (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  email VARCHAR(190) NULL,
  role ENUM('admin','guru','orang_tua') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY user_roles_user_unique (user_id),
  KEY user_roles_role_idx (role),
  CONSTRAINT user_roles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teacher_profiles (
  user_id CHAR(36) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  nip VARCHAR(80) NULL,
  phone VARCHAR(100) NULL,
  address TEXT NULL,
  bio TEXT NULL,
  photo_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT teacher_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classes (
  id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NULL,
  nama_kelas VARCHAR(20) NOT NULL,
  tingkat TINYINT UNSIGNED NOT NULL,
  rombel CHAR(1) NOT NULL DEFAULT 'A',
  wali_kelas VARCHAR(190) NULL,
  tahun_ajaran VARCHAR(9) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY classes_name_year_unique (nama_kelas, tahun_ajaran),
  KEY classes_teacher_idx (teacher_id),
  KEY classes_active_year_idx (aktif, tahun_ajaran),
  CONSTRAINT classes_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT classes_grade_check CHECK (tingkat BETWEEN 1 AND 6),
  CONSTRAINT classes_rombel_check CHECK (rombel IN ('A','B'))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
  id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NULL,
  nama_lengkap VARCHAR(190) NOT NULL,
  nis VARCHAR(80) NOT NULL,
  kelas VARCHAR(20) NOT NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  jenis_kelamin ENUM('L','P') NULL,
  nik VARCHAR(32) NULL,
  tempat_tanggal_lahir VARCHAR(190) NULL,
  nama_ayah VARCHAR(190) NULL,
  nama_ibu VARCHAR(190) NULL,
  wali_murid VARCHAR(190) NULL,
  alamat TEXT NULL,
  no_telp TEXT NULL,
  foto_url VARCHAR(500) NULL,
  status ENUM('aktif','tidak_aktif','pindah','lulus') NOT NULL DEFAULT 'aktif',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY students_nis_unique (nis),
  KEY students_teacher_idx (teacher_id),
  KEY students_class_status_idx (kelas, status),
  KEY students_level_idx (level),
  CONSTRAINT students_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT students_level_check CHECK (level BETWEEN 1 AND 9),
  CONSTRAINT students_nik_check CHECK (nik IS NULL OR nik REGEXP '^[0-9]{16}$')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parent_student_links (
  parent_id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  active_student_id CHAR(36) AS (CASE WHEN status = 'active' THEN student_id ELSE NULL END) STORED,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (parent_id),
  UNIQUE KEY parent_student_one_active_student (active_student_id),
  KEY parent_student_student_idx (student_id),
  CONSTRAINT parent_student_parent_fk FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT parent_student_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS laporan_tadarus_pagi (
  id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  tanggal DATE NOT NULL,
  nama_surah VARCHAR(120) NOT NULL,
  hal_ayat VARCHAR(190) NULL,
  keterangan TEXT NULL,
  guru_paraf TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tadarus_student_date_idx (student_id, tanggal),
  KEY tadarus_teacher_date_idx (teacher_id, tanggal),
  CONSTRAINT tadarus_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT tadarus_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS laporan_tahsin_tahfidz (
  id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  tanggal DATE NOT NULL,
  tahun_ajaran VARCHAR(9) NOT NULL DEFAULT '2026/2027',
  nama_surah VARCHAR(120) NOT NULL,
  ayat VARCHAR(190) NULL,
  makhraj TEXT NULL,
  murojaah TEXT NULL,
  keterangan TEXT NULL,
  nilai DECIMAL(5,2) NULL,
  nilai_kelancaran DECIMAL(5,2) NULL,
  nilai_makhraj DECIMAL(5,2) NULL,
  nilai_tajwid DECIMAL(5,2) NULL,
  nilai_hafalan DECIMAL(5,2) NULL,
  nilai_rata_rata DECIMAL(5,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY tahsin_student_date_idx (student_id, tanggal),
  KEY tahsin_teacher_date_idx (teacher_id, tanggal),
  KEY tahsin_year_surah_idx (tahun_ajaran, nama_surah),
  CONSTRAINT tahsin_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT tahsin_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT tahsin_kelancaran_check CHECK (nilai_kelancaran IS NULL OR nilai_kelancaran BETWEEN 0 AND 100),
  CONSTRAINT tahsin_makhraj_check CHECK (nilai_makhraj IS NULL OR nilai_makhraj BETWEEN 0 AND 100),
  CONSTRAINT tahsin_tajwid_check CHECK (nilai_tajwid IS NULL OR nilai_tajwid BETWEEN 0 AND 100),
  CONSTRAINT tahsin_hafalan_check CHECK (nilai_hafalan IS NULL OR nilai_hafalan BETWEEN 0 AND 100),
  CONSTRAINT tahsin_average_check CHECK (nilai_rata_rata IS NULL OR nilai_rata_rata BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_student_reports (
  id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NOT NULL,
  tanggal DATE NOT NULL,
  status_presensi ENUM('Hadir','Izin','Sakit','Alpa') NOT NULL,
  kegiatan TEXT NULL,
  ringkasan_tadarus TEXT NULL,
  ringkasan_hafalan TEXT NULL,
  catatan_guru TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY daily_student_date_unique (student_id, tanggal),
  KEY daily_teacher_date_idx (teacher_id, tanggal),
  CONSTRAINT daily_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT daily_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS level_promotion_exams (
  id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NOT NULL,
  tanggal DATE NOT NULL,
  level_asal TINYINT UNSIGNED NOT NULL,
  level_tujuan TINYINT UNSIGNED NOT NULL,
  nama_surah VARCHAR(120) NOT NULL,
  nilai_kelancaran DECIMAL(5,2) NOT NULL,
  nilai_makhraj DECIMAL(5,2) NOT NULL,
  nilai_tajwid DECIMAL(5,2) NOT NULL,
  nilai_hafalan DECIMAL(5,2) NOT NULL,
  nilai_rata_rata DECIMAL(5,2) NOT NULL,
  status ENUM('Lulus','Mengulang') NOT NULL,
  tahun_ajaran VARCHAR(9) NOT NULL,
  catatan_guru TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY level_exam_student_target_year_unique (student_id, level_tujuan, tahun_ajaran),
  KEY level_exam_student_date_idx (student_id, tanggal),
  KEY level_exam_teacher_year_idx (teacher_id, tahun_ajaran),
  CONSTRAINT level_exam_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT level_exam_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT level_exam_level_check CHECK (level_asal BETWEEN 1 AND 9 AND level_tujuan BETWEEN 1 AND 9 AND level_tujuan > level_asal),
  CONSTRAINT level_exam_score_check CHECK (
    nilai_kelancaran BETWEEN 0 AND 100 AND nilai_makhraj BETWEEN 0 AND 100
    AND nilai_tajwid BETWEEN 0 AND 100 AND nilai_hafalan BETWEEN 0 AND 100
    AND nilai_rata_rata BETWEEN 0 AND 100
  )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS munaqosyah_exams (
  id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NOT NULL,
  tanggal DATE NOT NULL,
  jenjang VARCHAR(20) NOT NULL DEFAULT 'SD/MI',
  durasi_menit SMALLINT UNSIGNED NOT NULL DEFAULT 120,
  status ENUM('Terjadwal','Berlangsung','Selesai') NOT NULL DEFAULT 'Selesai',
  hasil_ujian JSON NOT NULL,
  catatan_guru TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY munaqosyah_student_unique (student_id),
  KEY munaqosyah_teacher_date_idx (teacher_id, tanggal),
  CONSTRAINT munaqosyah_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT munaqosyah_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_reports (
  id CHAR(36) NOT NULL,
  student_id CHAR(36) NOT NULL,
  teacher_id CHAR(36) NOT NULL,
  bulan_tahun VARCHAR(80) NOT NULL,
  jenis_rapor ENUM('rapor','munaqosyah') NOT NULL,
  data_rapor JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY student_report_period_unique (student_id, jenis_rapor, bulan_tahun),
  KEY student_report_teacher_idx (teacher_id),
  CONSTRAINT student_report_student_fk FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT student_report_teacher_fk FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS surah_curriculum (
  id CHAR(36) NOT NULL,
  tahun_ajaran VARCHAR(9) NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  nama_surah VARCHAR(120) NOT NULL,
  urutan SMALLINT UNSIGNED NOT NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY surah_year_level_name_unique (tahun_ajaran, level, nama_surah),
  KEY surah_year_level_order_idx (tahun_ajaran, level, urutan),
  KEY surah_created_by_idx (created_by),
  CONSTRAINT surah_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT surah_level_check CHECK (level BETWEEN 1 AND 9),
  CONSTRAINT surah_order_check CHECK (urutan > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_settings (
  id TINYINT UNSIGNED NOT NULL DEFAULT 1,
  active_year VARCHAR(9) NOT NULL DEFAULT '2026/2027',
  daily_weight TINYINT UNSIGNED NOT NULL DEFAULT 30,
  level_exam_weight TINYINT UNSIGNED NOT NULL DEFAULT 30,
  munaqosyah_weight TINYINT UNSIGNED NOT NULL DEFAULT 40,
  minimum_level_score DECIMAL(5,2) NOT NULL DEFAULT 75,
  minimum_munaqosyah_score DECIMAL(5,2) NOT NULL DEFAULT 75,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT academic_weights_check CHECK (daily_weight + level_exam_weight + munaqosyah_weight = 100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_security_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id CHAR(36) NULL,
  target_user_id CHAR(36) NULL,
  event_type VARCHAR(100) NOT NULL,
  status ENUM('success','failed','blocked') NOT NULL,
  request_fingerprint CHAR(64) NULL,
  details JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY security_created_idx (created_at),
  KEY security_actor_created_idx (actor_user_id, created_at),
  KEY security_target_created_idx (target_user_id, created_at),
  CONSTRAINT security_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT security_target_fk FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY password_reset_token_unique (token_hash),
  KEY password_reset_user_idx (user_id, expires_at),
  CONSTRAINT password_reset_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (
  id, username, email, password_hash, full_name, role,
  approval_status, is_active, approved_at
) VALUES (
  '00000000-0000-4000-8000-000000000001',
  'admin',
  NULL,
  '$2y$10$on54t1PbldIcvM2lQlHFa.lRVc8kYh7f/D.rT/Yi4HZaTXE/W2bie',
  'Administrator',
  'admin',
  'approved',
  1,
  NOW()
) ON DUPLICATE KEY UPDATE id = VALUES(id);

INSERT INTO user_roles (id, user_id, email, role)
VALUES (
  '00000000-0000-4000-8000-000000000002',
  '00000000-0000-4000-8000-000000000001',
  NULL,
  'admin'
) ON DUPLICATE KEY UPDATE role = VALUES(role);

INSERT INTO academic_settings (id, active_year, daily_weight, level_exam_weight, munaqosyah_weight)
VALUES (1, '2026/2027', 30, 30, 40)
ON DUPLICATE KEY UPDATE id = VALUES(id);

INSERT INTO classes (id, teacher_id, nama_kelas, tingkat, rombel, tahun_ajaran, aktif) VALUES
('10000000-0000-4000-8000-000000000001', NULL, '1A', 1, 'A', '2026/2027', 1),
('10000000-0000-4000-8000-000000000002', NULL, '1B', 1, 'B', '2026/2027', 1),
('10000000-0000-4000-8000-000000000003', NULL, '2A', 2, 'A', '2026/2027', 1),
('10000000-0000-4000-8000-000000000004', NULL, '2B', 2, 'B', '2026/2027', 1),
('10000000-0000-4000-8000-000000000005', NULL, '3A', 3, 'A', '2026/2027', 1),
('10000000-0000-4000-8000-000000000006', NULL, '3B', 3, 'B', '2026/2027', 1),
('10000000-0000-4000-8000-000000000007', NULL, '4A', 4, 'A', '2026/2027', 1),
('10000000-0000-4000-8000-000000000008', NULL, '4B', 4, 'B', '2026/2027', 1),
('10000000-0000-4000-8000-000000000009', NULL, '5A', 5, 'A', '2026/2027', 1),
('10000000-0000-4000-8000-000000000010', NULL, '5B', 5, 'B', '2026/2027', 1),
('10000000-0000-4000-8000-000000000011', NULL, '6A', 6, 'A', '2026/2027', 1),
('10000000-0000-4000-8000-000000000012', NULL, '6B', 6, 'B', '2026/2027', 1)
ON DUPLICATE KEY UPDATE nama_kelas = VALUES(nama_kelas), aktif = VALUES(aktif);

INSERT INTO surah_curriculum (id, tahun_ajaran, level, nama_surah, urutan) VALUES
('20000000-0001-4000-8000-000000000001','2026/2027',1,'Surah An-Nas',1),
('20000000-0001-4000-8000-000000000002','2026/2027',1,'Surah Al-Falaq',2),
('20000000-0001-4000-8000-000000000003','2026/2027',1,'Surah Al-Ikhlas',3),
('20000000-0001-4000-8000-000000000004','2026/2027',1,'Surah Al-Lahab',4),
('20000000-0001-4000-8000-000000000005','2026/2027',1,'Surah An-Nasr',5),
('20000000-0001-4000-8000-000000000006','2026/2027',1,'Surah Al-Kafirun',6),
('20000000-0001-4000-8000-000000000007','2026/2027',1,'Surah Al-Kautsar',7),
('20000000-0001-4000-8000-000000000008','2026/2027',1,'Surah Al-Ma''un',8),
('20000000-0002-4000-8000-000000000001','2026/2027',2,'Surah Quraisy',1),
('20000000-0002-4000-8000-000000000002','2026/2027',2,'Surah Al-Fil',2),
('20000000-0002-4000-8000-000000000003','2026/2027',2,'Surah Al-Humazah',3),
('20000000-0002-4000-8000-000000000004','2026/2027',2,'Surah Al-Asr',4),
('20000000-0002-4000-8000-000000000005','2026/2027',2,'Surah At-Takasur',5),
('20000000-0002-4000-8000-000000000006','2026/2027',2,'Surah Al-Qari''ah',6),
('20000000-0002-4000-8000-000000000007','2026/2027',2,'Surah Al-''Adiyat',7),
('20000000-0002-4000-8000-000000000008','2026/2027',2,'Surah Az-Zalzalah',8),
('20000000-0003-4000-8000-000000000001','2026/2027',3,'Surah Al-Bayyinah',1),
('20000000-0003-4000-8000-000000000002','2026/2027',3,'Surah Al-Qadr',2),
('20000000-0003-4000-8000-000000000003','2026/2027',3,'Surah Al-Alaq',3),
('20000000-0003-4000-8000-000000000004','2026/2027',3,'Surah At-Tin',4),
('20000000-0003-4000-8000-000000000005','2026/2027',3,'Surah As-Syarh',5),
('20000000-0003-4000-8000-000000000006','2026/2027',3,'Surah Ad-Dhuha',6),
('20000000-0003-4000-8000-000000000007','2026/2027',3,'Surah Al-Lail',7),
('20000000-0003-4000-8000-000000000008','2026/2027',3,'Surah Asy-Syams',8),
('20000000-0003-4000-8000-000000000009','2026/2027',3,'Surah Al-Balad',9),
('20000000-0004-4000-8000-000000000001','2026/2027',4,'Surah Al-Fajr',1),
('20000000-0004-4000-8000-000000000002','2026/2027',4,'Surah Al-Ghasyiyah',2),
('20000000-0004-4000-8000-000000000003','2026/2027',4,'Surah Al-A''la',3),
('20000000-0004-4000-8000-000000000004','2026/2027',4,'Surah At-Tariq',4),
('20000000-0004-4000-8000-000000000005','2026/2027',4,'Surah Al-Buruj',5),
('20000000-0005-4000-8000-000000000001','2026/2027',5,'Surah Al-Insyiqaq',1),
('20000000-0005-4000-8000-000000000002','2026/2027',5,'Surah Al-Muthaffifin',2),
('20000000-0005-4000-8000-000000000003','2026/2027',5,'Surah Al-Infitar',3),
('20000000-0005-4000-8000-000000000004','2026/2027',5,'Surah At-Takwir',4),
('20000000-0006-4000-8000-000000000001','2026/2027',6,'Surah Abasa',1),
('20000000-0006-4000-8000-000000000002','2026/2027',6,'Surah An-Naziat',2),
('20000000-0006-4000-8000-000000000003','2026/2027',6,'Surah An-Naba',3),
('20000000-0007-4000-8000-000000000001','2026/2027',7,'Al-Mulk',1),
('20000000-0007-4000-8000-000000000002','2026/2027',7,'Al-Qalam',2),
('20000000-0007-4000-8000-000000000003','2026/2027',7,'Al-Haqqah',3),
('20000000-0007-4000-8000-000000000004','2026/2027',7,'Al-Ma''arij',4),
('20000000-0007-4000-8000-000000000005','2026/2027',7,'Nuh',5),
('20000000-0008-4000-8000-000000000001','2026/2027',8,'Al-Jinn',1),
('20000000-0008-4000-8000-000000000002','2026/2027',8,'Al-Muzammil',2),
('20000000-0008-4000-8000-000000000003','2026/2027',8,'Al-Muddasir',3),
('20000000-0009-4000-8000-000000000001','2026/2027',9,'Al-Qiyamah',1),
('20000000-0009-4000-8000-000000000002','2026/2027',9,'Al-Insan',2),
('20000000-0009-4000-8000-000000000003','2026/2027',9,'Al-Mursalat',3)
ON DUPLICATE KEY UPDATE urutan = VALUES(urutan), updated_at = NOW();
