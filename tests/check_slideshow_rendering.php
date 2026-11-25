<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$css = file_get_contents($root . DIRECTORY_SEPARATOR . 'slideshow.css');
$page = file_get_contents($root . DIRECTORY_SEPARATOR . 'slideshow.php');
$helpers = file_get_contents($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php');
$commonCss = file_get_contents($root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'style.css');
$envExample = file_get_contents($root . DIRECTORY_SEPARATOR . '.env.example');

$checks = [
    'slideshow stage uses the full available width' => str_contains($css, '.slideshow-stage {') && str_contains($css, 'width: 100%;'),
    'slideshow body uses a full-viewport three-row layout' => str_contains($css, 'grid-template-rows: auto minmax(0, 1fr) auto;') && str_contains($css, 'height: 100dvh;'),
    'slideshow image is anchored to the full stage box' => str_contains($css, 'position: absolute;') && str_contains($css, 'inset: 0;') && str_contains($css, 'width: 100%;') && str_contains($css, 'height: 100%;'),
    'slideshow image uses contain instead of crop' => str_contains($css, 'object-fit: contain;'),
    'slideshow stage uses border-box sizing to prevent viewport overflow' => str_contains($css, '.slideshow-stage {') && str_contains($css, 'box-sizing: border-box;'),
    'page loads the shared design system stylesheet' => str_contains($page, "assets/css/style.css?v=' . \$assetVersion"),
    'page loads the dedicated full-screen slideshow stylesheet' => str_contains($page, "slideshow.css?v=' . \$slideshowCssVersion"),
    'administrator layout exposes an Open public slideshow link' => str_contains(file_get_contents($root . '/admin/layout.php'), 'href="../slideshow.php"') && str_contains(file_get_contents($root . '/admin/layout.php'), 'Open public slideshow'),
    'shared stylesheet contains reusable slideshow state components' => str_contains($commonCss, '.slideshow-state-card') && str_contains($commonCss, '.slideshow-brand'),
    'page uses the dedicated slideshow image class' => str_contains($page, 'class="slideshow-photo"'),
    'page enables same-origin CSS and images only for the slideshow' => str_contains($page, 'send_security_headers(true, true);'),
    'photo selection requires a matching registered visitor' => substr_count($helpers, 'INNER JOIN visitors v ON v.id = p.visitor_id') >= 2,
    'photo selection requires the matching visitor consent' => substr_count($helpers, 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id') >= 2,
    'photo selection requires active status and administrator approval' => str_contains($helpers, 'p.status = :status AND p.approval_status = :approval'),
    'photo selection requires the correct public-display consent version' => str_contains($helpers, 'c.consent_version = :version'),
    'photo selection excludes withdrawn consent' => str_contains($helpers, 'c.withdrawn_at IS NULL'),
    'public images use the dedicated filtered delivery endpoint' => str_contains($page, 'slideshow_photo.php?id='),
    'Classic still-photo fade transition is defined' => str_contains($css, '@keyframes show-fade'),
    'reduced-motion preference disables the slideshow animation' => str_contains($css, '@media (prefers-reduced-motion: reduce)'),
    'JavaScript is not added to the slideshow page' => !preg_match('/<script\b|javascript:/i', $page),
    'Slideshow has no video-style progress timeline or continuous zoom/pan motion' => !str_contains($page, 'slideshow-progress-track') && !str_contains($css, 'animation: show-zoom') && !str_contains($css, 'animation: show-pan'),
    'Public final slide redirects to a completion state instead of cycling to the first photo' => str_contains($page, "'finished' => '1'") && str_contains($page, '$refreshTarget = $nextPhoto !== null ? $nextUrl : $finishUrl;'),
    'pause and resume controls are present' => str_contains($page, 'Pause slideshow') && str_contains($page, 'Resume slideshow'),
    'manual next-photo control is present' => str_contains($page, 'Show next photograph'),
    'Only classic slideshow transitions are offered (fade and instant cut)' => str_contains($page, "'fade', 'cut'") && str_contains($css, 'transition--fade') && str_contains($css, 'transition--cut') && !str_contains($css, 'animation: show-zoom') && !str_contains($css, 'animation: show-pan'),
    'five slide intervals are supported, including 5 seconds' => str_contains($page, "5 => '5 sec'") && str_contains($page, "20 => '20 sec'"),
    '5 seconds is the default and invalid durations fall back to it' => str_contains($page, '$allowedDurations = [5, 8, 10, 15, 20]') && str_contains($page, '$defaultDuration = 5;') && str_contains($page, "\$readInt(\$_GET['seconds'] ?? null, \$defaultDuration, 5, 20)"),
    'public slideshow query explicitly excludes Private photos' => str_contains($helpers, "p.visibility = 'public'"),
    'example environment also documents a 5-second default' => str_contains($envExample, 'SLIDESHOW_DEFAULT_DURATION_SECONDS=5'),
    'inline styles are not used under the restrictive stylesheet CSP' => !preg_match('/\sstyle\s*=/i', $page),
    'public screen does not expose the internal photo database id' => !str_contains($page, 'str_pad((string) $currentId'),
];

$passed = 0;
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
        continue;
    }
    echo 'PASS: ' . $label . PHP_EOL;
    $passed++;
}

if ($passed !== count($checks)) {
    fwrite(STDERR, 'RESULT: FAIL - ' . $passed . '/' . count($checks) . ' checks passed.' . PHP_EOL);
    exit(1);
}

echo 'RESULT: PASS - ' . $passed . ' slideshow rendering/eligibility checks.' . PHP_EOL;
