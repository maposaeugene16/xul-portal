<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php'; require_once __DIR__ . '/../includes/auth.php';
$admin=current_user();
if (!$admin || $admin['role']!=='Administrator') { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
$id=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT); $role=(string)($_POST['role']??''); $active=isset($_POST['active'])?1:0;
$number=trim((string)($_POST['staff_number']??'')); $title=trim((string)($_POST['job_title']??'')); $department=trim((string)($_POST['department']??'')); $phone=trim((string)($_POST['phone']??''));
$roles=['Administrator','Head','Teacher','Bursar','Clerk'];
if (!$id || $id===(int)$admin['id'] || !in_array($role,$roles,true)||strlen($number)>40||strlen($title)>100||strlen($department)>100||strlen($phone)>30) { $_SESSION['error']='Choose valid account details. Your own role and access cannot be changed here.'; header('Location: ../?page=settings&tab=users'); exit; }
$pdo=db();
try {
    $pdo->beginTransaction();
    $find=$pdo->prepare('SELECT id,role,active FROM users WHERE id=:id FOR UPDATE'); $find->execute(['id'=>$id]); $old=$find->fetch();
    if (!$old) { $pdo->rollBack(); $_SESSION['error']='Staff account not found.'; header('Location: ../?page=settings&tab=users'); exit; }
    if ($old['role']==='Administrator' && $old['active'] && ($role!=='Administrator'||!$active)) {
        // Serialize administrator removals. Without a shared lock, two simultaneous updates
        // could each see two active administrators and remove both accounts.
        $schoolLock=$pdo->query('SELECT id FROM school_profile WHERE id=1 FOR UPDATE')->fetchColumn();
        if (!$schoolLock) { $pdo->rollBack(); $_SESSION['error']='School settings are missing. Restore the school profile before changing administrator access.'; header('Location: ../?page=settings&tab=users&edit_user='.$id); exit; }
        $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='Administrator' AND active=1")->fetchColumn();
        if ($count<=1) { $pdo->rollBack(); $_SESSION['error']='At least one active administrator account must remain.'; header('Location: ../?page=settings&tab=users&edit_user='.$id); exit; }
    }
    $update=$pdo->prepare('UPDATE users SET role=:role,active=:active WHERE id=:id'); $update->execute(['role'=>$role,'active'=>$active,'id'=>$id]);
    $staff=$pdo->prepare('INSERT INTO staff_profiles (user_id,staff_number,job_title,department,phone) VALUES (:id,:number,:title,:department,:phone) ON DUPLICATE KEY UPDATE staff_number=VALUES(staff_number),job_title=VALUES(job_title),department=VALUES(department),phone=VALUES(phone)');
    $staff->execute(['id'=>$id,'number'=>$number?:null,'title'=>$title?:null,'department'=>$department?:null,'phone'=>$phone?:null]);
    audit_log('user.updated','users',(int)$id,['previous_role'=>$old['role'],'role'=>$role,'active'=>(bool)$active]);
    $pdo->commit(); $_SESSION['notice']='Staff account updated.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Staff account update failed: '.$e->getMessage()); $_SESSION['error']=$e->getCode()==='23000'?'That staff number is already assigned.':'Could not update this staff account.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Staff account update failed: '.$e->getMessage()); $_SESSION['error']='Could not update this staff account.';
}
header('Location: ../?page=settings&tab=users'); exit;
