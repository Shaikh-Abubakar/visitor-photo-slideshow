<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static fn(string $name): string => (string) file_get_contents($root . '/' . $name);
$identify = $read('identify.php');
$gallery = $read('includes/gallery.php');
$owner = $read('my_slideshow.php');
$public = $read('slideshow.php');
$publicHelpers = $read('includes/slideshow.php');
$css = $read('slideshow.css');
$passed = 0; $failed = 0;
function step13_check(string $name, bool $ok): void { global $passed, $failed; if ($ok) { $passed++; echo "[PASS] $name\n"; } else { $failed++; echo "[FAIL] $name\n"; } }
step13_check('Successful identification redirects directly to personal slideshow', str_contains($identify, "visitor_auth_redirect('my_slideshow.php?welcome=1')"));
step13_check('Returning visitor gets a welcome-back notice', str_contains($owner, 'Welcome back, ') && str_contains($owner, 'Your previous photos are playing'));
step13_check('Owner slideshow begins at oldest image', str_contains($gallery, 'ORDER BY p.id ASC'));
step13_check('Owner Next selects a later image', str_contains($gallery, 'p.id > :after_id ORDER BY p.id ASC'));
step13_check('Owner Previous selects an earlier image', str_contains($gallery, 'p.id < :before_id ORDER BY p.id DESC'));
step13_check('Owner slideshow remains scoped to the authenticated visitor', str_contains($gallery, 'p.visitor_id = :visitor'));
step13_check('Owner image is served through the protected photo endpoint', str_contains($owner, "'photo.php?id='"));
step13_check('Owner display has a Previous control', str_contains($owner, 'slideshow-control-previous'));
step13_check('Owner display has a visible Stop and Dashboard control', str_contains($owner, 'Stop · Dashboard'));
step13_check('Owner display provides a Home route when no photos exist', str_contains($owner, 'href="index.php"'));
step13_check('Owner slide counter shows position and total', str_contains($owner, 'gallery_owner_slideshow_position') && str_contains($owner, 'YOUR PRIVATE COLLECTION'));
step13_check('Public display has a Previous control', str_contains($public, '$previousUrl') && str_contains($public, 'slideshow-control-previous'));
step13_check('Public previous query retains consent and approval gates', str_contains($publicHelpers, "p.visibility = 'public'") && str_contains($publicHelpers, "c.withdrawn_at IS NULL"));
step13_check('Public display has an explicit Stop and Home route', str_contains($public, 'Stop · Home'));
step13_check('Both routes default to a soft fade, not a zoom treatment', str_contains($owner, "?? 'fade'") && str_contains($public, "?? 'fade'"));
step13_check('Welcome banner remains static and does not introduce video-like movement', str_contains($css, '.slideshow-welcome-toast') && str_contains($css, 'animation: none;'));
step13_check('Back, previous and stop actions have dedicated styles', str_contains($css, '.slideshow-control-previous') && str_contains($css, '.slideshow-control-exit'));
step13_check('Personal presentation has a final-slide completion overlay', str_contains($owner, 'slideshow-finish-overlay') && str_contains($owner, 'Go to Dashboard') && str_contains($owner, 'Go to Home'));
step13_check('Public presentation has a final-slide completion overlay', str_contains($public, 'slideshow-finish-overlay') && str_contains($public, 'Go to Home'));
step13_check('Both photo selectors stop at the end instead of looping to the first image', str_contains(strtolower($gallery), 'later calls never wrap') && str_contains(strtolower($publicHelpers), 'later calls never wrap'));
step13_check('Project still blocks scripts through Content Security Policy', str_contains($read('includes/security.php'), "default-src 'none';"));
$syntax = true;
foreach (glob($root . '/*.php') ?: [] as $file) { exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status); if ($status !== 0) { $syntax = false; break; } }
step13_check('All top-level PHP pages pass syntax lint', $syntax);
echo 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . " - $passed/" . ($passed + $failed) . " Step 13 checks.\n";
exit($failed === 0 ? 0 : 1);
