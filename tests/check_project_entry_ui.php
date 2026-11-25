<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$check = static function (string $label, bool $ok) use (&$checks): void {
    $checks[] = [$label, $ok];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
};

$index = (string) file_get_contents($root . '/index.php');
$css = (string) file_get_contents($root . '/assets/css/style.css');
$readme = (string) file_get_contents($root . '/README.md');

$check('Project root has a real visitor landing page', is_file($root . '/index.php'));
$check('Landing page links to registration and returning-visitor identification', str_contains($index, 'href="register.php"') && str_contains($index, 'href="identify.php"'));
$check('Landing page exposes the public slideshow without requiring visitor login', str_contains($index, 'href="slideshow.php"') && str_contains($index, 'PUBLIC DISPLAY'));
$check('Landing page provides administrator login, not a direct dashboard bypass', str_contains($index, 'href="admin/login.php"') && !str_contains($index, 'href="admin/index.php"'));
$check('Landing page loads the local versioned stylesheet', str_contains($index, 'assets/css/style.css?v=<?= $assetVersion ?>') && str_contains($index, "\$assetVersion = '9'"));
$check('Landing page includes viewport metadata and accessible navigation labels', str_contains($index, 'name="viewport"') && str_contains($index, 'aria-label="Choose what you would like to do"'));
$check('Landing page contains no JavaScript', preg_match('/<script\\b|javascript:/i', $index) !== 1);
$check('Project documentation describes CSS on visitor, admin and slideshow pages', str_contains($readme, 'Shared CSS styles the visitor, administrator and public slideshow pages'));
$check('The design system respects reduced-motion preferences', str_contains($css, '@media (prefers-reduced-motion: reduce)'));

$failed = count(array_filter($checks, static fn (array $item): bool => !$item[1]));
echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . (count($checks) - $failed) . '/' . count($checks) . ' project-entry/UI checks.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
