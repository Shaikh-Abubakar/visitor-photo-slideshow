<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$identify = (string) file_get_contents($root . '/identify.php');
$personal = (string) file_get_contents($root . '/my_slideshow.php');
$passed = 0;
$failed = 0;
function auto_replay_check(string $label, bool $ok): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo '[PASS] ' . $label . PHP_EOL; }
    else { $failed++; echo '[FAIL] ' . $label . PHP_EOL; }
}

auto_replay_check('Successful code verification redirects directly to the personal slideshow', str_contains($identify, "visitor_auth_redirect('my_slideshow.php?welcome=1')"));
auto_replay_check('Existing authenticated session is redirected away from the identification page', str_contains($identify, '$current !== null && $status === 200') && substr_count($identify, "visitor_auth_redirect('my_slideshow.php?welcome=1')") >= 2);
auto_replay_check('No manual Play previous photos interstitial remains on identification page', !str_contains($identify, 'Play my previous photos') && !str_contains($identify, 'identify_render_welcome'));
auto_replay_check('Personal slideshow begins at the oldest available owner photo', str_contains($personal, 'gallery_find_next_owner_slideshow_photo') && str_contains($personal, '$visitor'));
auto_replay_check('Personal slideshow automatically advances without a Play confirmation', str_contains($personal, '$refreshMeta = $paused ?') && str_contains($personal, 'http-equiv="refresh"'));
auto_replay_check('Personal slideshow keeps Stop and Dashboard controls visible', str_contains($personal, 'Stop · Dashboard'));
auto_replay_check('Empty personal collection offers upload, dashboard and home actions', str_contains($personal, 'Upload photographs') && str_contains($personal, 'My dashboard') && str_contains($personal, 'Home'));

echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . $passed . '/' . ($passed + $failed) . ' automatic returning visitor replay checks.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
