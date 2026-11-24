<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';require_once __DIR__ . '/../includes/auth.php';
$user=current_user();if(!$user||!in_array($user['role'],['Administrator','Head','Clerk'],true)){http_response_code(403);exit('Access denied.');}if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired.');}unset($_SESSION['student_import_preview']);header('Location: ../?page=students');exit;
