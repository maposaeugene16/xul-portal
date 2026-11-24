<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('MAVAMBOSESSID');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
    $now=time();
    $expired=(!empty($_SESSION['last_activity'])&&$now-(int)$_SESSION['last_activity']>1800)||(!empty($_SESSION['session_started'])&&$now-(int)$_SESSION['session_started']>43200);
    if($expired){$_SESSION=[];session_regenerate_id(true);$_SESSION['session_expired']=true;}
    $_SESSION['session_started']=$_SESSION['session_started']??$now;
    $_SESSION['last_activity']=$now;
}

function production_mode(): bool { return strtolower((string)(getenv('SCHOOL_APP_ENV')?:'development'))==='production'; }

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function current_user(): ?array {
    static $loaded = false;
    static $user = null;
    if ($loaded) return $user;
    $loaded = true;
    $id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) return null;
    $stmt = db()->prepare('SELECT id, full_name, email, role, must_change_password FROM users WHERE id = :id AND active = 1 LIMIT 1');
    $stmt->execute(['id'=>$id]);
    $user = $stmt->fetch() ?: null;
    if (!$user) unset($_SESSION['user_id']);
    return $user;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.h(csrf_token()).'">'; }

function verify_csrf(): bool {
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

function role_can_access_page(string $role, string $page): bool {
    $permissions = [
        'Administrator'=>['dashboard','students','academics','attendance','fees','expenses','reports','communications','library','welfare','admissions','inventory','exams','staffing','payroll','boarding','transport','activities','audit','settings'],
        'Head'=>['dashboard','students','academics','attendance','fees','expenses','reports','communications','library','welfare','admissions','inventory','exams','staffing','payroll','boarding','transport','activities'],
        'Teacher'=>['dashboard','students','academics','attendance','reports','communications','library','welfare','staffing','payroll','activities'],
        'Bursar'=>['dashboard','students','fees','expenses','reports','inventory','staffing','payroll'],
        'Clerk'=>['dashboard','students','attendance','communications','library','admissions','inventory','exams','staffing','payroll','boarding','transport'],
        'Guardian'=>['communications','boarding','transport','activities'],
    ];
    $permissions['Administrator'][]='account';
    $permissions['Head'][]='account';
    $permissions['Teacher'][]='account';
    $permissions['Bursar'][]='account';
    $permissions['Clerk'][]='account';
    $permissions['Guardian'][]='account';
    return in_array($page, $permissions[$role] ?? [], true);
}

function require_login(): array {
    $user = current_user();
    if (!$user) { header('Location: /'); exit; }
    return $user;
}

function audit_log(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void {
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (:user_id,:action,:entity_type,:entity_id,:details,:ip)');
    $stmt->execute([
        'user_id'=>$_SESSION['user_id'] ?? null,
        'action'=>$action,
        'entity_type'=>$entity,
        'entity_id'=>$entityId,
        'details'=>$details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        'ip'=>filter_var($_SERVER['REMOTE_ADDR'] ?? null, FILTER_VALIDATE_IP) ?: null,
    ]);
}
