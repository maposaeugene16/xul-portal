<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head','Clerk'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$sessionId=filter_var($_POST['session_id']??null,FILTER_VALIDATE_INT);$studentId=filter_var($_POST['student_id']??null,FILTER_VALIDATE_INT);
$type=(string)($_POST['candidate_type']??'');$payment=(string)($_POST['payment_type']??'');$nationality=(string)($_POST['nationality']??'');
$rawRequirements=$_POST['special_requirements']??[];$requirements=is_array($rawRequirements)?array_values(array_unique($rawRequirements)):null;$allowedReq=['Hearing','Physical','Visual'];$confirmOtherLevel=isset($_POST['confirm_cross_level']);
if(!$sessionId||!$studentId||!in_array($type,['Formal','Nonformal'],true)||!in_array($payment,['Self','BEAM'],true)||!in_array($nationality,['Local','Territorial'],true)||$requirements===null||array_filter($requirements,fn($r)=>!is_string($r))||array_diff($requirements,$allowedReq)){
    $_SESSION['error']='Choose a valid session, learner, candidate type, payment type, nationality and special requirements.';header('Location: ../?page=exams&session='.(int)$sessionId);exit;
}
$pdo=db();
try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT id,exam_year,level,session_name,status,opens_on,entry_deadline FROM exam_sessions WHERE id=:id FOR UPDATE');$q->execute(['id'=>$sessionId]);$session=$q->fetch();$today=date('Y-m-d');
    if(!$session||$session['status']!=='Open'||$today<$session['opens_on']||$today>$session['entry_deadline'])throw new DomainException('This examination entry window is not open.');
    $q=$pdo->prepare("SELECT id,first_name,last_name,sex,date_of_birth,national_id,status FROM students WHERE id=:id FOR UPDATE");$q->execute(['id'=>$studentId]);$student=$q->fetch();
    if(!$student||$student['status']!=='Active')throw new DomainException('Only active learners can be entered for examinations.');
    $q=$pdo->prepare("SELECT COUNT(*) FROM exam_candidates ec JOIN exam_sessions es ON es.id=ec.session_id WHERE ec.student_id=:student AND es.exam_year=:year AND es.session_name=:name AND es.level<>:level AND ec.status<>'Withdrawn'");
    $q->execute(['student'=>$studentId,'year'=>$session['exam_year'],'name'=>$session['session_name'],'level'=>$session['level']]);$crossLevel=(int)$q->fetchColumn()>0;
    if($crossLevel&&!$confirmOtherLevel)throw new DomainException('ZIMSEC advises against entering one candidate at two levels in the same sitting because a timetable clash may affect entries. Confirm the timetable and tick the confirmation if this cross-level entry is intentional.');
    $special=implode(',',$requirements);
    $q=$pdo->prepare('INSERT INTO exam_candidates(session_id,student_id,surname_snapshot,first_names_snapshot,sex_snapshot,date_of_birth_snapshot,national_id_snapshot,candidate_type,payment_type,nationality,special_requirements,created_by) VALUES(:session,:student,:surname,:first,:sex,:dob,:national,:type,:payment,:nationality,:special,:user)');
    $q->execute(['session'=>$sessionId,'student'=>$studentId,'surname'=>$student['last_name'],'first'=>$student['first_name'],'sex'=>$student['sex'],'dob'=>$student['date_of_birth'],'national'=>$student['national_id'],'type'=>$type,'payment'=>$payment,'nationality'=>$nationality,'special'=>$special?:null,'user'=>$user['id']]);
    $candidateId=(int)$pdo->lastInsertId();audit_log('exams.candidate_created','exam_candidates',$candidateId,['session_id'=>$sessionId,'level'=>$session['level'],'payment_type'=>$payment,'cross_level_confirmation'=>$crossLevel]);
    $pdo->commit();$_SESSION['notice']='Candidate added as a draft. Select subjects and check the entry before submitting.';header('Location: ../?page=exams&session='.$sessionId.'&candidate='.$candidateId);exit;
}catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Exam candidate creation failed: '.$e->getMessage());$_SESSION['error']=($e instanceof PDOException&&$e->getCode()==='23000')?'This learner is already on the selected session candidate register.':'Could not add the examination candidate.';}
header('Location: ../?page=exams&session='.(int)$sessionId);exit;
