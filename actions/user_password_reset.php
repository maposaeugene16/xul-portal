<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$admin=current_user();
if(!$admin||$admin['role']!=='Administrator'){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}

$id=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
$password=(string)($_POST['temporary_password']??'');
$confirm=(string)($_POST['password_confirm']??'');
$back='../?page=settings&tab=users'.($id?'&edit_user='.(int)$id:'');
if(!$id||$id===(int)$admin['id']||strlen($password)<12||strlen($password)>1024||!hash_equals($password,$confirm)){
    $_SESSION['error']='Choose another staff account and enter matching temporary passwords of at least 12 characters.';
    header('Location: '.$back);exit;
}

$pdo=db();
try{
    $pdo->beginTransaction();
    $find=$pdo->prepare("SELECT id FROM users WHERE id=:id AND role IN ('Administrator','Head','Teacher','Bursar','Clerk') FOR UPDATE");
    $find->execute(['id'=>$id]);
    if(!$find->fetchColumn())throw new DomainException('Staff account not found.');
    $update=$pdo->prepare('UPDATE users SET password_hash=:hash,must_change_password=1 WHERE id=:id');
    $update->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);
    audit_log('user.temporary_password_reset','users',(int)$id,['forced_change'=>true]);
    $pdo->commit();
    $_SESSION['notice']='Temporary password set. Share it privately; the staff member must change it at next sign-in.';
}catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Staff password reset failed: '.$e->getMessage());$_SESSION['error']='Could not reset this staff password.';}
header('Location: '.$back);exit;
