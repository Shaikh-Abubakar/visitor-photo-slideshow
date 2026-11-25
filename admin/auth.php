<?php
declare(strict_types=1);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once APP_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once APP_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once APP_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';
require_once APP_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php';
function admin_require_login(): void { session_start_secure(); if (!isset($_SESSION['admin_id']) || !is_int($_SESSION['admin_id'])) { header('Location: login.php'); exit; } }
function admin_csrf_check(): void { if (!csrf_is_valid($_POST['csrf_token'] ?? null)) { http_response_code(400); exit('Invalid or expired form token.'); } }
function admin_e(mixed $v): string { return html_escape((string)$v); }
function admin_public_consent(PDO $pdo, int $id): bool { $q=$pdo->prepare("SELECT 1 FROM photos p INNER JOIN consents c ON c.id=p.consent_id AND c.visitor_id=p.visitor_id WHERE p.id=? AND p.visibility='public' AND c.consent_version=? AND c.withdrawn_at IS NULL LIMIT 1"); $q->execute([$id,SLIDESHOW_PUBLIC_CONSENT_VERSION]); return (bool)$q->fetchColumn(); }
