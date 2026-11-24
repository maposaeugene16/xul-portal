<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/student_records.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$applicationId=filter_var($_POST['application_id']??null,FILTER_VALIDATE_INT);
$admissionNo=strtoupper(trim((string)($_POST['admission_no']??'')));
$termId=filter_var($_POST['term_id']??null,FILTER_VALIDATE_INT);
$stream=trim((string)($_POST['stream']??''));
if(!$applicationId||strlen($admissionNo)<2||strlen($admissionNo)>30||!$termId||$stream===''||strlen($stream)>20){$_SESSION['error']='Enter a valid admission number, term and configured class stream.';header('Location: ../?page=admissions');exit;}

$pdo=db();
try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM admission_applications WHERE id=:id FOR UPDATE');
    $q->execute(['id'=>$applicationId]);$app=$q->fetch();
    if(!$app||$app['status']!=='Accepted'||$app['student_id']!==null)throw new DomainException('Only an accepted application that has not been enrolled can be converted.');
    $today=date('Y-m-d');
    $q=$pdo->prepare('SELECT id,academic_year,term_number,starts_on,ends_on FROM school_terms WHERE id=:id FOR UPDATE');
    $q->execute(['id'=>$termId]);$term=$q->fetch();
    if(!$term||(int)$term['academic_year']!==(int)$app['entry_year']||$term['ends_on']<$today)throw new DomainException('Choose a configured term for the application’s entry year that has not ended.');
    $enrolmentStart=max($today,$term['starts_on']);
    $q=$pdo->prepare('SELECT id FROM class_groups WHERE academic_year=:year AND form_level=:form AND stream=:stream FOR UPDATE');
    $q->execute(['year'=>$term['academic_year'],'form'=>$app['intended_form'],'stream'=>$stream]);
    if(!$q->fetch())throw new DomainException('Create or select a class group for this form, stream and entry year before enrolling the learner.');
    $q=$pdo->prepare("INSERT INTO students(admission_no,first_name,last_name,national_id,sex,date_of_birth,form_level,stream,status,guardian_name,guardian_phone,guardian_email,address) VALUES(:admission,:first,:last,:national,:sex,:dob,:form,:stream,'Active',:guardian,:phone,:email,:address)");
    $q->execute(['admission'=>$admissionNo,'first'=>$app['first_name'],'last'=>$app['last_name'],'national'=>$app['national_id'],'sex'=>$app['sex'],'dob'=>$app['date_of_birth'],'form'=>$app['intended_form'],'stream'=>$stream,'guardian'=>$app['guardian_name'],'phone'=>$app['guardian_phone'],'email'=>$app['guardian_email'],'address'=>$app['address']]);
    $studentId=(int)$pdo->lastInsertId();
    $guardianData=['guardian_name'=>$app['guardian_name'],'guardian_relationship'=>$app['guardian_relationship'],'guardian_phone'=>$app['guardian_phone'],'guardian_email'=>$app['guardian_email']??'','consent_status'=>'Pending','consent_method'=>'','consent_reference'=>''];
    save_primary_guardian($pdo,$studentId,$guardianData,(int)$user['id']);
    $q=$pdo->prepare("INSERT INTO student_enrolments(student_id,academic_year,term_number,form_level,stream,start_date,status,change_reason,recorded_by) VALUES(:student,:year,:term,:form,:stream,:start,'Enrolled','Admitted from application',:user)");
    $q->execute(['student'=>$studentId,'year'=>$term['academic_year'],'term'=>$term['term_number'],'form'=>$app['intended_form'],'stream'=>$stream,'start'=>$enrolmentStart,'user'=>$user['id']]);
    $q=$pdo->prepare("UPDATE admission_applications SET status='Enrolled',student_id=:student WHERE id=:id AND status='Accepted' AND student_id IS NULL");
    $q->execute(['student'=>$studentId,'id'=>$applicationId]);
    if($q->rowCount()!==1)throw new DomainException('This application has already been enrolled.');
    $q=$pdo->prepare("INSERT INTO admission_status_history(application_id,from_status,to_status,notes,changed_by) VALUES(:id,'Accepted','Enrolled','Created learner and school-term enrollment',:user)");
    $q->execute(['id'=>$applicationId,'user'=>$user['id']]);
    audit_log('admissions.learner_enrolled','admission_applications',$applicationId,['student_id'=>$studentId,'term_id'=>$termId,'form_level'=>(int)$app['intended_form']]);
    audit_log('student.created_from_admission','students',$studentId,['application_code'=>$app['application_code'],'form_level'=>(int)$app['intended_form']]);
    $pdo->commit();$_SESSION['notice']='Learner enrolled from application '.$app['application_code'].'.';header('Location: ../?page=students&id='.$studentId);exit;
}catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();}
catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Admission enrollment failed: '.$e->getMessage());$_SESSION['error']=$e->getCode()==='23000'?'That admission number already exists. Choose a unique school admission number.':'Could not convert the application. No learner record was created.';}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Admission enrollment failed: '.$e->getMessage());$_SESSION['error']='Could not convert the application. No learner record was created.';}
header('Location: ../?page=admissions');exit;
