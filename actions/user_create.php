<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php'; require_once __DIR__ . '/../includes/auth.php';
$admin=current_user();
if (!$admin || $admin['role']!=='Administrator') { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
$name=trim((string)($_POST['full_name']??'')); $email=strtolower(trim((string)($_POST['email']??''))); $role=(string)($_POST['role']??''); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['password_confirm']??'');
$staffNo=trim((string)($_POST['staff_number']??'')); $job=trim((string)($_POST['job_title']??'')); $department=trim((string)($_POST['department']??'')); $phone=trim((string)($_POST['phone']??''));
$roles=['Administrator','Head','Teacher','Bursar','Clerk'];
if ($name===''||strlen($name)>160||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||!in_array($role,$roles,true)||strlen($password)<12||!hash_equals($password,$confirm)||strlen($staffNo)>40||strlen($job)>100||strlen($department)>100||strlen($phone)>30) { $_SESSION['error']='Check the account details. Use a 12-character temporary password and make sure both entries match.'; header('Location: ../?page=settings&tab=users&add=1'); exit; }
$pdo=db();
try {
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('INSERT INTO users (full_name,email,password_hash,role,active,must_change_password) VALUES (:name,:email,:hash,:role,1,1)');
    $stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role]);
    $id=(int)$pdo->lastInsertId();
    $staff=$pdo->prepare('INSERT INTO staff_profiles (user_id,staff_number,job_title,department,phone) VALUES (:id,:number,:title,:department,:phone)');
    $staff->execute(['id'=>$id,'number'=>$staffNo?:null,'title'=>$job?:null,'department'=>$department?:null,'phone'=>$phone?:null]);
    audit_log('user.created','users',$id,['role'=>$role]);
    $pdo->commit(); $_SESSION['notice']='Staff account created. The user must change the temporary password at first sign-in.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Staff account create failed: '.$e->getMessage());
    $_SESSION['error']=$e->getCode()==='23000'?'That email address or staff number is already in use.':'Could not create the staff account. Try again.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack(); error_log('Staff account create failed: '.$e->getMessage()); $_SESSION['error']='Could not create the staff account. Try again.';
}
header('Location: ../?page=settings&tab=users'); exit;
