<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php'; require_once __DIR__ . '/../includes/auth.php';
$user=current_user();if(!$user||!in_array($user['role'],['Administrator','Head'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$code=strtoupper(trim((string)($_POST['subject_code']??'')));$name=trim((string)($_POST['subject_name']??''));$level=(string)($_POST['level']??'');$id=filter_var($_POST['subject_id']??null,FILTER_VALIDATE_INT);
if($code===''||strlen($code)>20||$name===''||strlen($name)>100||!in_array($level,['O-Level','A-Level','Both'],true)){$_SESSION['error']='Enter a subject code, name and valid level.';header('Location: ../?page=academics&tab=subjects');exit;}
try{if($id){$active=(string)($_POST['active']??'0')==='1'?1:0;$toggle=db()->prepare('UPDATE subjects SET active=:active WHERE id=:id');$toggle->execute(['active'=>$active,'id'=>$id]);audit_log('subject.updated','subjects',$id,['active'=>(bool)$active]);}else{$stmt=db()->prepare('INSERT INTO subjects (subject_code,subject_name,level) VALUES (:code,:name,:level)');$stmt->execute(['code'=>$code,'name'=>$name,'level'=>$level]);audit_log('subject.created','subjects',(int)db()->lastInsertId(),['code'=>$code]);}$_SESSION['notice']=$id?'Subject status updated.':'Subject added to the catalog.';}catch(PDOException $e){error_log('Subject save failed: '.$e->getMessage());$_SESSION['error']=$e->getCode()==='23000'?'That subject code is already in use.':'Could not save the subject.';}
header('Location: ../?page=academics&tab=subjects');exit;
