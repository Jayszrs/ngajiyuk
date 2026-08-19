# Checklist Internal Migrasi

| Fitur asli | File asli utama | Implementasi PHP | Database | Status |
|---|---|---|---|---|
| Landing/onboarding | `src/app/page.tsx` | `index.php` | - | PASS |
| Login | `src/app/auth/login/page.tsx` | `login.php`, `api/auth/login.php` | `users`, `account_security_events` | PASS |
| Signup dan verifikasi | `src/app/auth/signup/page.tsx`, `verify/page.tsx` | `register.php`, persetujuan Admin | `users`, `parent_student_links` | PASS |
| Reset password | `src/app/auth/reset-password/page.tsx` | `forgot-password.php`, `reset-password.php` | `password_reset_tokens` | PASS lokal |
| Layout, navbar, sidebar | `src/components/DashboardLayout.tsx`, `Navbar.tsx` | `includes/header.php`, `sidebar.php`, `footer.php` | - | PASS |
| Dashboard Admin | `src/app/dashboard/admin/monitoring/page.tsx` | `admin/dashboard.php` | agregasi tabel utama | PASS |
| Monitoring Guru | `src/app/dashboard/admin/guru/page.tsx` | `admin/teachers.php` | `users`, `students`, laporan | PASS |
| Monitoring Orang Tua | `src/app/dashboard/admin/orang-tua/page.tsx` | `admin/parents.php` | `users`, `parent_student_links`, `students` | PASS |
| Siswa & Kelas Admin | `src/app/dashboard/admin/siswa-kelas/page.tsx` | `admin/students-classes.php` | `students`, `classes`, nilai | PASS |
| Persetujuan/manajemen akun | `src/app/dashboard/admin/page.tsx`, API users | `admin/users.php`, `api/users/index.php` | `users`, `user_roles` | PASS |
| Kelengkapan laporan | `src/app/dashboard/admin/kelengkapan-laporan/page.tsx` | `admin/completeness.php` | laporan harian/ujian | PASS |
| Audit aktivitas | `src/app/dashboard/admin/audit/page.tsx` | `admin/audit.php`, `audit_event()` | `account_security_events` | PASS |
| Tahun ajaran/setting | `src/app/dashboard/admin/tahun-ajaran/page.tsx` | konfigurasi + `academic_settings` | `academic_settings` | DIGABUNG |
| Siswa Guru | `src/app/dashboard/guru/students/page.tsx`, `student/[id]` | `guru/students.php`, API siswa | `students` | PASS |
| Data Kelas/Nilai | `src/app/dashboard/guru/classes/page.tsx` | `guru/classes.php` | kelas, siswa, tadarus, tahsin | PASS |
| Input tadarus | `src/app/dashboard/guru/input-tadarus/page.tsx` | `guru/daily-reports.php`, API laporan | `laporan_tadarus_pagi` | DIGABUNG |
| Input tahsin/tahfizh | `input-tahsin`, `tahsin`, `targets` | `guru/daily-reports.php`, `guru/classes.php` | `laporan_tahsin_tahfidz` | DIGABUNG |
| Laporan harian | `src/app/dashboard/guru/laporan-harian/page.tsx` | `guru/daily-reports.php` | `daily_student_reports` | PASS |
| Ujian kenaikan level | `src/app/dashboard/guru/ujian-level/page.tsx` | `guru/level-exams.php`, API ujian | `level_promotion_exams` | PASS |
| Munaqosyah | `src/app/dashboard/guru/munaqosyah/page.tsx` | `guru/munaqosyah.php`, API ujian | `munaqosyah_exams`, `student_reports` | PASS |
| Kurikulum surat | `src/components/SurahCurriculumManager.tsx` | `guru/surah.php`, API surat | `surah_curriculum` | PASS |
| Komposisi nilai | `KomposisiNilaiContent.tsx` | halaman komposisi Guru/Orang Tua | `academic_settings` | PASS |
| Tiga rapor resmi | `rapor-otomatis`, `OfficialReportTemplate.tsx` | `guru/reports.php`, `report.php` | tiga sumber laporan | PASS |
| Export | `src/lib/report-exports.ts`, `export-tadarus.ts` | `api/reports/export.php` | laporan/siswa | PASS CSV-Excel |
| Import Excel siswa | `src/lib/student-import.ts` | `XlsxReader.php`, `api/import/students.php` | `students` | PASS |
| Foto profil/storage | `profile-photos.ts`, `student-photos.ts` | `api/upload/photo.php` | `users.photo_url`, filesystem | PASS |
| Portal Orang Tua | halaman dashboard/profile/data surat | `orangtua/*.php` | relasi dan data anak | PASS |

`DIGABUNG` berarti behavior tetap tersedia tetapi ditempatkan dalam halaman terintegrasi agar sesuai alur aplikasi terbaru, bukan dihapus.
