<?php
declare(strict_types=1);

function collect_student_form(bool $editing = false): array {
    $data = [
        'first_name'=>trim((string)($_POST['first_name'] ?? '')),
        'last_name'=>trim((string)($_POST['last_name'] ?? '')),
        'admission_no'=>strtoupper(trim((string)($_POST['admission_no'] ?? ''))),
        'national_id'=>trim((string)($_POST['national_id'] ?? '')),
        'sex'=>(string)($_POST['sex'] ?? ''),
        'date_of_birth'=>trim((string)($_POST['date_of_birth'] ?? '')),
        'form_level'=>filter_var($_POST['form_level'] ?? null, FILTER_VALIDATE_INT),
        'stream'=>trim((string)($_POST['stream'] ?? '')),
        'address'=>trim((string)($_POST['address'] ?? '')),
        'guardian_name'=>trim((string)($_POST['guardian_name'] ?? '')),
        'guardian_relationship'=>trim((string)($_POST['guardian_relationship'] ?? '')),
        'guardian_phone'=>trim((string)($_POST['guardian_phone'] ?? '')),
        'guardian_email'=>strtolower(trim((string)($_POST['guardian_email'] ?? ''))),
        'consent_status'=>(string)($_POST['guardian_consent_status']??(isset($_POST['guardian_consent'])?'Granted':'Pending')),
        'consent_method'=>(string)($_POST['consent_method'] ?? ''),
        'consent_reference'=>trim((string)($_POST['consent_reference'] ?? '')),
        'status'=>(string)($_POST['status'] ?? 'Active'),
    ];
    $error = null;
    if ($data['first_name']==='' || $data['last_name']==='' || strlen($data['first_name'])>80 || strlen($data['last_name'])>80 || strlen($data['admission_no'])<2 || strlen($data['admission_no'])>30) $error='Enter a first name, last name and a valid admission number.';
    elseif (!in_array($data['sex'],['F','M'],true) || $data['form_level']===false || $data['form_level']<1 || $data['form_level']>6) $error='Choose a valid sex and Form 1–6.';
    elseif ($data['date_of_birth']!=='' && !valid_school_date($data['date_of_birth'])) $error='Enter a valid date of birth that is not in the future.';
    elseif (strlen($data['national_id'])>30 || strlen($data['stream'])>20 || strlen($data['address'])>1000) $error='One of the learner fields is longer than allowed.';
    elseif (($data['guardian_name']!=='' || $data['guardian_relationship']!=='' || $data['guardian_phone']!=='' || $data['guardian_email']!=='') && ($data['guardian_name']==='' || $data['guardian_relationship']==='' || $data['guardian_phone']==='')) $error='For a guardian record, provide the name, relationship and phone number.';
    elseif ($data['guardian_name']!=='' && (!in_array($data['guardian_relationship'],['Parent','Legal guardian','Grandparent','Other relative','Other'],true) || strlen($data['guardian_name'])>160 || strlen($data['guardian_email'])>160)) $error='Choose a valid guardian relationship and check the contact details.';
    elseif ($data['guardian_phone']!=='' && !preg_match('/^[+0-9()\s-]{7,30}$/',$data['guardian_phone'])) $error='Enter a valid guardian phone number.';
    elseif ($data['guardian_email']!=='' && !filter_var($data['guardian_email'],FILTER_VALIDATE_EMAIL)) $error='Enter a valid guardian email address.';
    elseif (!in_array($data['consent_status'],['Pending','Granted','Revoked'],true)) $error='Choose a valid guardian consent status.';
    elseif ($data['consent_status']!=='Pending' && $data['guardian_name']==='') $error='Enter the guardian details before recording a consent decision.';
    elseif (strlen($data['consent_reference'])>180) $error='Consent reference must be 180 characters or fewer.';
    elseif ($data['consent_status']==='Granted' && !in_array($data['consent_method'],['Written','Electronic','In person'],true)) $error='Choose how guardian consent was recorded.';
    elseif ($editing && !in_array($data['status'],['Active','Transferred','Withdrawn','Graduated'],true)) $error='Choose a valid learner status.';
    return [$data,$error];
}

function valid_school_date(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) return false;
    $value = DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    $problems = DateTimeImmutable::getLastErrors();
    return $value !== false && $value->format('Y-m-d') === $date && ($problems === false || ($problems['warning_count']===0 && $problems['error_count']===0)) && $date <= date('Y-m-d');
}

function current_school_term(PDO $pdo): array {
    $terms=$pdo->query('SELECT academic_year,term_number FROM school_terms WHERE is_current=1 ORDER BY academic_year DESC,term_number DESC LIMIT 2')->fetchAll();
    if (count($terms)>1) throw new DomainException('More than one school term is marked current. Correct the term calendar in Academics before enrolling learners.');
    if (!$terms) throw new DomainException('No current school term is configured. Set the current term in Academics before enrolling learners.');
    return ['year'=>(int)$terms[0]['academic_year'],'term'=>(int)$terms[0]['term_number']];
}

function save_primary_guardian(PDO $pdo, int $studentId, array $data, int $userId): array {
    $find=$pdo->prepare('SELECT sg.id,sg.full_name,sg.relationship,sg.phone,sg.email,sg.consent_status,l.user_id FROM student_guardians sg LEFT JOIN guardian_user_links l ON l.guardian_id=sg.id WHERE sg.student_id=:student AND sg.is_primary=1 ORDER BY sg.id LIMIT 1 FOR UPDATE');
    $find->execute(['student'=>$studentId]);
    $existing=$find->fetch()?:null;
    $guardianId=$existing?(int)$existing['id']:null;
    $portalUserId=$existing&&$existing['user_id']?(int)$existing['user_id']:null;
    $portalAccessRevoked=false;

    if ($data['guardian_name']==='') {
        if ($existing) {
            if ($portalUserId) {
                $unlink=$pdo->prepare('DELETE FROM guardian_user_links WHERE guardian_id=:guardian');
                $unlink->execute(['guardian'=>$guardianId]);
                $portalAccessRevoked=true;
                audit_log('guardian.portal_link_revoked_after_contact_removal','guardian_user_links',$guardianId,['portal_user_id'=>$portalUserId]);
            }
            $revoke=$pdo->prepare("UPDATE student_guardians SET consent_status='Revoked',consent_method=NULL,consent_recorded_at=NOW(),consent_reference=NULL,recorded_by=:user WHERE id=:id");
            $revoke->execute(['user'=>$userId,'id'=>$guardianId]);
            return ['portal_access_revoked'=>$portalAccessRevoked,'consent_status'=>'Revoked'];
        }
        return ['portal_access_revoked'=>false,'consent_status'=>'Pending'];
    }

    $status=$data['consent_status']??'Pending';
    if ($existing && $portalUserId) {
        $identityChanged=$existing['full_name']!==$data['guardian_name']
            || $existing['relationship']!==$data['guardian_relationship']
            || $existing['phone']!==$data['guardian_phone']
            || strtolower((string)$existing['email'])!==strtolower((string)($data['guardian_email']?:''));
        if ($identityChanged || $status!=='Granted') {
            $unlink=$pdo->prepare('DELETE FROM guardian_user_links WHERE guardian_id=:guardian');
            $unlink->execute(['guardian'=>$guardianId]);
            $portalAccessRevoked=true;
            if ($identityChanged && $status!=='Revoked') $status='Pending';
            $data['consent_reference']='';
            audit_log('guardian.portal_link_revoked','guardian_user_links',$guardianId,['portal_user_id'=>$portalUserId,'reason'=>$identityChanged?'contact_changed':'consent_'.$status]);
        }
    }

    $values=['student'=>$studentId,'name'=>$data['guardian_name'],'relationship'=>$data['guardian_relationship'],'phone'=>$data['guardian_phone'],'email'=>$data['guardian_email']?:null,'consent'=>$status,'method'=>$status==='Granted'?$data['consent_method']:null,'recorded'=>$status!=='Pending'?date('Y-m-d H:i:s'):null,'reference'=>$data['consent_reference']?:null,'user'=>$userId];
    if ($guardianId) {
        $stmt=$pdo->prepare('UPDATE student_guardians SET full_name=:name,relationship=:relationship,phone=:phone,email=:email,consent_status=:consent,consent_method=:method,consent_recorded_at=:recorded,consent_reference=:reference,recorded_by=:user WHERE id=:id');
        unset($values['student']);
        $stmt->execute($values+['id'=>$guardianId]);
    } else {
        $stmt=$pdo->prepare('INSERT INTO student_guardians (student_id,full_name,relationship,phone,email,is_primary,consent_status,consent_method,consent_recorded_at,consent_reference,recorded_by) VALUES (:student,:name,:relationship,:phone,:email,1,:consent,:method,:recorded,:reference,:user)');
        $stmt->execute($values);
    }
    return ['portal_access_revoked'=>$portalAccessRevoked,'consent_status'=>$status];
}

function add_student_enrolment(PDO $pdo, int $studentId, array $data, int $userId): void {
    $term=current_school_term($pdo);
    $class=$pdo->prepare('SELECT id FROM class_groups WHERE academic_year=:year AND form_level=:form AND stream=:stream FOR UPDATE');
    $class->execute(['year'=>$term['year'],'form'=>$data['form_level'],'stream'=>$data['stream']?:'']);
    if(!$class->fetchColumn())throw new DomainException('Create the matching form and stream under Academics → Classes before enrolling this learner.');
    $stmt=$pdo->prepare("INSERT INTO student_enrolments (student_id,academic_year,term_number,form_level,stream,start_date,status,recorded_by) VALUES (:student,:year,:term,:form,:stream,CURDATE(),'Enrolled',:user)");
    $stmt->execute(['student'=>$studentId,'year'=>$term['year'],'term'=>$term['term'],'form'=>$data['form_level'],'stream'=>$data['stream']?:null,'user'=>$userId]);
}
