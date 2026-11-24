<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}
$year=filter_var($_POST['exam_year']??null,FILTER_VALIDATE_INT);$level=(string)($_POST['level']??'');
$name=trim((string)($_POST['session_name']??''));$entry=trim((string)($_POST['entry_type']??''));$centre=strtoupper(trim((string)($_POST['centre_number']??'')));
$opens=trim((string)($_POST['opens_on']??''));$deadline=trim((string)($_POST['entry_deadline']??''));$status=(string)($_POST['status']??'Draft');
$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['subject_ids']??[])),fn($id)=>$id>0)));$groups=is_array($_POST['option_groups']??null)?$_POST['option_groups']:[];
$validDate=static function(string $date):bool{if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return false;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);$e=DateTimeImmutable::getLastErrors();return $d!==false&&$d->format('Y-m-d')===$date&&($e===false||(!$e['warning_count']&&!$e['error_count']));};
if(!$year||$year<date('Y')||$year>2100||!in_array($level,['O-Level','A-Level'],true)||$name===''||strlen($name)>80||$entry===''||strlen($entry)>60||$centre===''||strlen($centre)>30||!$validDate($opens)||!$validDate($deadline)||$deadline<$opens||!in_array($status,['Draft','Open'],true)||!$ids){$_SESSION['error']='Check the exam year, level, centre number, dates and offered subjects.';header('Location: ../?page=exams&new=1');exit;}
if($status==='Open'&&($opens>date('Y-m-d')||$deadline<date('Y-m-d'))){$_SESSION['error']='An entry window can be opened only between its opening date and deadline.';header('Location: ../?page=exams&new=1');exit;}
$pdo=db();
try{
    $pdo->beginTransaction();$in=implode(',',array_fill(0,count($ids),'?'));
    $q=$pdo->prepare("SELECT id FROM subjects WHERE active=1 AND (level=? OR level='Both') AND id IN ($in)");$q->execute(array_merge([$level],$ids));$valid=$q->fetchAll(PDO::FETCH_COLUMN);
    if(count($valid)!==count($ids))throw new DomainException('Every offered subject must be active and match the selected examination level.');
    $q=$pdo->prepare('INSERT INTO exam_sessions(exam_year,level,session_name,entry_type,centre_number,opens_on,entry_deadline,status,created_by) VALUES(:year,:level,:name,:entry,:centre,:opens,:deadline,:status,:user)');
    $q->execute(['year'=>$year,'level'=>$level,'name'=>$name,'entry'=>$entry,'centre'=>$centre,'opens'=>$opens,'deadline'=>$deadline,'status'=>$status,'user'=>$user['id']]);$sessionId=(int)$pdo->lastInsertId();
    $offer=$pdo->prepare('INSERT INTO exam_session_subjects(session_id,subject_id,option_group) VALUES(:session,:subject,:group)');
    foreach($ids as $subjectId){$rawGroup=$groups[$subjectId]??'';if(!is_string($rawGroup)&&!is_numeric($rawGroup))throw new DomainException('Enter a valid optional-subject group code.');$group=trim((string)$rawGroup);if(strlen($group)>40)throw new DomainException('Optional-subject group codes must be 40 characters or fewer.');$offer->execute(['session'=>$sessionId,'subject'=>$subjectId,'group'=>$group?:null]);}
    audit_log('exams.session_created','exam_sessions',$sessionId,['year'=>$year,'level'=>$level,'subject_count'=>count($ids)]);$pdo->commit();$_SESSION['notice']='Examination session created with '.count($ids).' offered subjects.';
}catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Exam session creation failed: '.$e->getMessage());$_SESSION['error']=($e instanceof PDOException&&$e->getCode()==='23000')?'That examination session and entry type already exist.':'Could not create the examination session.';}
header('Location: ../?page=exams');exit;
