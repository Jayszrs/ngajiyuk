<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$user=require_api_user();$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
  if($user['role']==='orang_tua'){$studentId=parent_student_id($user['id']);if(!$studentId)json_response(true,'Belum ada anak terhubung.',[]);$stmt=$pdo->prepare('SELECT * FROM students WHERE id=?');$stmt->execute([$studentId]);$rows=$stmt->fetchAll();}
  else{$class=normalize_class_name((string)($_GET['kelas']??''));$sql='SELECT s.*,u.full_name AS teacher_name FROM students s LEFT JOIN users u ON u.id=s.teacher_id WHERE 1=1';$params=[];if($class!==''){$sql.=' AND s.kelas=?';$params[]=$class;}$sql.=' ORDER BY s.kelas,s.nama_lengkap';$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();}
  json_response(true,'Data siswa berhasil dimuat.',$rows);
}
if($_SERVER['REQUEST_METHOD']!=='POST')json_response(false,'Metode tidak diizinkan.',null,405);$user=require_api_user('admin','guru');verify_csrf();$data=request_data();$action=(string)($data['action']??'');$id=(string)($data['student_id']??'');
if($action==='create'||$action==='update'){
  $name=trim((string)($data['nama_lengkap']??''));$nis=trim((string)($data['nis']??''));$kelas=normalize_class_name((string)($data['kelas']??''));$level=(int)($data['level']??1);$gender=trim((string)($data['jenis_kelamin']??''))?:null;$nik=trim((string)($data['nik']??''))?:null;
  if($name===''||$nis===''||!in_array($kelas,all_class_names(),true)||$level<1||$level>9)throw new RuntimeException('Nama, NIS, kelas 1A–6B, dan level 1–9 wajib valid.');if($gender&&!in_array($gender,['L','P'],true))throw new RuntimeException('Jenis kelamin tidak valid.');if($nik&&!preg_match('/^[0-9]{16}$/',$nik))throw new RuntimeException('NIK harus 16 digit.');
  $values=[$user['role']==='guru'?$user['id']:((string)($data['teacher_id']??'')?:null),$name,$nis,$kelas,$level,$gender,$nik,trim((string)($data['tempat_tanggal_lahir']??''))?:null,trim((string)($data['nama_ayah']??''))?:null,trim((string)($data['nama_ibu']??''))?:null,trim((string)($data['wali_murid']??''))?:null,trim((string)($data['alamat']??''))?:null,trim((string)($data['no_telp']??''))?:null];
  try{if($action==='create'){$id=uuidv4();$pdo->prepare('INSERT INTO students(id,teacher_id,nama_lengkap,nis,kelas,level,jenis_kelamin,nik,tempat_tanggal_lahir,nama_ayah,nama_ibu,wali_murid,alamat,no_telp)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge([$id],$values));}else{if(!$id)throw new RuntimeException('Siswa tidak ditemukan.');$pdo->prepare('UPDATE students SET teacher_id=?,nama_lengkap=?,nis=?,kelas=?,level=?,jenis_kelamin=?,nik=?,tempat_tanggal_lahir=?,nama_ayah=?,nama_ibu=?,wali_murid=?,alamat=?,no_telp=?,updated_at=NOW() WHERE id=?')->execute(array_merge($values,[$id]));}audit_event($action==='create'?'student_created':'student_updated','success',$id,['student'=>$name,'nis'=>$nis,'kelas'=>$kelas]);json_response(true,$action==='create'?'Siswa berhasil ditambahkan.':'Data siswa berhasil diperbarui.',['id'=>$id]);}catch(PDOException $e){throw new RuntimeException(str_contains($e->getMessage(),'Duplicate')?'NIS sudah digunakan siswa lain.':'Data siswa gagal disimpan.');}
}
$stmt=$pdo->prepare('SELECT * FROM students WHERE id=?');$stmt->execute([$id]);$student=$stmt->fetch();if(!$student)throw new RuntimeException('Siswa tidak ditemukan.');
if($action==='status'){$status=(string)($data['status']??'');if(!in_array($status,['aktif','tidak_aktif','pindah','lulus'],true))throw new RuntimeException('Status siswa tidak valid.');$pdo->prepare('UPDATE students SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$id]);audit_event('student_status_changed','success',$id,['student'=>$student['nama_lengkap'],'from'=>$student['status'],'to'=>$status]);json_response(true,'Status siswa diperbarui.');}
if($action==='delete'){$pdo->prepare('DELETE FROM students WHERE id=?')->execute([$id]);audit_event('student_deleted','success',null,['student'=>$student['nama_lengkap'],'nis'=>$student['nis']]);json_response(true,'Siswa dan data terkait berhasil dihapus.');}
throw new RuntimeException('Aksi siswa tidak dikenali.');

