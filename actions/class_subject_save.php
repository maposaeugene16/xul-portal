<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php'; require_once __DIR__ . '/../includes/auth.php';
$user=current_user();if(!$user||!in_array($user['role'],['Administrator','Head'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$class=filter_var($_POST['class_id']??null,FILTER_VALIDATE_INT);$subject=filter_var($_POST['subject_id']??null,FILTER_VALIDATE_INT);$teacher=filter_var($_POST['teacher_id']??null,FILTER_VALIDATE_INT);$teacher=$teacher?:null;
if(!$class||!$subject){$_SESSION['error']='Select a class and subject.';header('Location: ../?page=academics&tab=classes');exit;}
try{if($teacher){$valid=db()->prepare("SELECT COUNT(*) FROM users WHERE id=:id AND role='Teacher' AND active=1");$valid->execute(['id'=>$teacher]);if(!(int)$valid->fetchColumn())throw new DomainException('Choose an active teacher.');}$stmt=db()->prepare('INSERT INTO class_subjects (class_id,subject_id,teacher_id) VALUES (:class,:subject,:teacher) ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)');$stmt->execute(['class'=>$class,'subject'=>$subject,'teacher'=>$teacher]);audit_log('class_subject.assigned','class_subjects',null,['class_id'=>$class,'subject_id'=>$subject,'teacher_id'=>$teacher]);$_SESSION['notice']='Subject and teacher assignment saved.';}catch(DomainException $e){$_SESSION['error']=$e->getMessage();}catch(PDOException $e){error_log('Class subject assignment failed: '.$e->getMessage());$_SESSION['error']='Could not save this subject assignment.';}
header('Location: ../?page=academics&tab=classes');exit;
