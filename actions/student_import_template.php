<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';require_once __DIR__ . '/../includes/auth.php';
$user=current_user();if(!$user||!in_array($user['role'],['Administrator','Head','Clerk'],true)){http_response_code(403);exit('Access denied.');}header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="mavambo-learner-import-template.csv"');header('Cache-Control: no-store, private');$out=fopen('php://output','wb');fputcsv($out,['admission_no','first_name','last_name','sex','form_level','stream','date_of_birth','national_id','guardian_name','guardian_relationship','guardian_phone','guardian_email','address']);fclose($out);exit;
