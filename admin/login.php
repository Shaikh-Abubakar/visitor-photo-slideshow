<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

session_start_secure();
send_security_headers(false, true);
header('Content-Type: text/html; charset=utf-8');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'The form expired. Please try again.';
    } else {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($username !== '' && $password !== '' && strlen($username) <= 50) {
            try {
                $query = db_connection()->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
                $query->execute([$username]);
                $admin = $query->fetch();

                if (is_array($admin) && password_verify($password, (string) $admin['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    csrf_rotate();
                    header('Location: index.php');
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('Administrator login failed: ' . get_class($exception));
            }
        }
        $error = 'Invalid username or password.';
    }
}

$appName = admin_e((string) app_config('APP_NAME'));
echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<meta name="theme-color" content="#f2f0e9">';
echo '<link rel="stylesheet" href="../assets/css/style.css?v=9">';
echo '<title>Administrator Login | ' . $appName . '</title></head>';
echo '<body class="app-body admin-login-body"><main class="admin-login-layout">';
echo '<section class="admin-login-intro"><a class="admin-login-brand" href="../register.php"><span class="admin-brand-mark" aria-hidden="true">V</span><span>' . $appName . '</span></a>';
echo '<p class="admin-login-kicker">SECURE ADMINISTRATOR ACCESS</p>';
echo '<h1>Manage your visitor photo experience.</h1>';
echo '<p class="admin-login-description">Sign in to review visitor photos, manage submissions, and keep the public slideshow safe and up to date.</p>';
echo '<div class="admin-login-trust"><span aria-hidden="true">✓</span><span>Protected administrator area</span></div></section>';
echo '<section class="admin-login-card" aria-labelledby="admin-login-title"><div class="admin-login-card-mark" aria-hidden="true">↗</div>';
echo '<p class="admin-eyebrow">WELCOME BACK</p><h2 id="admin-login-title">Administrator Login</h2>';
echo '<p class="admin-login-card-copy">Enter your administrator credentials to continue.</p>';
if ($error !== '') {
    echo '<p class="admin-alert admin-alert-error" role="alert">' . admin_e($error) . '</p>';
}
echo '<form method="post" action="login.php" class="admin-login-form">';
echo '<input type="hidden" name="csrf_token" value="' . admin_e(csrf_token()) . '">';
echo '<label for="admin-username">Username</label>';
echo '<input id="admin-username" name="username" type="text" maxlength="50" required autocomplete="username" placeholder="Enter your username">';
echo '<label for="admin-password">Password</label>';
echo '<input id="admin-password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">';
echo '<button type="submit" class="admin-login-submit">Sign in to dashboard <span aria-hidden="true">→</span></button>';
echo '</form><p class="admin-login-footnote">Administrator access only. Visitor accounts cannot sign in here.</p>';
echo '</section></main></body></html>';
