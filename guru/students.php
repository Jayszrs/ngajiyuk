<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
$user = require_role('guru');
$pdo = db();

$filterClass = normalize_class_name((string) ($_GET['kelas'] ?? ''));
$filterLevel = (int) ($_GET['level'] ?? 0);
$filterStatus = trim((string) ($_GET['status'] ?? 'aktif'));
$search = trim((string) ($_GET['q'] ?? ''));
if ($filterClass !== '' && !in_array($filterClass, all_class_names(), true)) $filterClass = '';
if ($filterLevel < 0 || $filterLevel > 9) $filterLevel = 0;
if (!in_array($filterStatus, ['', 'aktif', 'tidak_aktif', 'pindah', 'lulus'], true)) $filterStatus = 'aktif';

$sql = "SELECT s.*,u.full_name AS teacher_name,
        EXISTS(SELECT 1 FROM parent_student_links p WHERE p.student_id=s.id AND p.status='active') AS has_parent
        FROM students s LEFT JOIN users u ON u.id=s.teacher_id WHERE 1=1";
$params = [];
if ($filterClass !== '') { $sql .= ' AND s.kelas=?'; $params[] = $filterClass; }
if ($filterLevel > 0) { $sql .= ' AND s.level=?'; $params[] = $filterLevel; }
if ($filterStatus !== '') { $sql .= ' AND s.status=?'; $params[] = $filterStatus; }
if ($search !== '') { $sql .= ' AND (s.nama_lengkap LIKE ? OR s.nis LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= ' ORDER BY CAST(LEFT(s.kelas,1) AS UNSIGNED),s.kelas,s.nama_lengkap';
$statement = $pdo->prepare($sql); $statement->execute($params); $students = $statement->fetchAll();
$groups = []; foreach ($students as $student) $groups[$student['kelas']][] = $student;

$pageTitle = 'Daftar Siswa Lengkap';
require ROOT_PATH . '/includes/header.php';
?>
<style>
.student-page-head{display:grid;grid-template-columns:minmax(250px,.75fr) minmax(650px,2fr);gap:28px;align-items:end;margin-bottom:24px}.student-toolbar{display:grid;grid-template-columns:1fr 1fr minmax(210px,1.5fr) auto auto auto;gap:10px;align-items:end}.student-toolbar .field{margin:0}.student-toolbar label{display:none}.student-toolbar .btn{min-height:46px;white-space:nowrap}.student-group{padding:0;overflow:hidden;margin-bottom:18px}.student-group-head{display:flex;align-items:center;justify-content:space-between;padding:20px 28px;border-bottom:1px solid #e5e9e7}.student-group-title{display:flex;align-items:center;gap:13px}.student-group-title h2{margin:0;font-size:20px}.student-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;padding:22px 28px 28px}.student-card{border:1px solid #dfe6e2;border-radius:16px;padding:20px;background:#fff;min-width:0}.student-card-top{display:flex;gap:14px;align-items:center}.student-card h3{margin:0 0 7px;font-size:17px}.student-tags{display:flex;flex-wrap:wrap;gap:6px}.student-tags .badge{font-size:10px}.student-meta{margin-top:15px;padding-top:14px;border-top:1px solid #eef1ef;color:#6a7785;font-size:12px;display:grid;gap:5px}.student-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.student-photo{width:58px;height:58px;border-radius:14px;object-fit:cover;box-shadow:0 5px 14px rgba(14,82,55,.18)}.photo-upload{display:flex;align-items:center;gap:18px;padding:16px;border:1px dashed #cfd8d3;border-radius:14px;background:#fafcfb}.photo-preview{width:72px;height:72px;border-radius:14px;object-fit:cover;background:#eaf7ef;display:grid;place-items:center;font-weight:900;color:#08764e}.photo-preview[hidden]{display:none!important}@media(max-width:1250px){.student-page-head{grid-template-columns:1fr}.student-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:850px){.student-toolbar{grid-template-columns:1fr 1fr}.student-toolbar .search-wide{grid-column:1/-1}.student-grid{grid-template-columns:1fr;padding:16px}.student-group-head{padding:16px}.student-toolbar .btn{width:100%}}
</style>

<div class="student-page-head">
  <div><p class="eyebrow">DATA PESERTA DIDIK</p><h1 class="page-title">Daftar Siswa Lengkap</h1><p class="page-description">Kelola seluruh data siswa sekolah yang terintegrasi untuk semua akun Guru.</p></div>
  <div class="student-toolbar">
    <form method="get" style="display:contents">
      <div class="field"><label>Kelas</label><select class="select" name="kelas" onchange="this.form.submit()"><option value="">Semua Kelas</option><?php foreach(all_class_names() as $class): ?><option value="<?= e($class) ?>" <?= $filterClass===$class?'selected':'' ?>>Kelas <?= e($class) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Jenjang</label><select class="select" name="level" onchange="this.form.submit()"><option value="0">Semua Jenjang</option><?php for($level=1;$level<=9;$level++): ?><option value="<?= $level ?>" <?= $filterLevel===$level?'selected':'' ?>><?= e(level_name($level)) ?></option><?php endfor; ?></select></div>
      <div class="field search-wide"><label>Pencarian</label><input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau NIS..."></div><input type="hidden" name="status" value="<?= e($filterStatus) ?>">
      <button class="btn btn-soft" type="submit"><?= svg_icon('search',15) ?> Cari</button>
    </form>
    <a class="btn btn-outline" href="<?= url('api/import/students.php?action=template') ?>"><?= svg_icon('file',15) ?> Template Excel</a>
    <button class="btn btn-soft" type="button" data-modal-open="import-students"><?= svg_icon('file',15) ?> Impor</button>
    <button class="btn btn-accent" type="button" data-student-create>+ Tambah Siswa</button>
  </div>
</div>

<section class="card" style="padding:0;overflow:hidden;margin-bottom:20px"><div class="filter-row" style="padding:16px 22px;margin:0"><strong><?= svg_icon('users',18) ?> Total <?= count($students) ?> Siswa</strong><form method="get" class="field" style="margin:0"><input type="hidden" name="kelas" value="<?= e($filterClass) ?>"><input type="hidden" name="level" value="<?= $filterLevel ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><label>Status</label><select class="select" name="status" onchange="this.form.submit()"><option value="" <?= $filterStatus===''?'selected':'' ?>>Semua Status</option><?php foreach(['aktif'=>'Aktif','tidak_aktif'=>'Tidak Aktif','pindah'=>'Pindah','lulus'=>'Lulus'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filterStatus===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></form></div></section>

<?php foreach($groups as $class=>$classStudents): ?><section class="card student-group"><header class="student-group-head"><div class="student-group-title"><span class="class-pill"><?= e($class) ?></span><h2>Kelas <?= e($class) ?></h2></div><span class="badge badge-gray"><?= count($classStudents) ?> Siswa</span></header><div class="student-grid">
<?php foreach($classStudents as $student): $payload=json_encode(['id'=>$student['id'],'nama_lengkap'=>$student['nama_lengkap'],'nis'=>$student['nis'],'kelas'=>$student['kelas'],'level'=>(int)$student['level'],'jenis_kelamin'=>$student['jenis_kelamin'],'nik'=>$student['nik'],'tempat_tanggal_lahir'=>$student['tempat_tanggal_lahir'],'nama_ayah'=>$student['nama_ayah'],'nama_ibu'=>$student['nama_ibu'],'wali_murid'=>$student['wali_murid'],'alamat'=>$student['alamat'],'no_telp'=>$student['no_telp'],'status'=>$student['status'],'foto_url'=>$student['foto_url']],JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT); ?>
<article class="student-card"><div class="student-card-top"><?php if($student['foto_url']): ?><img class="student-photo" src="<?= url($student['foto_url']) ?>" alt="Foto <?= e($student['nama_lengkap']) ?>"><?php else: ?><span class="avatar"><?= e(initials($student['nama_lengkap'],2)) ?></span><?php endif; ?><div style="min-width:0"><h3><?= e($student['nama_lengkap']) ?></h3><div class="student-tags"><span class="badge badge-gray">NIS <?= e($student['nis']) ?></span><span class="badge badge-green">Kelas <?= e($student['kelas']) ?></span><span class="badge badge-blue"><?= e(level_name((int)$student['level'])) ?></span></div></div></div><div class="student-meta"><span>Status: <strong><?= e(ucwords(str_replace('_',' ',$student['status']))) ?></strong></span><span>Guru pengampu: <strong><?= e($student['teacher_name'] ?: 'Belum ditentukan') ?></strong></span><span>Orang tua: <strong><?= $student['has_parent'] ? 'Terhubung' : 'Belum terhubung' ?></strong></span></div><div class="student-actions"><button class="btn btn-soft btn-sm" type="button" data-student-edit='<?= e($payload ?: '{}') ?>'><?= svg_icon('settings',14) ?> Edit</button><form action="<?= url('api/students/index.php') ?>" method="post" data-ajax><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= e($student['id']) ?>"><button class="btn btn-danger btn-sm" type="submit" data-confirm="Hapus <?= e($student['nama_lengkap']) ?> beserta seluruh laporan dan nilai terkait?">Hapus</button></form></div></article>
<?php endforeach; ?></div></section><?php endforeach; ?>
<?php if(!$students): ?><section class="card"><div class="empty"><?= svg_icon('users',42) ?><strong>Data siswa tidak ditemukan</strong>Ubah filter atau tambahkan siswa baru.</div></section><?php endif; ?>

<div class="modal" id="student-form" aria-hidden="true"><div class="modal-card modal-wide"><div class="modal-head"><div><p class="eyebrow">DATA PESERTA DIDIK</p><h2 data-student-title>Tambah Siswa Baru</h2></div><button class="modal-close" type="button" data-modal-close aria-label="Tutup">&times;</button></div><form action="<?= url('api/students/index.php') ?>" method="post" enctype="multipart/form-data" data-ajax class="form-grid form-grid-2" data-student-form><?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="student_id" value="">
<div class="field full"><label>Foto Siswa</label><div class="photo-upload"><img class="photo-preview" data-photo-preview alt="Preview foto" hidden><span class="photo-preview" data-photo-placeholder>?</span><div><input class="input" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG, atau WEBP. Maksimal 2 MB.</small></div></div></div>
<div class="field"><label>Nama Lengkap *</label><input class="input" name="nama_lengkap" maxlength="190" required placeholder="Contoh: Ahmad Fulan"></div><div class="field"><label>Jenis Kelamin</label><select class="select" name="jenis_kelamin"><option value="">Pilih jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
<div class="field"><label>Nomor Induk Siswa (NIS)</label><input class="input" name="nis" maxlength="80" placeholder="Kosongkan untuk dibuat otomatis"></div><div class="field"><label>NIK</label><input class="input" name="nik" inputmode="numeric" maxlength="16" pattern="[0-9]{16}" placeholder="16 digit NIK siswa"></div>
<div class="field"><label>Tempat, Tanggal Lahir</label><input class="input" name="tempat_tanggal_lahir" maxlength="190" placeholder="Bekasi, 12 Agustus 2012"></div><div class="field"><label>Kelas *</label><select class="select" name="kelas" required><?php foreach(all_class_names() as $class): ?><option value="<?= e($class) ?>">Kelas <?= e($class) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Jenjang Tahfidz *</label><select class="select" name="level" required><?php for($level=1;$level<=9;$level++): ?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor; ?></select></div><div class="field"><label>Status Siswa</label><select class="select" name="status"><?php foreach(['aktif'=>'Aktif','tidak_aktif'=>'Tidak Aktif','pindah'=>'Pindah','lulus'=>'Lulus'] as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Nama Ayah</label><input class="input" name="nama_ayah" maxlength="190"></div><div class="field"><label>Nama Ibu</label><input class="input" name="nama_ibu" maxlength="190"></div><div class="field"><label>Nama Wali Murid / Orang Tua</label><input class="input" name="wali_murid" maxlength="190"></div><div class="field"><label>No. Telepon / WhatsApp</label><input class="input" name="no_telp" maxlength="80" inputmode="tel"></div><div class="field full"><label>Alamat Lengkap</label><textarea class="textarea" name="alamat" rows="3"></textarea></div><div class="field full"><button class="btn btn-accent" type="submit" style="width:100%">Simpan Data Siswa</button></div></form></div></div>

<div class="modal" id="import-students" aria-hidden="true"><div class="modal-card"><div class="modal-head"><div><p class="eyebrow">IMPOR DATA</p><h2>Impor Siswa dari Excel</h2></div><button class="modal-close" type="button" data-modal-close>&times;</button></div><form action="<?= url('api/import/students.php') ?>" method="post" enctype="multipart/form-data" data-ajax class="form-grid"><?= csrf_field() ?><div class="field full"><label>File XLSX atau CSV *</label><input class="input" type="file" name="file" accept=".xlsx,.csv" required><small class="muted">Kolom Nama, NIS, Kelas, dan Level akan dideteksi otomatis.</small></div><div class="field full" style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn btn-outline btn-sm" href="<?= url('api/import/students.php?action=template') ?>"><?= svg_icon('file',14) ?> Template XLSX Berlogo</a><a class="btn btn-soft btn-sm" href="<?= url('api/import/students.php?action=template_csv') ?>"><?= svg_icon('file',14) ?> Template CSV</a></div><div class="field"><label>Kelas Default</label><select class="select" name="kelas"><?php foreach(all_class_names() as $class): ?><option value="<?= e($class) ?>"><?= e($class) ?></option><?php endforeach; ?></select></div><div class="field"><label>Level Default</label><select class="select" name="level"><?php for($level=1;$level<=9;$level++): ?><option value="<?= $level ?>"><?= e(level_name($level)) ?></option><?php endfor; ?></select></div><div class="field full"><button class="btn btn-primary" type="submit">Mulai Impor Siswa</button></div></form></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('student-form');
  const form = document.querySelector('[data-student-form]');
  const title = document.querySelector('[data-student-title]');
  const preview = document.querySelector('[data-photo-preview]');
  const placeholder = document.querySelector('[data-photo-placeholder]');

  if (!modal || !form || !title || !preview || !placeholder) return;

  function resetStudentForm() {
    form.reset();
    form.querySelector('[name=action]').value = 'create';
    form.querySelector('[name=student_id]').value = '';
    title.textContent = 'Tambah Siswa Baru';
    preview.hidden = true;
    preview.removeAttribute('src');
    placeholder.hidden = false;
  }

  document.querySelector('[data-student-create]')?.addEventListener('click', function () {
    resetStudentForm();
    window.appOpenModal(modal);
  });

  document.querySelectorAll('[data-student-edit]').forEach(function (button) {
    button.addEventListener('click', function () {
      resetStudentForm();
      const data = JSON.parse(button.dataset.studentEdit || '{}');
      form.querySelector('[name=action]').value = 'update';
      form.querySelector('[name=student_id]').value = data.id || '';

      [
        'nama_lengkap', 'nis', 'kelas', 'level', 'jenis_kelamin', 'nik',
        'tempat_tanggal_lahir', 'nama_ayah', 'nama_ibu', 'wali_murid',
        'alamat', 'no_telp', 'status'
      ].forEach(function (name) {
        const field = form.querySelector('[name="' + name + '"]');
        if (field) field.value = data[name] ?? '';
      });

      title.textContent = 'Edit Data Siswa';
      if (data.foto_url) {
        preview.src = '<?= e(url('')) ?>' + data.foto_url;
        preview.hidden = false;
        placeholder.hidden = true;
      }
      window.appOpenModal(modal);
    });
  });

  form.querySelector('[name=foto]')?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    placeholder.hidden = true;
  });
});
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
