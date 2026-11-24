<?php
declare(strict_types=1); require_once __DIR__.DIRECTORY_SEPARATOR.'auth.php'; admin_require_login(); send_security_headers(); if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');} admin_csrf_check(); $_SESSION = []; session_regenerate_id(true); csrf_rotate();
$params = session_get_cookie_params();
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000, 'path' => $params['path'] ?: '/',
        'domain' => $params['domain'], 'secure' => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax',
    ]);
} header('Location: login.php'); exit;
