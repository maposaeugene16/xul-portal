<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['Administrator','Head'], true)) {
    http_response_code(403);
    exit('Access denied.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    http_response_code(419);
    exit('This form has expired. Refresh the page and try again.');
}

$operation = (string)($_POST['operation'] ?? '');
$recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
$reason = trim((string)($_POST['reason'] ?? ''));
$candidateNumber = strtoupper(trim((string)($_POST['candidate_number'] ?? '')));
if (!$recordId || !in_array($operation, ['withdraw','candidate_number','reinstate'], true)
    || (in_array($operation, ['withdraw','reinstate'], true) && (strlen($reason) < 5 || strlen($reason) > 255))
    || ($operation === 'candidate_number' && ($candidateNumber === '' || strlen($candidateNumber) > 24))) {
    $_SESSION['error'] = 'Provide a valid action, candidate number or reason.';
    header('Location: ../?page=exams');
    exit;
}

$pdo = db();
$sessionId = 0;
try {
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT ec.*,es.id AS session_id,es.status AS session_status,es.exam_year,es.opens_on,es.entry_deadline FROM exam_candidates ec JOIN exam_sessions es ON es.id=ec.session_id WHERE ec.id=:id FOR UPDATE');
    $find->execute(['id'=>$recordId]);
    $entry = $find->fetch();
    if (!$entry) throw new DomainException('Candidate entry not found.');
    $sessionId = (int)$entry['session_id'];

    if ($operation === 'withdraw') {
        if (!in_array($entry['status'], ['Draft','Submitted'], true)) throw new DomainException('This candidate entry cannot be withdrawn.');
        $update = $pdo->prepare("UPDATE exam_candidates SET status='Withdrawn',withdrawal_reason=:reason WHERE id=:id");
        $update->execute(['reason'=>$reason,'id'=>$recordId]);
        audit_log('exams.candidate_withdrawn','exam_candidates',(int)$recordId,['reason'=>$reason]);
        $_SESSION['notice'] = 'Candidate withdrawal recorded with a reason.';
    } elseif ($operation === 'reinstate') {
        if ($entry['status'] !== 'Withdrawn') throw new DomainException('Only a withdrawn candidate can be restored.');
        $today = date('Y-m-d');
        if ($entry['session_status'] !== 'Open' || $today < $entry['opens_on'] || $today > $entry['entry_deadline']) {
            throw new DomainException('A withdrawn candidate can be restored only while the examination entry window is open.');
        }
        $update = $pdo->prepare("UPDATE exam_candidates SET status='Draft',withdrawal_reason=NULL,submitted_by=NULL,submitted_at=NULL WHERE id=:id AND status='Withdrawn'");
        $update->execute(['id'=>$recordId]);
        audit_log('exams.candidate_reinstated','exam_candidates',(int)$recordId,['session_id'=>$sessionId,'reason'=>$reason]);
        $_SESSION['notice'] = 'Candidate restored as a draft. Review the subjects and candidate details before resubmitting.';
    } else {
        if ($entry['status'] !== 'Submitted') throw new DomainException('Record ZIMSEC candidate numbers only for submitted entries.');
        $update = $pdo->prepare('UPDATE exam_candidates SET candidate_number=:number WHERE id=:id');
        $update->execute(['number'=>$candidateNumber,'id'=>$recordId]);
        audit_log('exams.candidate_number_recorded','exam_candidates',(int)$recordId,['exam_year'=>(int)$entry['exam_year']]);
        $_SESSION['notice'] = 'ZIMSEC candidate number recorded.';
    }

    $pdo->commit();
    header('Location: ../?page=exams&session='.$sessionId);
    exit;
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Exam candidate action failed: '.$e->getMessage());
    $_SESSION['error'] = ($e instanceof PDOException && $e->getCode() === '23000')
        ? 'That candidate number is already recorded in this session.'
        : 'Could not update this candidate entry.';
}
header('Location: ../?page=exams'.($sessionId ? '&session='.$sessionId : ''));
exit;
