<?php
declare(strict_types=1);

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$role = (string) $user['role'];
$menus = [
    'admin' => [
        ['Dashboard Monitoring', 'admin/dashboard.php', 'activity'],
        ['Monitoring Guru', 'admin/teachers.php', 'users'],
        ['Monitoring Orang Tua', 'admin/parents.php', 'link'],
        ['Siswa & Kelas', 'admin/students-classes.php', 'school'],
        ['Persetujuan Akun', 'admin/users.php', 'shield'],
        ['Kelengkapan Laporan', 'admin/completeness.php', 'clipboard'],
        ['Audit Aktivitas', 'admin/audit.php', 'history'],
    ],
    'guru' => [
        ['Dashboard', 'guru/dashboard.php', 'dashboard'],
        ['Daftar Siswa', 'guru/students.php', 'users'],
        ['Data Kelas', 'guru/classes.php', 'school'],
        ['Presensi & Harian', 'guru/daily-reports.php', 'file'],
        ['Ujian Kenaikan Level', 'guru/level-exams.php', 'award'],
        ['Form Munaqosyah', 'guru/munaqosyah.php', 'award'],
        ['Data Surat', 'guru/surah.php', 'book'],
        ['Komposisi Nilai', 'guru/composition.php', 'award'],
        ['3 Rapor Otomatis', 'guru/reports.php', 'printer'],
    ],
    'orang_tua' => [
        ['Dashboard Anak', 'orangtua/dashboard.php', 'dashboard'],
        ['Komposisi Nilai', 'orangtua/composition.php', 'award'],
        ['Data Surat', 'orangtua/surah.php', 'book'],
        ['Biodata Orang Tua', 'orangtua/profile.php', 'settings'],
    ],
];
?>
<div class="sidebar-overlay screen-only" data-sidebar-toggle></div>
<aside class="sidebar screen-only" aria-label="Navigasi utama">
    <div class="sidebar-brand">
        <a class="brand" href="<?= url(dashboard_path($role)) ?>">
            <img src="<?= url('assets/images/logo.png') ?>" alt="Logo SD Islam Labschool Bani Saleh">
            <span>
                <strong>NGAJI YUK</strong>
                <small>SD ISLAM LABSCHOOL BANI SALEH</small>
            </span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <p class="nav-caption"><?= $role === 'admin' ? 'Admin Panel' : ($role === 'guru' ? 'Menu Utama' : 'Laporan') ?></p>
        <?php foreach ($menus[$role] ?? [] as [$label, $href, $icon]): ?>
            <?php $active = str_ends_with($currentPath, '/' . $href); ?>
            <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= url($href) ?>">
                <span class="nav-icon"><?= svg_icon($icon, 19) ?></span>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <p class="nav-caption">Sistem</p>
        <button class="nav-link nav-button" type="button" data-guide-open>
            <span class="nav-icon"><?= svg_icon('help', 19) ?></span>
            <span>Panduan Penggunaan</span>
        </button>
        <?php if ($role === 'guru'): ?>
            <a class="nav-link" href="<?= url('guru/profile.php') ?>">
                <span class="nav-icon"><?= svg_icon('settings', 19) ?></span>
                <span>Profil Guru</span>
            </a>
        <?php endif; ?>
        <a class="nav-link logout-link" href="<?= url('logout.php') ?>">
            <span class="nav-icon"><?= svg_icon('logout', 19) ?></span>
            <span>Keluar</span>
        </a>
    </nav>
</aside>
