<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
$admin = require_api_user('admin');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = db()->query('SELECT id,username,email,full_name,role,approval_status,is_active,last_login_at,created_at FROM users ORDER BY created_at DESC')->fetchAll();
    json_response(true, 'Daftar pengguna berhasil dimuat.', $rows);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(false, 'Metode tidak diizinkan.', null, 405);
verify_csrf(); $data=request_data(); $action=(string)($data['action']??''); $targetId=(string)($data['user_id']??''); $pdo=db();

if ($action === 'create') {
    $name=trim((string)($data['full_name']??''));$username=trim((string)($data['username']??''));$email=trim((string)($data['email']??''))?:null;$role=(string)($data['role']??'');$password=(string)($data['password']??'');$confirm=(string)($data['password_confirmation']??'');
    if($name===''||!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$username)) throw new RuntimeException('Nama lengkap dan username valid wajib diisi.');
    if(!in_array($role,['admin','guru','orang_tua'],true)) throw new RuntimeException('Role tidak valid.');
    if($email!==null&&!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Format email tidak valid.');
    if(strlen($password)<8||$password!==$confirm) throw new RuntimeException('Password minimal 8 karakter dan konfirmasi harus sama.');
    $id=uuidv4();$pdo->beginTransaction();
    try{$pdo->prepare('INSERT INTO users (id,username,email,password_hash,full_name,role,approval_status,is_active,approved_at,approved_by) VALUES (?,?,?,?,?,?,\'approved\',1,NOW(),?)')->execute([$id,$username,$email,password_hash($password,PASSWORD_DEFAULT),$name,$role,$admin['id']]);$pdo->prepare('INSERT INTO user_roles (id,user_id,email,role) VALUES (?,?,?,?)')->execute([uuidv4(),$id,$email,$role]);if($role==='guru')$pdo->prepare('INSERT INTO teacher_profiles(user_id,full_name)VALUES(?,?)')->execute([$id,$name]);$pdo->commit();audit_event('account_created','success',$id,['target'=>$username,'role'=>$role]);json_response(true,'Akun berhasil dibuat.',['id'=>$id]);}catch(Throwable $e){$pdo->rollBack();if($e instanceof PDOException)throw new RuntimeException('Username atau email sudah digunakan.');throw $e;}
}

$stmt=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');$stmt->execute([$targetId]);$target=$stmt->fetch();if(!$target)throw new RuntimeException('Akun tidak ditemukan.');

switch($action){
case 'approve':$pdo->prepare("UPDATE users SET approval_status='approved',is_active=1,approved_at=NOW(),approved_by=? WHERE id=?")->execute([$admin['id'],$targetId]);audit_event('account_approved','success',$targetId,['target'=>$target['username']]);json_response(true,'Akun berhasil disetujui.');
case 'reject':$pdo->prepare("UPDATE users SET approval_status='rejected',is_active=0,updated_at=NOW() WHERE id=?")->execute([$targetId]);audit_event('account_rejected','success',$targetId,['target'=>$target['username']]);json_response(true,'Akun ditolak dan dinonaktifkan.');
case 'toggle_active':$active=filter_var($data['active']??false,FILTER_VALIDATE_BOOL);$pdo->prepare('UPDATE users SET is_active=?,updated_at=NOW() WHERE id=?')->execute([$active?1:0,$targetId]);audit_event($active?'account_activated':'account_deactivated','success',$targetId,['target'=>$target['username']]);json_response(true,$active?'Akun diaktifkan.':'Akun dinonaktifkan.');
case 'change_role':$role=(string)($data['role']??'');if(!in_array($role,['admin','guru','orang_tua'],true))throw new RuntimeException('Role tidak valid.');if($targetId===$admin['id']&&$role!=='admin')throw new RuntimeException('Admin tidak dapat menurunkan role akun yang sedang digunakan.');$pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET role=?,updated_at=NOW() WHERE id=?')->execute([$role,$targetId]);$pdo->prepare('UPDATE user_roles SET role=?,updated_at=NOW() WHERE user_id=?')->execute([$role,$targetId]);if($role==='guru')$pdo->prepare('INSERT INTO teacher_profiles(user_id,full_name)VALUES(?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name)')->execute([$targetId,$target['full_name']]);$pdo->commit();audit_event('role_changed','success',$targetId,['from'=>$target['role'],'to'=>$role,'target'=>$target['username']]);json_response(true,'Role akun berhasil diubah.');}catch(Throwable $e){$pdo->rollBack();throw $e;}
case 'change_password':$password=(string)($data['password']??'');$confirm=(string)($data['password_confirmation']??'');if(strlen($password)<8||$password!==$confirm)throw new RuntimeException('Password minimal 8 karakter dan konfirmasi harus sama.');$pdo->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$targetId]);audit_event('password_changed','success',$targetId,['source'=>'administrator','target'=>$target['username']]);json_response(true,'Password berhasil diganti dan langsung dapat digunakan.');
case 'delete':if($targetId===$admin['id'])throw new RuntimeException('Akun yang sedang digunakan tidak dapat dihapus.');if($target['role']==='admin'){ $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();if($count<=1)throw new RuntimeException('Admin terakhir tidak dapat dihapus.');}$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$targetId]);audit_event('account_deleted','success',null,['deleted_username'=>$target['username'],'role'=>$target['role']]);json_response(true,'Akun berhasil dihapus.');
case 'link_child':if($target['role']!=='orang_tua')throw new RuntimeException('Target bukan akun Orang Tua.');$nis=trim((string)($data['nis']??''));$student=$pdo->prepare('SELECT id,nama_lengkap FROM students WHERE nis=? LIMIT 1');$student->execute([$nis]);$child=$student->fetch();if(!$child)throw new RuntimeException('NIS anak tidak ditemukan.');$occupied=$pdo->prepare("SELECT parent_id FROM parent_student_links WHERE student_id=? AND status='active' AND parent_id<>? LIMIT 1");$occupied->execute([$child['id'],$targetId]);if($occupied->fetch())throw new RuntimeException('Anak sudah terhubung ke akun Orang Tua lain.');$pdo->prepare("INSERT INTO parent_student_links(parent_id,student_id,status)VALUES(?,?,'active') ON DUPLICATE KEY UPDATE student_id=VALUES(student_id),status='active',updated_at=NOW()")->execute([$targetId,$child['id']]);audit_event('parent_child_linked','success',$targetId,['student'=>$child['nama_lengkap'],'nis'=>$nis]);json_response(true,'Akun Orang Tua berhasil dihubungkan dengan anak.');
case 'unlink_child':$pdo->prepare("UPDATE parent_student_links SET status='inactive',updated_at=NOW() WHERE parent_id=?")->execute([$targetId]);audit_event('parent_child_unlinked','success',$targetId,['target'=>$target['username']]);json_response(true,'Hubungan anak dinonaktifkan.');
default:throw new RuntimeException('Aksi pengguna tidak dikenali.');}

