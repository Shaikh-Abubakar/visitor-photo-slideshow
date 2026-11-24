<?php
declare(strict_types=1);

/**
 * Web security helpers (Step 3): secure session, CSRF token, output escaping.
 *
 * Load after includes/bootstrap.php:
 *
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
 *
 * This file only defines functions. It starts nothing until a page calls them.
 */

// Direct web requests to this file do nothing: it must be loaded by a page.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/** Name of the session cookie. A project-specific name avoids clashes with other PHP apps on the same host. */
const SESSION_COOKIE_NAME = 'vps_session';

/** Escapes text for safe output inside HTML (element content and quoted attribute values). */
function html_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** True when the current request arrived over HTTPS (directly, or APP_URL says the site is https). */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return str_starts_with((string) app_config('APP_URL'), 'https://');
}

/**
 * Starts the PHP session with safe settings. Safe to call more than once.
 *
 *  - the session id travels only in a cookie, never in the URL;
 *  - strict mode: the server refuses a session id it did not create (against session fixation);
 *  - the cookie is HttpOnly (not readable by scripts), SameSite=Lax, and Secure on HTTPS;
 *  - the cookie is a browser-session cookie (it is dropped when the browser closes).
 *
 * Must be called before any output is sent.
 */
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        throw new RuntimeException('Cannot start the session: output has already been sent.');
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // PHP's automatic session cache headers are switched off; send_security_headers() sets them.
    session_cache_limiter('');
    session_name(SESSION_COOKIE_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        throw new RuntimeException('The PHP session could not be started. Check session.save_path.');
    }
}

/** Security headers for pages that show a form or personal data. Call before any output. */
function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    // The pages use no CSS, no JavaScript and no images, so everything except form posting to this site can be blocked.
    header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    // The page can show a visitor code: it must not be stored by the browser or by a shared cache.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

/** The CSRF token of this session (created on first use). Requires an active session. */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('csrf_token() needs an active session.');
    }
    $token = $_SESSION['csrf_token'] ?? null;
    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }
    return $token;
}

/** Is the submitted value the token of this session? Constant-time comparison. */
function csrf_is_valid(mixed $submitted): bool
{
    $expected = $_SESSION['csrf_token'] ?? null;
    return is_string($submitted)
        && is_string($expected)
        && $expected !== ''
        && hash_equals($expected, $submitted);
}

/** Discards the current token; the next csrf_token() call creates a new one (a token is good for one successful action). */
function csrf_rotate(): void
{
    unset($_SESSION['csrf_token']);
}
