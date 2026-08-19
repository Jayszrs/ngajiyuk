<?php
declare(strict_types=1);
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$role = $user['role'];
$menus = [
  'admin' => [
    ['Dashboard Monitoring','admin/dashboard.php','⌁'],
    ['Monitoring Guru','admin/teachers.php','♙'],
    ['Monitoring Orang Tua','admin/parents.php','↗'],
    ['Siswa & Kelas','admin/students-classes.php','♜'],
    ['Persetujuan Akun','admin/users.php','♢'],
    ['Kelengkapan Laporan','admin/completeness.php','▣'],
    ['Audit Aktivitas','admin/audit.php','◴'],
  ],
  'guru' => [
    ['Dashboard','guru/dashboard.php','⌁'],
    ['Daftar Siswa','guru/students.php','♙'],
    ['Data Kelas','guru/classes.php','♜'],
    ['Presensi & Harian','guru/daily-reports.php','▣'],
    ['Ujian Kenaikan Level','guru/level-exams.php','♢'],
    ['Form Munaqosyah','guru/munaqosyah.php','♙'],
    ['Data Surat','guru/surah.php','▤'],
    ['Komposisi Nilai','guru/composition.php','♢'],
    ['3 Rapor Otomatis','guru/reports.php','▧'],
  ],
  'orang_tua' => [
    ['Dashboard Anak','orangtua/dashboard.php','⌁'],
    ['Komposisi Nilai','orangtua/composition.php','♢'],
    ['Data Surat','orangtua/surah.php','▤'],
    ['Biodata Orang Tua','orangtua/profile.php','⚙'],
  ],
];
?>
<aside class="sidebar screen-only">
  <div class="sidebar-brand"><a class="brand" href="<?= url(dashboard_path($role)) ?>"><img src="<?= url('assets/images/logo.png') ?>" alt="Logo"><span><strong><?= APP_NAME ?></strong><small><?= APP_SUBTITLE ?></small></span></a></div>
  <nav class="sidebar-nav">
    <p class="nav-caption"><?= $role === 'admin' ? 'Admin Panel' : ($role === 'guru' ? 'Menu Utama' : 'Laporan') ?></p>
    <?php foreach ($menus[$role] ?? [] as [$label,$href,$icon]): $active = str_ends_with($currentPath, '/' . $href); ?>
      <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= url($href) ?>"><span class="nav-icon"><?= $icon ?></span><?= e($label) ?></a>
    <?php endforeach; ?>
    <p class="nav-caption">Sistem</p>
    <?php if ($role === 'guru'): ?><a class="nav-link" href="<?= url('guru/profile.php') ?>"><span class="nav-icon">⚙</span>Profil Guru</a><?php endif; ?>
    <a class="nav-link" href="<?= url('logout.php') ?>"><span class="nav-icon">↪</span>Keluar</a>
  </nav>
</aside>

