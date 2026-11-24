<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_records.php';
$user=current_user();
if (!$user || !in_array($user['role'],['Administrator','Head','Clerk'],true)) { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD']!=='POST') { header('Location: ../?page=students'); exit; }
if (!verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
$studentId=filter_var($_POST['student_id'] ?? null,FILTER_VALIDATE_INT);
[$data,$error]=collect_student_form(true);
if (!$studentId) $error='The learner record is missing.';
if ($error) { $_SESSION['error']=$error; header('Location: ../?page=students'.($studentId?'&edit='.$studentId:'')); exit; }
$pdo=db();
try {
    $pdo->beginTransaction();
    $find=$pdo->prepare('SELECT * FROM students WHERE id=:id FOR UPDATE');
    $find->execute(['id'=>$studentId]);
    $old=$find->fetch();
    if (!$old) { $pdo->rollBack(); $_SESSION['error']='Learner record not found.'; header('Location: ../?page=students'); exit; }
    $classChanged=(int)$old['form_level']!==(int)$data['form_level'] || (string)($old['stream'] ?? '')!==$data['stream'];
    if ($classChanged) { $pdo->rollBack(); $_SESSION['error']='Use Learners → Class progression to change a learner’s form or stream so the dated enrolment history remains accurate.'; header('Location: ../?page=students&edit='.(int)$studentId); exit; }
    $update=$pdo->prepare('UPDATE students SET admission_no=:admission,first_name=:first,last_name=:last,national_id=:national,sex=:sex,date_of_birth=:dob,form_level=:form,stream=:stream,status=:status,guardian_name=:guardian,guardian_phone=:phone,guardian_email=:email,address=:address WHERE id=:id');
    $update->execute(['admission'=>$data['admission_no'],'first'=>$data['first_name'],'last'=>$data['last_name'],'national'=>$data['national_id']?:null,'sex'=>$data['sex'],'dob'=>$data['date_of_birth']?:null,'form'=>$data['form_level'],'stream'=>$data['stream']?:null,'status'=>$data['status'],'guardian'=>$data['guardian_name']?:null,'phone'=>$data['guardian_phone']?:null,'email'=>$data['guardian_email']?:null,'address'=>$data['address']?:null,'id'=>$studentId]);
    $guardianSave=save_primary_guardian($pdo,(int)$studentId,$data,(int)$user['id']);

    $statusChanged=$old['status']!==$data['status'];
    $active=$pdo->prepare('SELECT id,form_level FROM student_enrolments WHERE student_id=:id AND end_date IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $active->execute(['id'=>$studentId]);
    $currentEnrolment=$active->fetch();
    if ($currentEnrolment && ($classChanged || $statusChanged)) {
        $historyStatus='ClassChanged';
        if ($data['status']==='Transferred') $historyStatus='Transferred';
        elseif ($data['status']==='Withdrawn') $historyStatus='Withdrawn';
        elseif ($data['status']==='Graduated') $historyStatus='Completed';
        elseif ($classChanged && (int)$data['form_level']>(int)$old['form_level']) $historyStatus='Promoted';
        $close=$pdo->prepare('UPDATE student_enrolments SET end_date=CURDATE(),status=:status,change_reason=:reason WHERE id=:id');
        $close->execute(['status'=>$historyStatus,'reason'=>$classChanged?'Class placement/status updated':null,'id'=>$currentEnrolment['id']]);
    }
    if ($data['status']==='Active' && (!$currentEnrolment || $classChanged || $statusChanged)) add_student_enrolment($pdo,(int)$studentId,$data,(int)$user['id']);
    audit_log('student.updated','students',(int)$studentId,['admission_no'=>$data['admission_no'],'form_level'=>$data['form_level'],'status'=>$data['status'],'guardian_consent_status'=>$guardianSave['consent_status']]);
    $pdo->commit();
    $_SESSION['notice']=$guardianSave['portal_access_revoked']?'Learner record updated. The old guardian portal link was removed; verify consent again before granting new access.':'Learner record updated successfully.';
    header('Location: ../?page=students&id='.(int)$studentId); exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Student update failed: '.$e->getMessage());
    $_SESSION['error']=$e->getCode()==='23000'?'That admission number belongs to another learner.':'The learner could not be updated. Please try again.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Student update failed: '.$e->getMessage());
    $_SESSION['error']='The learner could not be updated. Please try again.';
}
header('Location: ../?page=students&edit='.(int)$studentId); exit;
