# NgajiYuk - PHP, MySQL/MariaDB, dan XAMPP

NgajiYuk adalah hasil port aplikasi **Catatan Mengaji Digital** ke PHP 8 + PDO + MySQL/MariaDB. Source ini berdiri sendiri dan tidak membutuhkan Node.js, Next.js, Vercel, PostgreSQL, atau Supabase pada runtime.

## Requirement

- Windows 10/11
- XAMPP dengan Apache, PHP 8.0+, MariaDB 10.4+ atau MySQL 8.0+
- Ekstensi PHP: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `xml`, `gd`, dan `session`
- Browser modern
- RAM minimum 2 GB untuk komputer lokal; 4 GB direkomendasikan
- Ruang kosong minimum 500 MB; 2 GB direkomendasikan untuk pertumbuhan upload dan backup

Tidak ada instalasi Composer atau npm yang diperlukan.

## Lokasi source

```text
%USERPROFILE%\Downloads\ngajiyuk
```

Source yang dijalankan Apache adalah source yang sama melalui directory junction:

```text
C:\xampp\htdocs\ngajiyuk -> %USERPROFILE%\Downloads\ngajiyuk
```

Jika junction belum ada, buka Command Prompt sebagai Administrator lalu jalankan:

```bat
mklink /J "C:\xampp\htdocs\ngajiyuk" "%USERPROFILE%\Downloads\ngajiyuk"
```

## Instalasi database

1. Jalankan Apache dan MySQL pada XAMPP Control Panel.
2. Buka `http://localhost/phpmyadmin/`.
3. Pilih menu **Import**.
4. Pilih `database/ngajiyuk.sql`.
5. Klik **Import/Go**. Script otomatis membuat database `ngajiyuk`, 16 tabel, master 12 kelas, dan 48 surat kurikulum.

Alternatif terminal:

```bat
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source C:/Users/NAMA/Downloads/ngajiyuk/database/ngajiyuk.sql"
```

## Menjalankan

1. Start **Apache**.
2. Start **MySQL**.
3. Buka `http://localhost/ngajiyuk/`.

Akun instalasi lokal yang ditandai khusus untuk development:

```text
Username : admin
Password : Admin123!
```

Segera ubah password melalui Manajemen Akun setelah login pertama.

## Konfigurasi database

Konfigurasi terpusat berada di `config/database.php` dengan default XAMPP:

```text
Host     : 127.0.0.1
Port     : 3306
Database : ngajiyuk
Username : root
Password : (kosong)
```

Konfigurasi dapat dioverride tanpa mengubah source menggunakan environment variable: `NGAJIYUK_DB_HOST`, `NGAJIYUK_DB_PORT`, `NGAJIYUK_DB_NAME`, `NGAJIYUK_DB_USER`, dan `NGAJIYUK_DB_PASS`.

## Alur akun

- Admin: memantau sistem, menyetujui akun, mengelola role, akun, siswa, kelas, hubungan Orang Tua, dan audit.
- Guru: mengelola siswa, kelas mengaji, laporan harian, nilai per surat, ujian level, Munaqosyah, kurikulum surat, dan tiga rapor resmi.
- Orang Tua: hanya melihat data anak yang terhubung, komposisi nilai, surat sesuai level anak, rapor, serta mengubah biodata sendiri.
- Pendaftaran Guru menunggu persetujuan Admin.
- Pendaftaran Orang Tua memerlukan NIS yang valid dan belum diklaim; akun tetap mengikuti status persetujuan pada backend.

## Import siswa

Halaman Daftar Siswa menerima `.xlsx` dan `.csv` hingga 8 MB. Header dicari otomatis, termasuk `NIS`, `Nama Peserta Didik/Nama Siswa`, kelas, jenis kelamin, NIK, orang tua, alamat, dan telepon. NIS menjadi kunci upsert sehingga impor ulang memperbarui data tanpa menggandakan siswa.

## Upload

Foto profil disimpan ke `uploads/profile/`. Server hanya menerima JPEG, PNG, atau WebP, maksimal 2 MB, menggunakan nama acak. `.htaccess` pada folder upload menonaktifkan eksekusi script.

## Reset password

Permintaan reset membuat token sekali pakai yang kedaluwarsa dalam 30 menit. Pada instalasi lokal tanpa SMTP, tautan reset ditampilkan di layar. Untuk produksi, sambungkan pengiriman email dan jangan tampilkan tautan token pada respons pengguna.

## Keamanan

- Password menggunakan `password_hash()` / `password_verify()`.
- Session cookie HttpOnly dan SameSite Lax.
- Semua aksi tulis memakai CSRF token.
- Query input pengguna memakai PDO prepared statement.
- Otorisasi Admin/Guru/Orang Tua diperiksa di PHP, bukan sekadar menyembunyikan menu.
- Output dinamis di-escape dengan `htmlspecialchars()`.
- Proses multi-tabel menggunakan transaction.

## Troubleshooting

- `Access denied for user`: periksa kredensial di `config/database.php` atau environment variable.
- `Unknown database ngajiyuk`: import ulang `database/ngajiyuk.sql`.
- Halaman 404: pastikan junction `C:\xampp\htdocs\ngajiyuk` benar dan module Apache `mod_rewrite` aktif.
- Port 80/3306 terpakai: hentikan service lain atau sesuaikan port XAMPP dan konfigurasi.
- XLSX gagal dibaca: aktifkan ekstensi PHP `zip` dan `xml`, lalu restart Apache.
- Session tidak bertahan: akses selalu dari URL `/ngajiyuk/`, bukan membuka file PHP langsung.

## Struktur

```text
ngajiyuk/
|- admin/       halaman Admin
|- guru/        halaman Guru
|- orangtua/    halaman Orang Tua
|- api/         endpoint JSON PHP
|- config/      konfigurasi, koneksi, auth
|- includes/    layout dan helper reusable
|- assets/      CSS, JavaScript, logo, gambar
|- uploads/     penyimpanan lokal tervalidasi
|- database/    schema dan seed MariaDB
|- docs/        checklist migrasi
|- tests/       smoke test database
```
