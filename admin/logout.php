<?php
declare(strict_types=1); require_once __DIR__.DIRECTORY_SEPARATOR.'auth.php'; admin_require_login(); send_security_headers(); if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');} admin_csrf_check(); unset($_SESSION['admin_id']); session_regenerate_id(true); csrf_rotate(); header('Location: login.php'); exit;
