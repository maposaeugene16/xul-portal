<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head','Clerk'],true)){http_response_code(403);exit('Access denied.');}
$id=filter_var($_GET['session_id']??null,FILTER_VALIDATE_INT);
if(!$id){http_response_code(400);exit('A valid exam session is required.');}
$pdo=db();
$q=$pdo->prepare('SELECT id,exam_year,level,session_name,entry_type,centre_number FROM exam_sessions WHERE id=:id');
$q->execute(['id'=>$id]);$session=$q->fetch();
if(!$session){http_response_code(404);exit('Exam session not found.');}
$sql="SELECT ec.status,ec.withdrawal_reason,ec.candidate_type,ec.payment_type,ec.nationality,ec.special_requirements,ec.candidate_number,ec.surname_snapshot,ec.first_names_snapshot,ec.sex_snapshot,ec.date_of_birth_snapshot,ec.national_id_snapshot,s.admission_no,es.centre_number,es.exam_year,es.level,es.session_name,es.entry_type,sub.subject_code,sub.subject_name,cs.is_repeat,cs.previous_year,cs.previous_centre_number,cs.previous_candidate_number FROM exam_candidates ec JOIN exam_sessions es ON es.id=ec.session_id JOIN students s ON s.id=ec.student_id LEFT JOIN exam_candidate_subjects cs ON cs.candidate_id=ec.id LEFT JOIN subjects sub ON sub.id=cs.subject_id WHERE es.id=:session ORDER BY ec.surname_snapshot,ec.first_names_snapshot,sub.subject_name";
$q=$pdo->prepare($sql);$q->execute(['session'=>$id]);$rows=$q->fetchAll();
audit_log('exams.register_exported','exam_sessions',(int)$id,['row_count'=>count($rows)]);

function export_csv_cell(mixed $value):string{
    $value=(string)$value;
    if(preg_match('/^[\s\x00-\x1F]*[=+@-]/u',$value))$value="'".$value;
    return $value;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="exam-register-'.$session['exam_year'].'-'.$session['level'].'-'.$id.'.csv"');
header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
$headers=['Centre number','Exam year','Level','Session','Entry type','Candidate number','Admission number','Surname','First names','Date of birth','Sex','National ID','Nationality','Candidate type','Payment type','Special requirements','Subject code','Subject name','Repeat subject','Previous year','Previous centre number','Previous candidate number','Local entry status','Withdrawal reason'];
fputcsv($out,$headers);
foreach($rows as $r){
    $values=[
        $r['centre_number'],$r['exam_year'],$r['level'],$r['session_name'],$r['entry_type'],$r['candidate_number'],
        $r['admission_no'],$r['surname_snapshot'],$r['first_names_snapshot'],$r['date_of_birth_snapshot'],$r['sex_snapshot'],
        $r['national_id_snapshot'],$r['nationality'],$r['candidate_type'],$r['payment_type'],$r['special_requirements'],
        $r['subject_code'],$r['subject_name'],$r['is_repeat']?'Yes':'No',$r['previous_year'],$r['previous_centre_number'],
        $r['previous_candidate_number'],$r['status'],$r['withdrawal_reason'],
    ];
    fputcsv($out,array_map('export_csv_cell',$values));
}
fclose($out);exit;
