<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }
if (!verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
$blockedUntil = (int)($_SESSION['login_blocked_until'] ?? 0);
if ($blockedUntil > time()) {
    require __DIR__ . '/../includes/auth_views.php'; render_auth_page('login','Too many attempts. Wait a few minutes and try again.'); exit;
}
$emailInput = $_POST['email'] ?? '';
$passwordInput = $_POST['password'] ?? '';
$email = strtolower(trim(is_string($emailInput) ? $emailInput : ''));
$password = is_string($passwordInput) ? $passwordInput : '';
$emailKey = hash('sha256', $email);
$remoteIp = filter_var($_SERVER['REMOTE_ADDR'] ?? null, FILTER_VALIDATE_IP) ?: '';
$ipKey = $remoteIp !== '' ? hash('sha256', $remoteIp) : null;
$throttleReady = false;
try {
    $pdo = db();
    $throttleReady = true;
    if (random_int(1, 20) === 1) $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 1000');
    $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_key=:email AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
    $q->execute(['email'=>$emailKey]);
    $emailFailures = (int)$q->fetchColumn();
    $ipFailures = 0;
    if ($ipKey !== null) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_key=:ip AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $q->execute(['ip'=>$ipKey]);
        $ipFailures = (int)$q->fetchColumn();
    }
    if ($emailFailures >= 10 || $ipFailures >= 60) {
        require __DIR__ . '/../includes/auth_views.php';
        render_auth_page('login','Too many sign-in attempts. Wait 15 minutes before trying again.'); exit;
    }
} catch (PDOException $e) {
    if ($e->getCode() !== '42S02') throw $e;
    $throttleReady = false;
    error_log('Persistent login throttling is unavailable; import migration 018_login_rate_limits.sql.');
}
$stmt = db()->prepare('SELECT id, password_hash, active, must_change_password FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email'=>$email]);
$candidate = $stmt->fetch();
$dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
$passwordMatches = password_verify($password, $candidate['password_hash'] ?? $dummyHash);
if (!$candidate || !$candidate['active'] || !$passwordMatches) {
    if ($throttleReady) {
        $q = db()->prepare('INSERT INTO login_attempts(email_key,ip_key) VALUES(:email,:ip)');
        $q->execute(['email'=>$emailKey,'ip'=>$ipKey]);
    }
    $attempts = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_attempts'] = $attempts;
    if ($attempts >= 5) { $_SESSION['login_blocked_until'] = time() + 300; $_SESSION['login_attempts'] = 0; }
    require __DIR__ . '/../includes/auth_views.php'; render_auth_page('login','Email or password is incorrect.'); exit;
}
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$candidate['id'];
unset($_SESSION['login_attempts'], $_SESSION['login_blocked_until'], $_SESSION['csrf_token']);
if ($throttleReady) {
    $updateAttempts = db()->prepare('DELETE FROM login_attempts WHERE email_key=:email');
    $updateAttempts->execute(['email'=>$emailKey]);
}
$update = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
$update->execute(['id'=>$candidate['id']]);
audit_log('auth.login');
if ((int)$candidate['active'] && !empty($candidate['must_change_password'])) { header('Location: /?page=account'); exit; }
header('Location: /'); exit;
