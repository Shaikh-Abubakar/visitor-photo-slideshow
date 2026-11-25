<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'login' => (string) file_get_contents($root . '/admin/login.php'),
    'dashboard' => (string) file_get_contents($root . '/admin/index.php'),
    'visitors' => (string) file_get_contents($root . '/admin/visitors.php'),
    'photos' => (string) file_get_contents($root . '/admin/photos.php'),
    'auth' => (string) file_get_contents($root . '/admin/auth.php'),
    'photo_endpoint' => (string) file_get_contents($root . '/admin/photo.php'),
    'layout' => (string) file_get_contents($root . '/admin/layout.php'),
    'css' => (string) file_get_contents($root . '/assets/css/style.css'),
];

$checks = [];
$check = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};

$check('Admin login still validates CSRF and verifies stored password hashes', str_contains($files['login'], 'csrf_is_valid(') && str_contains($files['login'], 'password_verify('));
$check('Successful administrator login regenerates the session ID', str_contains($files['login'], 'session_regenerate_id(true)'));
$check('Dashboard requires administrator authentication', str_contains($files['dashboard'], 'admin_require_login()'));
$check('Dashboard statistics come from database COUNT queries', substr_count($files['dashboard'], 'SELECT COUNT(*)') >= 5);
$check('Dashboard recent photos use the existing authenticated admin image endpoint', str_contains($files['dashboard'], 'src="photo.php?id='));
$check('Visitor search remains parameterized and paginated', str_contains($files['visitors'], 'prepare(') && str_contains($files['visitors'], 'LIMIT :limit OFFSET :offset'));
$check('Photo review requires admin login and POST CSRF validation', str_contains($files['photos'], 'admin_require_login()') && str_contains($files['photos'], 'admin_csrf_check()'));
$check('Photo approval still requires valid active public consent', str_contains($files['photos'], 'admin_public_consent($pdo, (int) $photoId)'));
$check('Approval does not create or modify visitor consent records', !str_contains($files['photos'], 'INSERT INTO consents') && !str_contains($files['photos'], 'UPDATE consents'));
$check('Approve and hide/reject remain POST form actions', str_contains($files['photos'], 'method="post"') && str_contains($files['photos'], 'value="approve"') && str_contains($files['photos'], 'value="hide"'));
$check('Private admin photo preview endpoint remains authenticated', str_contains($files['photo_endpoint'], 'admin_require_login()'));
$check('Shared admin layout uses CSRF-protected POST logout', str_contains($files['layout'], 'method="post" action="logout.php"') && str_contains($files['layout'], 'csrf_token()'));
$check('Admin layout links only to existing dashboard, visitor and photo pages', str_contains($files['layout'], "'index.php'") && str_contains($files['layout'], "'visitors.php'") && str_contains($files['layout'], "'photos.php'"));
$check('Responsive admin breakpoints exist for tablet and mobile', str_contains($files['css'], '@media (max-width: 820px)') && str_contains($files['css'], '@media (max-width: 600px)'));
$check('Admin styling does not add JavaScript or external CSS dependencies', !str_contains($files['login'], '<script') && !str_contains($files['layout'], '<script') && !str_contains($files['css'], '@import url('));

$failed = count(array_filter($checks, static fn (array $item): bool => !$item[1]));
echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . (count($checks) - $failed) . '/' . count($checks) . ' checks.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
