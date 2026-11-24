<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }
if (!verify_csrf()) { http_response_code(419); exit('This form has expired. Refresh the page and try again.'); }
if (production_mode()) {
    $setupKey=(string)(getenv('SCHOOL_SETUP_KEY')?:'');
    if ($setupKey==='' || !hash_equals($setupKey,(string)($_POST['setup_key']??''))) { http_response_code(403); exit('The setup key is invalid.'); }
}
$name = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['password_confirm'] ?? '');
$error = null;
if ($name === '' || mb_strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $error = 'Enter a valid name and email address.';
elseif (strlen($password) < 12) $error = 'Choose a password with at least 12 characters.';
elseif (!hash_equals($password, $confirm)) $error = 'The passwords do not match.';
if ($error) { require __DIR__ . '/../includes/auth_views.php'; render_auth_page('setup', $error); exit; }

$pdo = db();
$lock = (int)$pdo->query("SELECT GET_LOCK('mavambo_first_admin', 10)")->fetchColumn();
if ($lock !== 1) { http_response_code(503); exit('Could not lock administrator setup. Please refresh and try again.'); }
try {
    $pdo->beginTransaction();
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
        $pdo->rollBack(); header('Location: /'); exit;
    }
    $stmt = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES (:name,:email,:hash,'Administrator')");
    $stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    unset($_SESSION['csrf_token']);
    audit_log('admin.first_account_created','users',$id,['role'=>'Administrator']);
    header('Location: /'); exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $e->getCode() === '23000' ? 'An account with that email already exists.' : 'Could not create the administrator account. Please check the database and try again.';
    require __DIR__ . '/../includes/auth_views.php'; render_auth_page('setup', $message);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('mavambo_first_admin')");
}
