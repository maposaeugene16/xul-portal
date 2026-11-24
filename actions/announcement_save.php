<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

$user=current_user();
if(!$user||!in_array($user['role'],['Administrator','Head'],true)){http_response_code(403);exit('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!verify_csrf()){http_response_code(419);exit('This form has expired. Refresh the page and try again.');}

$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
$operation=(string)($_POST['operation']??'save');
$return='../?page=communications&tab=notices';
$pdo=db();
try{
    if($operation==='archive'){
        if(!$id)throw new DomainException('Select an announcement to archive.');
        $stmt=$pdo->prepare("UPDATE announcements SET status='Archived' WHERE id=:id AND status<>'Archived'");
        $stmt->execute(['id'=>$id]);
        if(!$stmt->rowCount())throw new DomainException('Announcement not found or already archived.');
        audit_log('announcement.archived','announcements',(int)$id);
        $_SESSION['notice']='Announcement archived.';
        header('Location: '.$return);exit;
    }

    $title=trim((string)($_POST['title']??''));
    $body=trim((string)($_POST['body']??''));
    $audience=(string)($_POST['audience']??'');
    $priority=(string)($_POST['priority']??'Normal');
    $status=(string)($_POST['status']??'Draft');
    if($title===''||strlen($title)>180||$body===''||strlen($body)>60000||!in_array($audience,['Everyone','Staff','Guardians'],true)||!in_array($priority,['Normal','Important','Urgent'],true)||!in_array($status,['Draft','Published'],true))throw new DomainException('Enter a title, message, valid audience and priority.');

    $pdo->beginTransaction();
    if($id){
        $find=$pdo->prepare('SELECT id,status,published_at FROM announcements WHERE id=:id FOR UPDATE');
        $find->execute(['id'=>$id]);$old=$find->fetch();
        if(!$old||$old['status']==='Archived')throw new DomainException('This announcement cannot be edited.');
        $publishedAt=$status==='Published'?($old['published_at']?:$pdo->query('SELECT NOW()')->fetchColumn()):null;
        $stmt=$pdo->prepare('UPDATE announcements SET title=:title,body=:body,audience=:audience,priority=:priority,status=:status,published_at=:published WHERE id=:id');
        $stmt->execute(['title'=>$title,'body'=>$body,'audience'=>$audience,'priority'=>$priority,'status'=>$status,'published'=>$publishedAt,'id'=>$id]);
        $action='announcement.updated';
    }else{
        $publishedAt=$status==='Published'?$pdo->query('SELECT NOW()')->fetchColumn():null;
        $stmt=$pdo->prepare('INSERT INTO announcements (title,body,audience,status,priority,published_at,created_by) VALUES (:title,:body,:audience,:status,:priority,:published,:user)');
        $stmt->execute(['title'=>$title,'body'=>$body,'audience'=>$audience,'status'=>$status,'priority'=>$priority,'published'=>$publishedAt,'user'=>$user['id']]);
        $id=(int)$pdo->lastInsertId();$action='announcement.created';
    }
    audit_log($action,'announcements',(int)$id,['audience'=>$audience,'priority'=>$priority,'status'=>$status]);
    $pdo->commit();
    $_SESSION['notice']=$status==='Published'?'Announcement published.':'Draft saved.';
}catch(DomainException $e){
    if($pdo->inTransaction())$pdo->rollBack();$_SESSION['error']=$e->getMessage();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();error_log('Announcement save failed: '.$e->getMessage());$_SESSION['error']='Could not save this announcement.';
}
header('Location: '.$return);exit;
