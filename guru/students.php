<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();
$students = $pdo->query(
    "SELECT s.*, u.full_name teacher_name,
            EXISTS(SELECT 1 FROM parent_student_links l WHERE l.student_id=s.id AND l.status='active') has_parent
     FROM students s LEFT JOIN users u ON u.id=s.teacher_id
     ORDER BY CAST(LEFT(s.kelas,1) AS UNSIGNED), s.kelas, s.nama_lengkap"
)->fetchAll();
$editing = null;
if (!empty($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $statement->execute([(string) $_GET['edit']]);
    $editing = $statement->fetch() ?: null;
}
$grouped = [];
foreach ($students as $student) {
    $grouped[$student['kelas']][] = $student;
}
$pageTitle = 'Daftar Siswa Lengkap';
require ROOT_PATH . '/includes/header.php';
?>
<div class="page-head">
    <div><h1 class="page-title">Daftar Siswa Lengkap</h1><p class="page-description">Kelola dan lihat seluruh daftar siswa yang terintegrasi.</p></div>
    <div class="header-filters">
        <select class="select" data-student-class-filter aria-label="Filter kelas"><option value="">Semua Kelas</option><?php foreach (all_class_names() as $class): ?><option value="<?= $class ?>"><?= $class ?></option><?php endforeach; ?></select>
        <select class="select" data-student-level-filter aria-label="Filter jenjang"><option value="">Semua Jenjang</option><?php for ($level=1;$level<=9;$level++): ?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor; ?></select>
        <input class="input" style="min-width:210px" data-search-items="#student-groups" placeholder="Cari nama atau NIS..." aria-label="Cari siswa">
        <a class="btn btn-soft" href="<?= url('api/import/students.php?action=template') ?>"><?= svg_icon('file',16) ?> Template Excel</a>
        <button class="btn btn-blue" type="button" data-modal-open="import-students"><?= svg_icon('file',16) ?> Import</button>
        <button class="btn btn-bright" type="button" data-modal-open="student-form">+ Tambah Siswa</button>
    </div>
</div>

<section class="card" id="student-groups">
    <h2 class="card-title"><?= svg_icon('users') ?> Total <?= count($students) ?> Siswa</h2>
    <?php foreach ($grouped as $class => $classStudents): ?>
        <div class="student-group" data-class-group="<?= e($class) ?>">
            <div class="group-heading"><div class="group-title"><span class="group-number"><?= e($class) ?></span><h2 style="margin:0;font-size:18px">Kelas <?= e($class) ?></h2></div><span class="badge badge-gray"><?= count($classStudents) ?> Siswa</span></div>
            <div class="student-grid">
                <?php foreach ($classStudents as $student): ?>
                    <?php $initial = mb_strtoupper(implode('', array_map(static fn($part) => mb_substr($part,0,1), array_slice(preg_split('/\s+/', trim($student['nama_lengkap'])) ?: [], 0, 2)))); ?>
                    <article class="student-card" data-search-text="<?= e($student['nama_lengkap'].' '.$student['nis'].' '.$student['kelas']) ?>" data-class="<?= e($student['kelas']) ?>" data-level="<?= (int)$student['level'] ?>">
                        <div class="student-card-head"><div class="student-initial"><?= e($initial ?: 'S') ?></div><div><h3><?= e($student['nama_lengkap']) ?></h3><div class="student-meta"><span class="badge badge-gray">NIS: <?= e($student['nis']) ?></span><span class="badge badge-green">Kelas <?= e($student['kelas']) ?></span><span class="badge badge-blue"><?= e(level_name($student['level'])) ?></span></div></div></div>
                        <div class="student-card-actions"><a class="btn btn-blue btn-sm" href="<?= url('guru/students.php?edit='.$student['id']) ?>">Edit</a><form action="<?= url('api/students/index.php') ?>" method="post" data-ajax><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= e($student['id']) ?>"><button class="btn btn-danger btn-sm" type="submit" data-confirm="Hapus siswa beserta seluruh nilai dan laporan?">Hapus</button></form></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$students): ?><div class="empty"><strong>Belum ada siswa</strong>Tambahkan satu per satu atau impor file XLSX/CSV.</div><?php endif; ?>
</section>

<div class="modal <?= $editing ? 'open' : '' ?>" id="student-form" aria-hidden="<?= $editing ? 'false' : 'true' ?>"><div class="modal-card"><div class="modal-head"><div><p class="eyebrow">Biodata Siswa</p><h2><?= $editing ? 'Edit Siswa' : 'Tambah Siswa Baru' ?></h2></div><button class="modal-close" type="button" data-modal-close aria-label="Tutup">&times;</button></div><form action="<?= url('api/students/index.php') ?>" method="post" data-ajax class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><input type="hidden" name="student_id" value="<?= e($editing['id'] ?? '') ?>"><div class="field full"><label>Nama Lengkap</label><input class="input" name="nama_lengkap" maxlength="190" required value="<?= e($editing['nama_lengkap'] ?? '') ?>"></div><div class="field"><label>NIS</label><input class="input" name="nis" maxlength="80" required value="<?= e($editing['nis'] ?? '') ?>"></div><div class="field"><label>Kelas</label><select class="select" name="kelas"><?php foreach(all_class_names() as $class):?><option <?= ($editing['kelas']??'1A')===$class?'selected':'' ?>><?= $class ?></option><?php endforeach;?></select></div><div class="field"><label>Level Tahfidz</label><select class="select" name="level"><?php for($level=1;$level<=9;$level++):?><option value="<?= $level ?>" <?= (int)($editing['level']??1)===$level?'selected':'' ?>><?= e(level_name($level)) ?></option><?php endfor;?></select></div><div class="field"><label>Status</label><select class="select" name="status"><?php foreach(['aktif'=>'Aktif','tidak_aktif'=>'Tidak Aktif','pindah'=>'Pindah','lulus'=>'Lulus'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($editing['status']??'aktif')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="field"><label>Jenis Kelamin</label><select class="select" name="jenis_kelamin"><option value="">-</option><option value="L" <?= ($editing['jenis_kelamin']??'')==='L'?'selected':'' ?>>Laki-laki</option><option value="P" <?= ($editing['jenis_kelamin']??'')==='P'?'selected':'' ?>>Perempuan</option></select></div><div class="field"><label>NIK (16 digit)</label><input class="input" name="nik" inputmode="numeric" pattern="[0-9]{16}" value="<?= e($editing['nik']??'') ?>"></div><div class="field"><label>Tempat/Tanggal Lahir</label><input class="input" name="tempat_tanggal_lahir" value="<?= e($editing['tempat_tanggal_lahir']??'') ?>"></div><div class="field"><label>Nama Ayah</label><input class="input" name="nama_ayah" value="<?= e($editing['nama_ayah']??'') ?>"></div><div class="field"><label>Nama Ibu</label><input class="input" name="nama_ibu" value="<?= e($editing['nama_ibu']??'') ?>"></div><div class="field"><label>Wali Murid</label><input class="input" name="wali_murid" value="<?= e($editing['wali_murid']??'') ?>"></div><div class="field"><label>No. Telepon</label><input class="input" name="no_telp" value="<?= e($editing['no_telp']??'') ?>"></div><div class="field full"><label>Alamat</label><textarea class="textarea" name="alamat"><?= e($editing['alamat']??'') ?></textarea></div><button class="btn btn-primary field full" type="submit">Simpan Data Siswa</button></form></div></div>
<div class="modal" id="import-students" aria-hidden="true"><div class="modal-card"><div class="modal-head"><div><p class="eyebrow">Impor Data</p><h2>Impor Excel Siswa</h2></div><button class="modal-close" type="button" data-modal-close aria-label="Tutup">&times;</button></div><div class="alert alert-info">Sistem mendeteksi kolom NIS dan Nama Peserta Didik. Format XLSX dan CSV didukung.</div><form action="<?= url('api/import/students.php') ?>" method="post" enctype="multipart/form-data" data-ajax class="form-grid"><?= csrf_field() ?><div class="field full"><label>File Excel</label><input class="input" type="file" name="file" accept=".xlsx,.csv" required></div><div class="field"><label>Kelas Default</label><select class="select" name="kelas"><?php foreach(all_class_names() as $class):?><option><?= $class ?></option><?php endforeach;?></select></div><div class="field"><label>Level Default</label><select class="select" name="level"><?php for($level=1;$level<=9;$level++):?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor;?></select></div><button class="btn btn-primary field full" type="submit">Impor dan Sinkronkan Berdasarkan NIS</button></form></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{const c=document.querySelector('[data-student-class-filter]'),l=document.querySelector('[data-student-level-filter]');const apply=()=>{document.querySelectorAll('.student-card').forEach(x=>x.hidden=!!c.value&&x.dataset.class!==c.value||!!l.value&&x.dataset.level!==l.value);document.querySelectorAll('.student-group').forEach(g=>g.hidden=![...g.querySelectorAll('.student-card')].some(x=>!x.hidden));};c?.addEventListener('change',apply);l?.addEventListener('change',apply);});
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
