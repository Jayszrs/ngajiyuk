<?php
declare(strict_types=1);

require __DIR__ . '/config/bootstrap.php';
if (is_logged_in()) {
    redirect(dashboard_path((string) current_user()['role']));
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b4332">
    <title>Ngaji Yuk! · SD Islam Labschool Bani Saleh</title>
    <link rel="icon" href="<?= url('assets/images/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="landing-page">
<nav class="public-nav">
    <div class="container nav-inner">
        <a class="brand" href="<?= url() ?>">
            <img src="<?= url('assets/images/logo.png') ?>" alt="Logo sekolah">
            <span><strong>NGAJI YUK!</strong><small>SD ISLAM LABSCHOOL BANI SALEH</small></span>
        </a>
        <div class="nav-actions">
            <a class="btn" href="<?= url('login.php') ?>">Masuk</a>
            <a class="btn btn-primary" href="<?= url('register.php') ?>">Daftar Sekarang →</a>
        </div>
    </div>
</nav>

<header class="landing-hero">
    <div class="container hero-grid">
        <div>
            <span class="integration-pill"><?= svg_icon('activity', 15) ?> Sistem Tahfidz Terintegrasi</span>
            <h1 class="hero-title">Pendampingan hafalan yang lebih <span>terarah</span>, setiap hari.</h1>
            <p class="hero-copy">Satu ruang digital untuk membantu guru mencatat, sekolah mengevaluasi, dan orang tua mengikuti perkembangan Tahsin &amp; Tahfidz siswa secara berkelanjutan.</p>
            <div class="actions" style="margin-top:28px">
                <a class="btn btn-primary" href="<?= url('register.php') ?>">Mulai Menggunakan →</a>
                <a class="btn btn-outline" href="<?= url('login.php') ?>">Saya sudah punya akun</a>
            </div>
            <div class="hero-stats">
                <span><strong>3</strong><small>Rapor Otomatis</small></span>
                <span><strong>9</strong><small>Jenjang Tahfidz</small></span>
                <span><strong>2</strong><small>Akses Terhubung</small></span>
            </div>
        </div>
        <div class="dashboard-mock" aria-label="Contoh dashboard perkembangan">
            <div class="mock-head">
                <span class="brand"><img src="<?= url('assets/images/logo.png') ?>" alt="" style="width:34px;height:34px"><span><strong style="font-size:12px">Dashboard Perkembangan</strong><small>TAHUN AJARAN <?= e(active_academic_year()) ?></small></span></span>
                <span class="badge badge-green">● Aktif</span>
            </div>
            <div class="mock-progress">
                <span class="mock-score">82%</span>
                <small>PROGRES TAHFIDZ</small>
                <h3>Calon Penghuni Surga</h3>
                <span style="color:#c8ddd3">Kelas 5 · Level 4</span>
                <div class="progress" style="margin-top:15px;background:#315e4d"><span style="width:82%;background:#65d897"></span></div>
            </div>
            <div class="mock-kpis">
                <div class="mock-kpi"><?= svg_icon('file',16) ?><strong>24</strong><small>Laporan</small></div>
                <div class="mock-kpi"><?= svg_icon('award',16) ?><strong>A-</strong><small>Predikat</small></div>
                <div class="mock-kpi"><?= svg_icon('book',16) ?><strong>5</strong><small>Surat</small></div>
            </div>
            <div class="mock-activity"><strong>Aktivitas Terbaru</strong><div class="mock-row">✓ HAFALAN — Al-Fajr ayat 1–10</div><div class="mock-row">✓ TADARUS — Al-Baqarah ayat 1–15</div></div>
        </div>
    </div>
</header>

<section class="landing-section">
    <div class="container">
        <div class="landing-intro">
            <div><p class="eyebrow">SATU SISTEM YANG UTUH</p><h2>Dibuat untuk alur belajar yang benar-benar berjalan.</h2></div>
            <p>Setiap fitur dirancang mengikuti kebutuhan sekolah—mulai dari pencatatan di kelas sampai laporan resmi yang diterima orang tua.</p>
        </div>
        <div class="feature-cards">
            <article class="feature-card"><span class="feature-icon"><?= svg_icon('file') ?></span><h3>Catatan harian yang tertata</h3><p>Presensi, kegiatan, tadarus, hafalan, dan catatan guru tersimpan dalam satu alur kerja.</p></article>
            <article class="feature-card"><span class="feature-icon" style="color:#d15a00;background:#fff4e8"><?= svg_icon('printer') ?></span><h3>Tiga rapor otomatis</h3><p>Rapor harian, kenaikan level, dan munaqosyah tersusun otomatis dalam format resmi sekolah.</p></article>
            <article class="feature-card"><span class="feature-icon" style="color:#147cc0;background:#edf7ff"><?= svg_icon('book') ?></span><h3>Kurikulum per level</h3><p>Target surat dari Level 1 hingga Mustawa Muttawasit tercatat jelas dan mudah diperbarui.</p></article>
            <article class="feature-card"><span class="feature-icon" style="color:#781ee5;background:#f6efff"><?= svg_icon('users') ?></span><h3>Guru dan orang tua tetap terhubung</h3><p>Perkembangan siswa dapat dipantau secara transparan tanpa menunggu pembagian rapor.</p></article>
        </div>
    </div>
</section>

<section class="landing-section soft">
    <div class="container">
        <div class="flow-heading"><p class="eyebrow">ALUR YANG SEDERHANA</p><h2>Dari kelas hingga rumah, tetap tersambung.</h2></div>
        <div class="flow-grid">
            <article class="flow-card"><span class="flow-number">01</span><h3>Guru mencatat</h3><p class="muted">Input kegiatan dan penilaian siswa melalui form yang ringkas.</p></article>
            <article class="flow-card"><span class="flow-number">02</span><h3>Sistem merangkum</h3><p class="muted">Data harian diolah menjadi progres dan rapor yang konsisten.</p></article>
            <article class="flow-card"><span class="flow-number">03</span><h3>Orang tua memantau</h3><p class="muted">Capaian anak dapat dilihat kapan saja dari akun orang tua.</p></article>
        </div>
    </div>
</section>

<section class="landing-section">
    <div class="container">
        <div class="dark-audience">
            <article class="audience-panel"><span style="color:#5cdd96"><?= svg_icon('award',26) ?></span><p class="eyebrow" style="color:#5cdd96">UNTUK GURU</p><h2>Lebih fokus mendampingi, lebih sedikit mengurus administrasi.</h2><ul class="audience-list"><li>Input harian dalam satu halaman</li><li>Penilaian dan kenaikan level terstruktur</li><li>Rapor resmi siap cetak</li></ul></article>
            <article class="audience-panel"><span style="color:#f5c84b"><?= svg_icon('users',26) ?></span><p class="eyebrow" style="color:#f5c84b">UNTUK ORANG TUA</p><h2>Perkembangan anak hadir dengan jelas, bukan sekadar angka.</h2><ul class="audience-list"><li>Riwayat belajar mudah dipahami</li><li>Target hafalan terlihat transparan</li><li>Catatan guru dapat dipantau kapan saja</li></ul></article>
        </div>
        <div class="landing-cta"><div><p class="eyebrow">MULAI SEKARANG</p><h2>Bangun pendampingan Tahsin &amp; Tahfidz yang lebih konsisten.</h2></div><a class="btn btn-primary" href="<?= url('register.php') ?>">Daftar Sekarang →</a></div>
    </div>
</section>

<footer class="landing-footer"><div class="container footer-inner"><a class="brand" href="<?= url() ?>"><img src="<?= url('assets/images/logo.png') ?>" alt="Logo" style="width:32px;height:32px"><span><strong style="font-size:11px">NGAJI YUK!</strong><small>SD ISLAM LABSCHOOL BANI SALEH</small></span></a><span class="muted">© <?= date('Y') ?> SD Islam Labschool Bani Saleh. Hak cipta dilindungi.</span></div></footer>
</body>
</html>
