<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php'; require_once __DIR__ . '/../includes/auth.php';
$user=current_user(); if (!$user||!in_array($user['role'],['Administrator','Head'],true)) {http_response_code(403);exit('Access denied.');}
if ($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()) {http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$form=filter_var($_POST['form_level']??null,FILTER_VALIDATE_INT);$year=filter_var($_POST['academic_year']??null,FILTER_VALIDATE_INT);$stream=trim((string)($_POST['stream']??''));$teacher=filter_var($_POST['class_teacher_id']??null,FILTER_VALIDATE_INT);$teacher=$teacher?:null;
if ($form===false||$form<1||$form>6||$year===false||$year<2000||$year>2100||$stream===''||strlen($stream)>20) {$_SESSION['error']='Enter a valid Form 1–6, academic year and stream.';header('Location: ../?page=academics&tab=classes');exit;}
if ($teacher) {$check=db()->prepare("SELECT COUNT(*) FROM users WHERE id=:id AND role='Teacher' AND active=1");$check->execute(['id'=>$teacher]);if(!(int)$check->fetchColumn()){$_SESSION['error']='Choose an active teacher account.';header('Location: ../?page=academics&tab=classes');exit;}}
try {$stmt=db()->prepare('INSERT INTO class_groups (form_level,stream,academic_year,class_teacher_id) VALUES (:form,:stream,:year,:teacher)');$stmt->execute(['form'=>$form,'stream'=>$stream,'year'=>$year,'teacher'=>$teacher]);$id=(int)db()->lastInsertId();audit_log('class.created','class_groups',$id,['form'=>$form,'stream'=>$stream,'year'=>$year]);$_SESSION['notice']='Class group created.';}catch(PDOException $e){error_log('Class group create failed: '.$e->getMessage());$_SESSION['error']=$e->getCode()==='23000'?'That form, stream and academic year already exists.':'Could not create the class group.';}
header('Location: ../?page=academics&tab=classes');exit;
