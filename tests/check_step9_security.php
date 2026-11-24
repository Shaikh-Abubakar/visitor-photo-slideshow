<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$check = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};
$read = static fn (string $path): string => (string) file_get_contents($root . DIRECTORY_SEPARATOR . $path);

$security = $read('includes/security.php');
$bootstrap = $read('includes/bootstrap.php');
$auth = $read('includes/visitor_auth.php');
$slideshow = $read('includes/slideshow.php');
$adminAuth = $read('admin/auth.php');
$privacy = $read('privacy.php');
$schema = $read('database/schema.sql');
$migration = $read('database/step9_consent_withdrawal_migration.sql');
$gallery = $read('gallery.php');
$upload = $read('upload.php');

$check('Session cookie uses HttpOnly and SameSite=Lax', str_contains($security, "'httponly' => true") && str_contains($security, "'samesite' => 'Lax'"));
$check('Session cookie is Secure when HTTPS is detected', str_contains($security, "'secure'   => request_is_https()"));
$check('Visitor login regenerates the session ID', str_contains($auth, 'session_regenerate_id(true)'));
$check('Visitor logout clears session data and expires the cookie', str_contains($auth, '$_SESSION = [];') && str_contains($auth, "'expires' => time() - 42000"));
$check('Uncaught web exceptions are not rendered to browsers', str_contains($bootstrap, "echo 'Something went wrong. Please try again later.';") && !str_contains($bootstrap, 'echo $debug'));
$check('Public slideshow selection excludes withdrawn consent', str_contains($slideshow, 'c.withdrawn_at IS NULL'));
$check('Administrator approval excludes withdrawn consent', str_contains($adminAuth, 'c.withdrawn_at IS NULL'));
$check('Privacy withdrawal requires authenticated visitor and POST CSRF token', str_contains($privacy, "visitor_require_login('identify.php')") && str_contains($privacy, "csrf_is_valid(\$_POST['csrf_token'] ?? null)"));
$check('Consent withdrawal timestamp exists in new schema and migration', str_contains($schema, 'withdrawn_at') && str_contains($migration, 'ADD COLUMN withdrawn_at'));
$check('Visitor-facing gallery no longer prints internal visitor ID', !str_contains($gallery, "html_escape((string) \$visitor['id'])"));
$check('Visitor-facing upload page no longer prints internal visitor ID', !str_contains($upload, "html_escape((string) \$visitor['id'])"));

$failed = count(array_filter($checks, static fn (array $x): bool => !$x[1]));
echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . (count($checks) - $failed) . '/' . count($checks) . ' checks.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
