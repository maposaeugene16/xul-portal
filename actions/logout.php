<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) { http_response_code(419); exit('Invalid sign-out request. Refresh the page and try again.'); }
try { audit_log('auth.logout'); } catch (Throwable $e) { error_log('Logout audit failed: '.$e->getMessage()); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$params['path'],'domain'=>$params['domain'],'secure'=>$params['secure'],'httponly'=>$params['httponly'],'samesite'=>'Strict']);
}
session_destroy();
header('Location: /'); exit;
