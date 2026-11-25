<?php
declare(strict_types=1);

/** Static regression checks for upload next actions, returning visitors and owner-only replay. */
$root = dirname(__DIR__);
$files = [
    'upload' => (string) file_get_contents($root . '/upload.php'),
    'identify' => (string) file_get_contents($root . '/identify.php'),
    'gallery' => (string) file_get_contents($root . '/gallery.php'),
    'gallery_helpers' => (string) file_get_contents($root . '/includes/gallery.php'),
    'personal' => (string) file_get_contents($root . '/my_slideshow.php'),
    'public' => (string) file_get_contents($root . '/slideshow.php'),
    'css' => (string) file_get_contents($root . '/slideshow.css'),
    'shared_css' => (string) file_get_contents($root . '/assets/css/style.css'),
];
$passed = 0;
$failed = 0;
function replay_check(string $label, bool $ok): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo '[PASS] ' . $label . PHP_EOL; }
    else { $failed++; echo '[FAIL] ' . $label . PHP_EOL; }
}

replay_check('Successful upload shows Play my photo slideshow action', str_contains($files['upload'], 'Play my photo slideshow'));
replay_check('Successful upload offers Upload more photos action', str_contains($files['upload'], 'Upload more photos'));
replay_check('Upload next step explains it includes photos from previous visits', str_contains($files['upload'], 'photos from previous visits'));
replay_check('Public upload explains administrator approval before shared display', str_contains($files['upload'], 'administrator approves them'));
replay_check('Returning visitor identification has no extra Play previous photos prompt', !str_contains($files['identify'], 'Play my previous photos') && !str_contains($files['identify'], 'identify_render_welcome'));
replay_check('An already identified visitor who opens identify.php is redirected directly to personal slideshow', str_contains($files['identify'], '$current !== null && $status === 200') && str_contains($files['identify'], "visitor_auth_redirect('my_slideshow.php?welcome=1')"));
replay_check('Returning visitor gallery toolbar offers personal slideshow', str_contains($files['gallery'], 'Play my slideshow'));
replay_check('Personal slideshow requires a valid visitor session', str_contains($files['personal'], "visitor_require_login('identify.php')"));
replay_check('Personal slideshow closes the session lock before image refresh requests', str_contains($files['personal'], 'session_write_close();'));
replay_check('Personal slideshow uses owner-specific photo selector', str_contains($files['personal'], 'gallery_find_next_owner_slideshow_photo'));
replay_check('Personal slideshow displays image through owner-protected photo.php', str_contains($files['personal'], "'photo.php?id='"));
replay_check('Personal photo selection is constrained to the identified owner', str_contains($files['gallery_helpers'], 'p.visitor_id = :visitor'));
replay_check('Personal photo selection excludes hidden/non-active photos', str_contains($files['gallery_helpers'], 'p.status = :status'));
replay_check('A fresh personal slideshow starts with the oldest saved photograph', str_contains($files['gallery_helpers'], 'ORDER BY p.id ASC'));
replay_check('Returning visitor code success automatically starts saved photos', str_contains($files['identify'], "visitor_auth_redirect('my_slideshow.php?welcome=1')"));
replay_check('Personal slideshow provides previous-photo navigation', str_contains($files['personal'], 'slideshow-control-previous') && str_contains($files['personal'], '$previousUrl'));
replay_check('Personal slideshow provides a visible Stop and Dashboard action', str_contains($files['personal'], 'Stop · Dashboard'));
replay_check('Personal slideshow displays a slide position and total', str_contains($files['personal'], 'gallery_owner_slideshow_position') && str_contains($files['personal'], 'YOUR PRIVATE COLLECTION')); 
replay_check('Private photos are included only in the authenticated owner gallery selector', str_contains($files['gallery_helpers'], "p.visibility = 'private'"));
replay_check('Personal slideshow never uses public slideshow image endpoint', !str_contains($files['personal'], 'slideshow_photo.php'));
replay_check('Public slideshow retains the public-photo-only selector', str_contains($files['public'], 'slideshow_find_next_photo'));
replay_check('Settings button pauses and opens the settings panel', str_contains($files['public'], "'settings' => '1'") && str_contains($files['public'], 'is-open'));
replay_check('Settings offer only static-photo transitions', str_contains($files['public'], "'fade' => 'Classic fade'") && str_contains($files['public'], "'cut' => 'Instant cut'") && !str_contains($files['public'], 'Ken Burns pan'));
replay_check('Settings include five timing choices including 5 seconds', str_contains($files['public'], '[5 => \'5 sec\', 8 =>'));
replay_check('Settings panel is hidden until explicitly opened', preg_match('/\.slideshow-settings\s*\{\s*display:\s*none;/', $files['css']) === 1);
replay_check('Settings appear as a compact floating panel without taking over the screen', str_contains($files['css'], 'position: fixed;') && str_contains($files['css'], 'max-height: min(70vh, 560px)')); 
replay_check('Settings do not trigger browser fullscreen automatically', !preg_match('/requestFullscreen|webkitRequestFullscreen|mozRequestFullScreen/i', $files['public'] . $files['personal']));
replay_check('Settings clearly explain full image fit and manual F11 fullscreen', str_contains($files['public'], 'contain · no crop') && str_contains($files['public'], 'press F11'));
replay_check('Personal final slide shows a completion dialog with dashboard and home actions', str_contains($files['personal'], "'finished' => '1'") && str_contains($files['personal'], 'Go to Dashboard') && str_contains($files['personal'], 'Go to Home'));
replay_check('Personal slideshow targets completion rather than wrapping after the final photo', str_contains($files['personal'], '$refreshTarget = $nextPhoto !== null ? $nextUrl : $finishUrl;'));
replay_check('Personal photo selector does not wrap from the last photo to the first', str_contains($files['gallery_helpers'], 'later calls never wrap') && str_contains($files['gallery_helpers'], 'if ($afterId > 0)'));
replay_check('Successful-upload actions have responsive styling', str_contains($files['shared_css'], '.upload-next-steps') && str_contains($files['shared_css'], '@media (max-width: 720px)'));

$php = glob($root . '/*.php');
$syntax = true;
foreach ($php ?: [] as $file) {
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
    if ($status !== 0) { $syntax = false; break; }
}
replay_check('Top-level PHP pages pass syntax checks', $syntax);

echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . " - {$passed}/" . ($passed + $failed) . " upload/returning visitor/personal slideshow checks." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
