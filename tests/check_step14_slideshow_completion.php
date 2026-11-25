<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$public = (string) file_get_contents($root . '/slideshow.php');
$personal = (string) file_get_contents($root . '/my_slideshow.php');
$publicHelper = (string) file_get_contents($root . '/includes/slideshow.php');
$ownerHelper = (string) file_get_contents($root . '/includes/gallery.php');
$css = (string) file_get_contents($root . '/slideshow.css');
$checks = [
    'Public final slide is revalidated before completion is shown' => str_contains($public, 'slideshow_find_photo_for_serving($pdo, $afterId)'),
    'Public autoplay goes to the finish panel when no later slide exists' => str_contains($public, '$refreshTarget = $nextPhoto !== null ? $nextUrl : $finishUrl;'),
    'Personal final slide is revalidated against the signed-in owner' => str_contains($personal, 'gallery_owner_photo_is_renderable($pdo, $afterId, (int) $visitor[\'id\'])'),
    'Personal autoplay goes to the finish panel when no later slide exists' => str_contains($personal, '$refreshTarget = $nextPhoto !== null ? $nextUrl : $finishUrl;'),
    'Public finish panel offers Home and replay' => str_contains($public, 'Go to Home') && str_contains($public, 'Play again'),
    'Personal finish panel offers Dashboard and Home' => str_contains($personal, 'Go to Dashboard') && str_contains($personal, 'Go to Home'),
    'Public next selector has no wrap-around branch' => str_contains($publicHelper, 'Later calls never wrap') && str_contains($publicHelper, '$afterId > 0'),
    'Owner next selector has no first-photo fallback after an id is supplied' => str_contains($ownerHelper, 'later calls never wrap') && str_contains($ownerHelper, '$afterId > 0'),
    'Motion is limited to a short opacity fade or instant cut' => str_contains($css, '.transition--fade .slideshow-photo') && str_contains($css, '.transition--cut .slideshow-photo') && !str_contains($css, 'animation: show-pan') && !str_contains($css, 'animation: show-zoom'),
    'Video-style animated timeline is hidden and not rendered on slideshow pages' => str_contains($css, '.slideshow-progress-track { display: none !important; }') && !str_contains($public, 'slideshow-progress-track') && !str_contains($personal, 'slideshow-progress-track'),
    'Completion panel uses an accessible modal dialog role' => str_contains($public, 'role="dialog" aria-modal="true"') && str_contains($personal, 'role="dialog" aria-modal="true"'),
    'No JavaScript has been added' => !preg_match('/<script\\b|javascript:/i', $public . $personal),
];
$passed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if ($ok) $passed++;
}
$phpOk = true;
foreach (['slideshow.php','my_slideshow.php','includes/slideshow.php','includes/gallery.php'] as $file) {
    exec('php -l ' . escapeshellarg($root . '/' . $file) . ' 2>&1', $output, $status);
    if ($status !== 0) $phpOk = false;
}
echo ($phpOk ? '[PASS] ' : '[FAIL] ') . 'Changed PHP files pass syntax lint' . PHP_EOL;
if ($phpOk) $passed++;
echo 'RESULT: ' . ($passed === count($checks) + 1 ? 'PASS' : 'FAIL') . ' - ' . $passed . '/' . (count($checks) + 1) . ' Step 14 checks.' . PHP_EOL;
exit($passed === count($checks) + 1 ? 0 : 1);
