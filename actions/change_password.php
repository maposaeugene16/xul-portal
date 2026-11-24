<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$user=current_user();
if (!$user) { header('Location: /'); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST' || !verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
$current=(string)($_POST['current_password'] ?? '');
$next=(string)($_POST['new_password'] ?? '');
$confirm=(string)($_POST['confirm_password'] ?? '');
$stmt=db()->prepare('SELECT password_hash FROM users WHERE id=:id'); $stmt->execute(['id'=>$user['id']]); $hash=$stmt->fetchColumn();
$error=null;
if (!$hash || !password_verify($current,(string)$hash)) $error='Current password is incorrect.';
elseif (strlen($next)<12) $error='New password must contain at least 12 characters.';
elseif (!hash_equals($next,$confirm)) $error='The new passwords do not match.';
elseif (hash_equals($current,$next)) $error='Choose a different password from your current one.';
if ($error) { $_SESSION['error']=$error; header('Location: ../?page=account'); exit; }
$update=db()->prepare('UPDATE users SET password_hash=:hash,must_change_password=0 WHERE id=:id');
$update->execute(['hash'=>password_hash($next,PASSWORD_DEFAULT),'id'=>$user['id']]);
session_regenerate_id(true);
unset($_SESSION['csrf_token']);
audit_log('auth.password_changed','users',(int)$user['id']);
$_SESSION['notice']='Password updated successfully.';
header('Location: ../?page=account'); exit;
