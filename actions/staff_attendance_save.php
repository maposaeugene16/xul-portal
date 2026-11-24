<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$date=trim((string)($_POST['attendance_date']??''));$entries=is_array($_POST['staff']??null)?$_POST['staff']:[];
$dateObject=DateTimeImmutable::createFromFormat('!Y-m-d',$date);$dateErrors=DateTimeImmutable::getLastErrors();
$validDate=preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)===1&&$dateObject!==false&&$dateObject->format('Y-m-d')===$date&&($dateErrors===false||(!$dateErrors['warning_count']&&!$dateErrors['error_count']))&&$date<=date('Y-m-d');
if(!$validDate||!$entries){$_SESSION['error']='Choose a valid school date and record at least one staff member.';header('Location: ../?page=staffing');exit;}
$allowed=['Present','Late','Absent','On Leave','Off Duty'];
$timeOk=static function(string $time):bool{if(!preg_match('/^\d{2}:\d{2}$/',$time))return false;$d=DateTimeImmutable::createFromFormat('!H:i',$time);$e=DateTimeImmutable::getLastErrors();return $d!==false&&$d->format('H:i')===$time&&($e===false||(!$e['warning_count']&&!$e['error_count']));};
$pdo=db();$saved=0;
try{
    $pdo->beginTransaction();
    $userCheck=$pdo->prepare("SELECT id FROM users WHERE id=:id AND active=1 AND role IN ('Administrator','Head','Teacher','Bursar','Clerk') FOR UPDATE");
    $find=$pdo->prepare('SELECT id FROM staff_attendance WHERE staff_id=:staff AND attendance_date=:date FOR UPDATE');
    $insert=$pdo->prepare('INSERT INTO staff_attendance(staff_id,attendance_date,status,check_in,check_out,note,recorded_by) VALUES(:staff,:date,:status,:check_in,:check_out,:note,:recorder)');
    $update=$pdo->prepare('UPDATE staff_attendance SET status=:status,check_in=:check_in,check_out=:check_out,note=:note,recorded_by=:recorder WHERE id=:id');
    foreach($entries as $staffId=>$data){
        $staffId=filter_var((string)$staffId,FILTER_VALIDATE_INT);if(!$staffId||!is_array($data))throw new DomainException('A staff register row is invalid.');
        $status=(string)($data['status']??'');if($status==='')continue;if(!in_array($status,$allowed,true))throw new DomainException('Choose a valid staff attendance status.');
        $in=trim((string)($data['check_in']??''));$out=trim((string)($data['check_out']??''));$note=trim((string)($data['note']??''));
        if(($in!==''&&!$timeOk($in))||($out!==''&&!$timeOk($out))||($in!==''&&$out!==''&&$out<$in)||strlen($note)>255)throw new DomainException('Check the staff check-in/out times and note length.');
        if(in_array($status,['Absent','On Leave','Off Duty'],true)){$in='';$out='';}
        $userCheck->execute(['id'=>$staffId]);if(!$userCheck->fetch())throw new DomainException('A selected staff account is inactive or does not exist.');
        $find->execute(['staff'=>$staffId,'date'=>$date]);$existing=$find->fetchColumn();
        $values=['staff'=>$staffId,'date'=>$date,'status'=>$status,'check_in'=>$in?:null,'check_out'=>$out?:null,'note'=>$note?:null,'recorder'=>$user['id']];
        if($existing)$update->execute($values+['id'=>$existing]);else $insert->execute($values);
        $saved++;
    }
    if(!$saved)throw new DomainException('Select a status for at least one staff member.');
    audit_log('staff.attendance_register_saved','staff_attendance',null,['date'=>$date,'staff_count'=>$saved]);
    $pdo->commit();$_SESSION['notice']='Staff attendance saved for '.$saved.' staff member(s).';
}catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Staff attendance save failed: '.$e->getMessage());$_SESSION['error']='Could not save the staff register. No attendance changes were kept.';}
header('Location: ../?page=staffing&date='.rawurlencode($date));exit;
