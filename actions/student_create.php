<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_records.php';
$user = current_user();
if (!$user || !in_array($user['role'], ['Administrator','Head','Clerk'], true)) { http_response_code(403); exit('Access denied.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../?page=students'); exit; }
if (!verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }

[$data,$error]=collect_student_form();
if ($error) { $_SESSION['error']=$error; header('Location: ../?page=students&add=1'); exit; }

$pdo = db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO students (admission_no,first_name,last_name,national_id,sex,date_of_birth,form_level,stream,guardian_name,guardian_phone,guardian_email,address) VALUES (:admission,:first,:last,:national,:sex,:dob,:form,:stream,:guardian,:phone,:email,:address)');
    $stmt->execute(['admission'=>$data['admission_no'],'first'=>$data['first_name'],'last'=>$data['last_name'],'national'=>$data['national_id']?:null,'sex'=>$data['sex'],'dob'=>$data['date_of_birth']?:null,'form'=>$data['form_level'],'stream'=>$data['stream']?:null,'guardian'=>$data['guardian_name']?:null,'phone'=>$data['guardian_phone']?:null,'email'=>$data['guardian_email']?:null,'address'=>$data['address']?:null]);
    $studentId=(int)$pdo->lastInsertId();
    save_primary_guardian($pdo,$studentId,$data,(int)$user['id']);
    add_student_enrolment($pdo,$studentId,$data,(int)$user['id']);
    $termContext=current_school_term($pdo);
    audit_log('student.created','students',$studentId,['form_level'=>$data['form_level'],'academic_year'=>$termContext['year'],'guardian_consent_status'=>$data['consent_status']]);
    $pdo->commit();
    $_SESSION['notice']='Learner enrolled successfully.';
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error']=$e->getMessage();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Student create failed: '.$e->getMessage());
    $_SESSION['error']=$e->getCode()==='23000'?'That admission number already exists. Check the record and try again.':'The learner could not be saved. Please try again or contact your administrator.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Student create failed: '.$e->getMessage());
    $_SESSION['error']='The learner could not be saved. Please try again or contact your administrator.';
}
header('Location: ../?page=students'); exit;
