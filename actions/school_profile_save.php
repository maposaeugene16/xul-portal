<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php'; require_once __DIR__ . '/../includes/auth.php';
$user=current_user();
if (!$user || $user['role']!=='Administrator') { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
$data=['school_name'=>trim((string)($_POST['school_name']??'')),'school_code'=>trim((string)($_POST['school_code']??'')),'school_type'=>(string)($_POST['school_type']??''),'province'=>(string)($_POST['province']??''),'district'=>trim((string)($_POST['district']??'')),'address'=>trim((string)($_POST['address']??'')),'phone'=>trim((string)($_POST['phone']??'')),'email'=>strtolower(trim((string)($_POST['email']??''))),'website'=>trim((string)($_POST['website']??''))];
$provinces=['','Bulawayo','Harare','Manicaland','Mashonaland Central','Mashonaland East','Mashonaland West','Masvingo','Matabeleland North','Matabeleland South','Midlands'];
if ($data['school_name']===''||strlen($data['school_name'])>180||!in_array($data['school_type'],['Government','Mission','Private','Trust','Other'],true)||!in_array($data['province'],$provinces,true)||strlen($data['address'])>2000||($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))||($data['website']!==''&&!filter_var($data['website'],FILTER_VALIDATE_URL))) { $_SESSION['error']='Check the school profile fields and try again.'; header('Location: ../?page=settings'); exit; }
$stmt=db()->prepare('UPDATE school_profile SET school_name=:school_name,school_code=:school_code,school_type=:school_type,province=:province,district=:district,address=:address,phone=:phone,email=:email,website=:website WHERE id=1');
$stmt->execute($data); audit_log('school.profile_updated','school_profile',1); $_SESSION['notice']='School profile saved.'; header('Location: ../?page=settings'); exit;
