<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$user=current_user();
if (!$user || !in_array($user['role'],['Administrator','Head','Clerk'],true)) { http_response_code(403); exit('Access denied.'); }
$where=[]; $params=[];
$query=trim((string)($_GET['q'] ?? ''));
$form=filter_var($_GET['form'] ?? null,FILTER_VALIDATE_INT);
$status=(string)($_GET['status'] ?? 'Active');
$sex=(string)($_GET['sex'] ?? '');
if ($query!=='') { $where[]='(first_name LIKE :q1 OR last_name LIKE :q2 OR admission_no LIKE :q3)'; $params += ['q1'=>'%'.$query.'%','q2'=>'%'.$query.'%','q3'=>'%'.$query.'%']; }
if ($form!==false && $form>=1 && $form<=6) { $where[]='form_level=:form'; $params['form']=$form; }
if (in_array($status,['Active','Transferred','Withdrawn','Graduated'],true)) { $where[]='status=:status'; $params['status']=$status; }
if (in_array($sex,['F','M'],true)) { $where[]='sex=:sex'; $params['sex']=$sex; }
$sql='SELECT admission_no,first_name,last_name,form_level,stream,sex,status FROM students'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY form_level,stream,last_name,first_name';
$stmt=db()->prepare($sql); $stmt->execute($params);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="mavambo-learners-'.date('Y-m-d').'.csv"');
header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','wb');
fwrite($out,"\xEF\xBB\xBF");
$sanitize=static function($value): string {
    $value=(string)$value;
    return preg_match('/^[\s]*[=+@-]/u',$value) ? "'".$value : $value;
};
fputcsv($out,['Admission number','First name','Last name','Form','Stream','Sex','Status']);
while ($row=$stmt->fetch(PDO::FETCH_NUM)) fputcsv($out,array_map($sanitize,$row));
fclose($out);
