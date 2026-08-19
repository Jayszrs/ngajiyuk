# Laporan Migrasi Catatan Mengaji Digital ke NgajiYuk

Tanggal verifikasi: 19 Agustus 2026 (Asia/Jakarta)

## Ringkasan

| Item | Hasil |
|---|---|
| Lokasi project baru | `C:\Users\jaela\Downloads\ngajiyuk` |
| Lokasi project asli | `C:\Users\jaela\Downloads\Project2 Web\PKM Sd Bani Saleh\catatan-mengaji-digital` |
| Commit project asli saat baseline | `d7f4967f51a684cefe2b96c1588f8f20f0583104` |
| Database | MariaDB/MySQL `ngajiyuk` |
| Tabel | 16 |
| Halaman PHP | 28 |
| Endpoint API PHP | 12 |
| Role | Admin, Guru, Orang Tua |
| Runtime frontend | HTML + CSS statis + JavaScript browser |
| Runtime backend | PHP 8 + PDO |
| Dependency Node/Supabase runtime | Tidak ada |

## Status fitur

- [PASS] Project baru dibuat terpisah di Downloads.
- [PASS] Project asli tidak dijadikan target penulisan.
- [PASS] Schema PostgreSQL/Supabase dipetakan ke 16 tabel MariaDB dengan UUID, FK, unique constraint, check constraint, JSON, index, dan transaction.
- [PASS] File `database/ngajiyuk.sql` dapat di-import dan dijalankan ulang tanpa menggandakan seed.
- [PASS] 12 kelas 1A-6B dan 48 surat untuk Level 1-6 serta Mustawa Muttawasit 1-3 tersedia.
- [PASS] PDO terpusat dan prepared statement digunakan.
- [PASS] Login benar, login salah, logout, session, direct URL, dan role blocking diuji.
- [PASS] Password hash lokal dan reset password sekali pakai diuji sampai login dengan password baru.
- [PASS] Admin: monitoring, Guru, Orang Tua, siswa/kelas, akun, kelengkapan, dan audit.
- [PASS] Guru: siswa, impor Excel, kelas/nilai, harian, tadarus, tahsin, ujian level, Munaqosyah, surat, komposisi, profil, rapor.
- [PASS] Orang Tua hanya membaca anak terhubung dan surat pada level anak; biodata sendiri dapat diedit.
- [PASS] CRUD integrasi akun, siswa, laporan harian, tadarus, tahsin, ujian level, Munaqosyah, dan relasi Orang Tua diuji melalui HTTP API.
- [PASS] Audit akademik menyimpan pelaku, jenis aktivitas, target entitas, rincian, dan status.
- [PASS] Rapor resmi harian/level/Munaqosyah memakai kop dan template A4 yang sama.
- [PASS] Export `.xls` kompatibel Excel diuji dengan header download.
- [PASS] File Excel sekolah asli dibaca: 56 baris workbook, 52 siswa tersimpan, 0 baris dilewati, NIS/nama/NIK terdeteksi.
- [PASS] Upload lokal memiliki pembatas MIME, ukuran, nama acak, dan pencegahan eksekusi PHP.
- [PASS] CSRF, escaping, session authorization, validasi server, dan error page generik aktif.
- [PASS] 52 file PHP lolos `php -l`.
- [PASS] Smoke test database: CREATE, INSERT, SELECT, UPDATE, DELETE, JOIN, FK, transaction rollback, dan index (9 pemeriksaan).
- [PASS] 21 halaman dashboard lintas role diakses melalui Apache tanpa PHP fatal/warning.
- [PASS] Asset utama CSS, JavaScript, logo, dan foto memperoleh HTTP 200.
- [PASS] Tampilan login diuji melalui screenshot desktop 1440x900 dan compact 500x844.
- [PASS] Junction `C:\xampp\htdocs\ngajiyuk` mengarah ke folder Downloads.

## Temuan lingkungan yang belum dapat diubah otomatis

### [FAIL - lingkungan] MySQL default XAMPP tidak dapat start

- Nama fitur: Start MySQL dari instalasi XAMPP yang sudah ada.
- File/komponen: `C:\xampp\mysql\data` (bukan bagian project NgajiYuk).
- Penyebab: data MariaDB lama sudah mengalami kegagalan recovery Aria: `Aria recovery failed`, `Could not open mysql.plugin table`, lalu server abort.
- Status: schema dan aplikasi sudah berhasil diuji pada MariaDB 10.4.32 XAMPP menggunakan data directory uji terisolasi; data directory bawaan tidak diubah.
- Solusi: backup seluruh `C:\xampp\mysql\data`, lalu lakukan prosedur recovery Aria/XAMPP atau gunakan instalasi XAMPP MariaDB yang sehat. Jangan menghapus `aria_log.*` sebelum backup.

### [BATASAN] Password Supabase Auth tidak dapat dibawa

- Nama fitur: migrasi kredensial pengguna lama.
- Penyebab: hash password milik Supabase Auth tidak tersedia di repository atau migration SQL.
- Status: tidak ada password pengguna yang dikarang. Disediakan akun instalasi lokal `admin` dan alur reset password.
- Solusi: impor profil pengguna yang sah, kemudian Admin memberi password lokal sementara atau pengguna menjalankan reset.

### [BATASAN] Pengiriman email reset produksi

- Nama fitur: delivery email reset password.
- Penyebab: XAMPP lokal tidak memiliki SMTP yang dikonfigurasi.
- Status: token aman 30 menit bekerja; tautan ditampilkan hanya untuk instalasi lokal.
- Solusi: pada production/VPS sambungkan SMTP dan hilangkan tampilan tautan token dari halaman publik.

Tidak ada fitur aplikasi yang disembunyikan sebagai PASS jika pengujiannya gagal. Kendala yang tersisa berasal dari instalasi MariaDB lama dan layanan eksternal email, bukan dependency runtime aplikasi.

## Mapping utama source

| Source Next.js/React | Target PHP |
|---|---|
| `src/app/page.tsx` | `index.php` |
| `src/app/auth/login/page.tsx` | `login.php`, `api/auth/login.php` |
| `src/app/auth/signup/page.tsx` | `register.php` |
| `src/app/auth/reset-password/page.tsx` | `forgot-password.php`, `reset-password.php` |
| `src/components/DashboardLayout.tsx` | `includes/header.php`, `sidebar.php`, `footer.php` |
| `src/app/dashboard/admin/*` | `admin/*.php` |
| `src/app/dashboard/guru/students/page.tsx` | `guru/students.php`, `api/students/index.php` |
| `src/app/dashboard/guru/classes/page.tsx` | `guru/classes.php`, `api/classes/index.php` |
| halaman input tadarus/tahsin/laporan | `guru/daily-reports.php`, `api/reports/index.php` |
| `src/app/dashboard/guru/ujian-level/page.tsx` | `guru/level-exams.php`, `api/exams/index.php` |
| `src/app/dashboard/guru/munaqosyah/page.tsx` | `guru/munaqosyah.php`, `api/exams/index.php` |
| `src/components/SurahCurriculumManager.tsx` | `guru/surah.php`, `api/surah/index.php` |
| `src/components/OfficialReportTemplate.tsx` | `report.php` |
| `src/app/dashboard/orang-tua/*` | `orangtua/*.php` |
| `src/lib/student-import.ts` | `includes/XlsxReader.php`, `api/import/students.php` |
| `src/lib/report-exports.ts` | `api/reports/export.php` |
| Supabase client/server queries | `config/database.php` + PDO API |
| Supabase Auth | PHP Session + `users` |
| Supabase Storage | `uploads/` + validasi PHP |
| PostgreSQL migrations/RLS | `database/ngajiyuk.sql` + authorization PHP |

Mapping rinci per fitur terdapat di `docs/MIGRATION-CHECKLIST.md`.

## Verifikasi project asli

Baseline sebelum pengerjaan:

```text
HEAD: d7f4967f51a684cefe2b96c1588f8f20f0583104
Tracked files: 122
Combined tracked-content SHA-256:
d017c14acada10b87a520268b7541ebd506c642b361311bdbb637796d766003c
```

Nilai tersebut harus sama pada verifikasi akhir. Repository Git NgajiYuk diinisialisasi terpisah dan tidak memiliki remote ke repository asli.
