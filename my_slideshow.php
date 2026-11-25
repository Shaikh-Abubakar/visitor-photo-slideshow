<?php
declare(strict_types=1);

/** Owner-only photo presentation. Private images are served only through photo.php. */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';

send_security_headers(true, true);
header('Content-Type: text/html; charset=utf-8');
session_start_secure();
$visitor = visitor_require_login('identify.php');
session_write_close();

$appName = (string) app_config('APP_NAME');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$allowedDurations = [5, 8, 10, 15, 20];
$allowedTransitions = ['fade', 'cut'];
$readInt = static function (mixed $value, int $fallback, int $min, int $max): int {
    if (!is_string($value) || preg_match('/^(0|[1-9][0-9]{0,14})\\z/', $value) !== 1) return $fallback;
    $number = (int) $value;
    return $number >= $min && $number <= $max ? $number : $fallback;
};
$defaultDuration = (int) app_config('SLIDESHOW_DEFAULT_DURATION_SECONDS');
if (!in_array($defaultDuration, $allowedDurations, true)) $defaultDuration = 5;
$duration = $readInt($_GET['seconds'] ?? null, $defaultDuration, 5, 20);
if (!in_array($duration, $allowedDurations, true)) $duration = $defaultDuration;
$transition = $_GET['transition'] ?? 'fade';
if (!is_string($transition) || !in_array($transition, $allowedTransitions, true)) $transition = 'fade';
$afterId = gallery_parse_positive_int($_GET['after'] ?? null) ?? 0;
$beforeId = gallery_parse_positive_int($_GET['before'] ?? null) ?? 0;
$showId = gallery_parse_positive_int($_GET['show'] ?? null) ?? 0;
$isFinished = ($_GET['finished'] ?? '') === '1';
$paused = $isFinished || ($_GET['paused'] ?? '') === '1';
$settingsOpen = !$isFinished && $paused && ($_GET['settings'] ?? '') === '1';
$welcomeBack = ($_GET['welcome'] ?? '') === '1';
$slideshowCssVersion = '15';
$stylesheets = '<link rel="stylesheet" href="assets/css/style.css?v=9"><link rel="stylesheet" href="slideshow.css?v=' . $slideshowCssVersion . '">';

try {
    $pdo = db_connection();
    if (($paused || $isFinished) && $showId > 0 && gallery_owner_photo_is_renderable($pdo, $showId, (int) $visitor['id'])) {
        $photo = ['id' => $showId];
    } elseif ($beforeId > 0) {
        $photo = gallery_find_previous_owner_slideshow_photo($pdo, (int) $visitor['id'], $beforeId);
        // At the first slide, keep the same photo; never wrap to the end of the collection.
        if ($photo === null && gallery_owner_photo_is_renderable($pdo, $beforeId, (int) $visitor['id'])) $photo = ['id' => $beforeId];
    } elseif ($afterId > 0) {
        $photo = gallery_find_next_owner_slideshow_photo($pdo, (int) $visitor['id'], $afterId);
        // Reaching the end returns the last photo in a paused completion state.
        if ($photo === null && gallery_owner_photo_is_renderable($pdo, $afterId, (int) $visitor['id'])) {
            $photo = ['id' => $afterId];
            $showId = $afterId;
            $isFinished = true;
            $paused = true;
        }
    } else {
        $photo = gallery_find_next_owner_slideshow_photo($pdo, (int) $visitor['id'], 0);
    }
} catch (Throwable $e) {
    error_log('Owner slideshow selection failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $escape($appName) . ' — Personal slideshow</title>' . $stylesheets
        . '</head><body class="slideshow-empty"><main class="slideshow-state-card" role="status"><p class="slideshow-eyebrow">YOUR PERSONAL COLLECTION</p>'
        . '<h1>Your slideshow is temporarily unavailable.</h1><p>Please return to your dashboard and try again. Your photographs have not been changed.</p>'
        . '<a class="slideshow-control slideshow-control-primary" href="gallery.php">Back to my dashboard</a></main></body></html>';
    exit;
}

if ($photo === null) {
    $heading = $welcomeBack ? 'Welcome back, ' . $escape((string) $visitor['name']) . '!' : 'Your slideshow is waiting.';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="theme-color" content="#101511"><title>' . $escape($appName) . ' — My slideshow</title>' . $stylesheets
        . '</head><body class="slideshow-empty"><main class="slideshow-state-card"><p class="slideshow-eyebrow">YOUR PERSONAL COLLECTION</p>'
        . '<h1>' . $heading . '</h1><p>You do not have active photographs to play yet. Upload photos to create your personal slideshow. Your private display can include photographs from every visit.</p>'
        . '<div class="visitor-actions"><a class="button button-primary" href="upload.php">Upload photographs</a>'
        . '<a class="button button-secondary" href="gallery.php">My dashboard</a><a class="button button-secondary" href="index.php">Home</a></div></main></body></html>';
    exit;
}

$currentId = (int) $photo['id'];
$photoUrl = 'photo.php?id=' . $currentId;
$position = gallery_owner_slideshow_position($pdo, (int) $visitor['id'], $currentId);
$hasPrevious = $position['position'] > 1;
$common = ['seconds' => $duration, 'transition' => $transition];
$nextUrl = 'my_slideshow.php?' . http_build_query(['after' => $currentId] + $common);
$previousUrl = 'my_slideshow.php?' . http_build_query(['before' => $currentId] + $common);
$pauseUrl = 'my_slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1'] + $common);
$resumeUrl = $nextUrl;
$settingsUrl = 'my_slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1', 'settings' => '1'] + $common);
$settingsCloseUrl = $pauseUrl;
$finishUrl = 'my_slideshow.php?' . http_build_query(['finished' => '1', 'show' => $currentId] + $common);
$nextPhoto = (!$paused && !$isFinished) ? gallery_find_next_owner_slideshow_photo($pdo, (int) $visitor['id'], $currentId) : null;
$refreshTarget = $nextPhoto !== null ? $nextUrl : $finishUrl;
$refreshMeta = $paused ? '' : '<meta http-equiv="refresh" content="' . $duration . ';url=' . $escape($refreshTarget) . '">';
$timerClass = $paused ? ' is-paused' : '';
$controlUrl = $paused ? $resumeUrl : $pauseUrl;
$controlLabel = $paused ? 'Resume slideshow' : 'Pause slideshow';
$controlSymbol = $paused ? '▶' : 'Ⅱ';
$transitionLabels = ['fade' => 'Classic fade', 'cut' => 'Instant cut'];
$durationLabels = [5 => '5 sec', 8 => '8 sec', 10 => '10 sec', 15 => '15 sec', 20 => '20 sec'];
$transitionLinks = '';
foreach ($transitionLabels as $key => $label) {
    $url = 'my_slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1', 'settings' => '1', 'seconds' => $duration, 'transition' => $key]);
    $transitionLinks .= '<a class="slideshow-choice' . ($transition === $key ? ' is-active' : '') . '" href="' . $escape($url) . '"' . ($transition === $key ? ' aria-current="true"' : '') . '>' . $escape($label) . '</a>';
}
$durationLinks = '';
foreach ($durationLabels as $seconds => $label) {
    $url = 'my_slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1', 'settings' => '1', 'seconds' => $seconds, 'transition' => $transition]);
    $durationLinks .= '<a class="slideshow-choice' . ($duration === $seconds ? ' is-active' : '') . '" href="' . $escape($url) . '"' . ($duration === $seconds ? ' aria-current="true"' : '') . '>' . $escape($label) . '</a>';
}

echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
    . $refreshMeta . '<meta name="theme-color" content="#101511"><title>' . $escape($appName) . ' — My photo slideshow</title>' . $stylesheets
    . '</head><body class="slideshow-screen"><div class="slideshow-shell">'
    . '<header class="slideshow-topbar"><a class="slideshow-brand" href="gallery.php" aria-label="Back to my dashboard"><span class="slideshow-back-arrow" aria-hidden="true">←</span><span class="slideshow-back-label">Back to dashboard</span>'
    . '<span class="slideshow-brand-mark" aria-hidden="true">V</span><span class="slideshow-brand-copy"><span class="slideshow-brand-name">' . $escape($appName) . '</span><span class="slideshow-brand-caption">My dashboard</span></span></a>'
    . '<div class="slideshow-header-actions"><a class="slideshow-control slideshow-control-exit" href="gallery.php">Stop · Dashboard</a>'
    . '<div class="slideshow-live"><span class="slideshow-live-dot" aria-hidden="true"></span><span>' . ($paused ? 'PAUSED' : 'PHOTO PRESENTATION') . '</span></div></div></header>'
    . ($welcomeBack && !$isFinished ? '<aside class="slideshow-welcome-toast" role="status"><strong>Welcome back, ' . $escape((string) $visitor['name']) . '.</strong> Your previous photos are playing from your earliest visit. <a href="gallery.php">Stop and open your dashboard</a></aside>' : '')
    . '<main class="slideshow-stage" aria-label="Your private photo slideshow"><div class="slideshow-stage-glow" aria-hidden="true"></div>'
    . '<figure class="slideshow-frame transition--' . $escape($transition) . '"><img class="slideshow-photo" src="' . $escape($photoUrl) . '" alt="Your photograph from your personal gallery"></figure>'
    . '<div class="slideshow-photo-index"><span class="slideshow-index-dot"></span> PHOTO ' . str_pad((string) $position['position'], 2, '0', STR_PAD_LEFT)
    . ' / ' . str_pad((string) $position['total'], 2, '0', STR_PAD_LEFT) . ' <span class="slideshow-index-separator">/</span> YOUR PRIVATE COLLECTION</div></main>'
    . '<footer class="slideshow-control-area"><div class="slideshow-toolbar"><div class="slideshow-toolbar-note"><strong>One photograph at a time.</strong><span>Older photos first · All previous visits · Owner-only</span></div>'
    . '<nav class="slideshow-controls" aria-label="Personal slideshow controls">'
    . ($hasPrevious ? '<a class="slideshow-control slideshow-control-secondary slideshow-control-previous" href="' . $escape($previousUrl) . '" aria-label="Show previous photograph">← <span>Previous</span></a>' : '<span class="slideshow-control slideshow-control-secondary is-disabled" aria-disabled="true">← <span>Previous</span></span>')
    . '<a class="slideshow-control slideshow-control-secondary" href="' . $escape($controlUrl) . '" aria-label="' . $escape($controlLabel) . '"><span aria-hidden="true">' . $controlSymbol . '</span><span>' . ($paused ? 'Play' : 'Pause') . '</span></a>'
    . '<a class="slideshow-control slideshow-control-primary" href="' . $escape($nextUrl) . '" aria-label="Show next photograph"><span>Next</span><span aria-hidden="true">→</span></a>'
    . '<a class="slideshow-control slideshow-control-secondary slideshow-control-settings" href="' . $escape($settingsUrl) . '" aria-label="Open slideshow settings">⚙ <span>Settings</span></a>'
    . '<a class="slideshow-control slideshow-control-exit slideshow-control-stop" href="gallery.php" title="Stop slideshow and return to dashboard">Stop · Dashboard</a>'
    . '</nav></div>'
    . ($settingsOpen ? '<section class="slideshow-settings is-open" id="slideshow-settings" aria-label="Personal slideshow settings">'
        . '<div class="slideshow-settings-heading"><div><span class="slideshow-setting-label">DISPLAY SETTINGS</span><h2>Personal photo presentation settings</h2></div><a class="slideshow-control slideshow-settings-close" href="' . $escape($settingsCloseUrl) . '">Close settings</a></div>'
        . '<div class="slideshow-setting-group"><span class="slideshow-setting-label">TRANSITION</span><div class="slideshow-choices">' . $transitionLinks . '</div></div>'
        . '<div class="slideshow-setting-group"><span class="slideshow-setting-label">TIME PER PHOTO</span><div class="slideshow-choices">' . $durationLinks . '</div></div>'
        . '<div class="slideshow-setting-info"><span><strong>Image fit</strong> Full photo · contain · no crop</span><span><strong>Privacy</strong> Owner-only · private photos included</span><span><strong>Screen mode</strong> Windowed by default · press F11 only when you want fullscreen</span></div>'
        . '<p class="slideshow-settings-note">Settings pause the presentation. They never activate fullscreen.</p>'
        . '<a class="slideshow-control slideshow-control-primary slideshow-settings-resume" href="' . $escape($resumeUrl) . '">Continue presentation <span aria-hidden="true">→</span></a></section>' : '')
    . '<div class="slideshow-bottomline"><span>Private collection · Active photos from all visits</span><span>Presentation stops after the final photograph</span></div>'
    . '</footer></div>'
    . ($isFinished ? '<div class="slideshow-finish-overlay" role="dialog" aria-modal="true" aria-labelledby="slideshow-finish-title"><section class="slideshow-finish-card">'
        . '<span class="slideshow-finish-mark" aria-hidden="true">✓</span><p class="slideshow-eyebrow">END OF YOUR PRESENTATION</p><h1 id="slideshow-finish-title">You’ve reached the end of your memories.</h1>'
        . '<p>You’ve viewed all your active photographs, including photos from previous visits.</p><div class="slideshow-finish-actions"><a class="slideshow-control slideshow-control-primary" href="gallery.php">Go to Dashboard</a>'
        . '<a class="slideshow-control slideshow-control-secondary" href="index.php">Go to Home</a><a class="slideshow-control slideshow-control-secondary" href="my_slideshow.php">Play again</a></div>'
        . '</section></div>' : '')
    . '</body></html>';
