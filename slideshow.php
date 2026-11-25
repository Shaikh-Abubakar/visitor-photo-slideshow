<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php';

// Public display remains JavaScript-free and serves only approved, consented public photos.
send_security_headers(true, true);
header('Content-Type: text/html; charset=utf-8');

$appName = (string) app_config('APP_NAME');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$readInt = static function (mixed $value, int $fallback, int $min, int $max): int {
    if (!is_string($value) || preg_match('/^(0|[1-9][0-9]{0,14})\\z/', $value) !== 1) return $fallback;
    $number = (int) $value;
    return $number >= $min && $number <= $max ? $number : $fallback;
};

$allowedDurations = [5, 8, 10, 15, 20];
$allowedTransitions = ['fade', 'cut'];
$defaultDuration = (int) app_config('SLIDESHOW_DEFAULT_DURATION_SECONDS');
if (!in_array($defaultDuration, $allowedDurations, true)) $defaultDuration = 5;
$duration = $readInt($_GET['seconds'] ?? null, $defaultDuration, 5, 20);
if (!in_array($duration, $allowedDurations, true)) $duration = $defaultDuration;
$transition = $_GET['transition'] ?? 'fade';
if (!is_string($transition) || !in_array($transition, $allowedTransitions, true)) $transition = 'fade';
$afterId = $readInt($_GET['after'] ?? null, 0, 0, 999999999999999);
$beforeId = $readInt($_GET['before'] ?? null, 0, 0, 999999999999999);
$showId = $readInt($_GET['show'] ?? null, 0, 0, 999999999999999);
$isFinished = ($_GET['finished'] ?? '') === '1';
$paused = $isFinished || ($_GET['paused'] ?? '') === '1';
$settingsOpen = !$isFinished && $paused && ($_GET['settings'] ?? '') === '1';
$emptyRefreshSeconds = 30;
$assetVersion = '9';
$slideshowCssVersion = '15';
$stylesheets = '<link rel="stylesheet" href="assets/css/style.css?v=' . $assetVersion . '">'
    . '<link rel="stylesheet" href="slideshow.css?v=' . $slideshowCssVersion . '">';

try {
    $pdo = db_connection();
    if (($paused || $isFinished) && $showId > 0) {
        $photo = slideshow_find_photo_for_serving($pdo, $showId);
    } elseif ($beforeId > 0) {
        $photo = slideshow_find_previous_photo($pdo, $beforeId);
        // At the first slide, keep the current eligible image rather than wrapping to the end.
        if ($photo === null) $photo = slideshow_find_photo_for_serving($pdo, $beforeId);
    } elseif ($afterId > 0) {
        $photo = slideshow_find_next_photo($pdo, $afterId);
        // No next photo means the current one was the final slide. Revalidate before showing completion.
        if ($photo === null) {
            $photo = slideshow_find_photo_for_serving($pdo, $afterId);
            if ($photo !== null) {
                $showId = $afterId;
                $isFinished = true;
                $paused = true;
            }
        }
    } else {
        $photo = slideshow_find_next_photo($pdo, 0);
    }
} catch (Throwable $e) {
    error_log('Slideshow selection failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: ' . $emptyRefreshSeconds);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . $escape($appName) . ' — Display unavailable</title>' . $stylesheets
        . '</head><body class="slideshow-empty slideshow-error">'
        . '<header><a class="slideshow-brand" href="index.php" aria-label="Back to Home"><span class="slideshow-back-arrow" aria-hidden="true">←</span>'
        . '<span class="slideshow-brand-mark" aria-hidden="true">V</span><span class="slideshow-brand-copy"><span class="slideshow-brand-name">' . $escape($appName) . '</span><span class="slideshow-brand-caption">Back to Home</span></span></a></header>'
        . '<main class="slideshow-state-card" role="status"><p class="slideshow-eyebrow">PUBLIC PHOTO DISPLAY</p>'
        . '<h1>We’ll be back in a moment.</h1><p>The display could not retrieve the next approved photograph. It will try again automatically.</p>'
        . '<p class="slideshow-refresh-note">Retrying in ' . $emptyRefreshSeconds . ' seconds</p></main></body></html>';
    exit;
}

if ($photo === null) {
    header('Refresh: ' . $emptyRefreshSeconds);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta http-equiv="refresh" content="' . $emptyRefreshSeconds . '">'
        . '<title>' . $escape($appName) . ' — Shared moments</title>' . $stylesheets
        . '</head><body class="slideshow-empty">'
        . '<header><a class="slideshow-brand" href="index.php" aria-label="Back to Home"><span class="slideshow-back-arrow" aria-hidden="true">←</span>'
        . '<span class="slideshow-brand-mark" aria-hidden="true">V</span><span class="slideshow-brand-copy"><span class="slideshow-brand-name">' . $escape($appName) . '</span><span class="slideshow-brand-caption">Back to Home</span></span></a></header>'
        . '<main class="slideshow-state-card"><p class="slideshow-eyebrow">A GALLERY OF SHARED MOMENTS</p>'
        . '<h1>The first story is yet to be shared.</h1><p>Photographs appear here only after a visitor grants public-display consent and an administrator approves them.</p>'
        . '<p class="slideshow-refresh-note">Checking for approved photographs every ' . $emptyRefreshSeconds . ' seconds</p></main></body></html>';
    exit;
}

$currentId = (int) $photo['id'];
$photoUrl = 'slideshow_photo.php?id=' . $currentId;
$common = ['seconds' => $duration, 'transition' => $transition];
$nextUrl = 'slideshow.php?' . http_build_query(['after' => $currentId] + $common);
$previousUrl = 'slideshow.php?' . http_build_query(['before' => $currentId] + $common);
$pauseUrl = 'slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1'] + $common);
$resumeUrl = $nextUrl;
$settingsUrl = 'slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1', 'settings' => '1'] + $common);
$settingsCloseUrl = $pauseUrl;
$finishUrl = 'slideshow.php?' . http_build_query(['finished' => '1', 'show' => $currentId] + $common);

$countSql = " FROM photos p INNER JOIN visitors v ON v.id = p.visitor_id INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id WHERE p.visibility = 'public' AND p.status = 'active' AND p.approval_status = 'approved' AND c.consent_version = '1.0' AND c.withdrawn_at IS NULL";
$totalStmt = $pdo->query('SELECT COUNT(*)' . $countSql);
$slideTotal = max(1, (int) $totalStmt->fetchColumn());
$positionStmt = $pdo->prepare('SELECT COUNT(*)' . $countSql . ' AND p.id <= :photo_id');
$positionStmt->execute([':photo_id' => $currentId]);
$slidePosition = max(1, (int) $positionStmt->fetchColumn());
$hasPrevious = $slidePosition > 1;
$nextPhoto = (!$paused && !$isFinished) ? slideshow_find_next_photo($pdo, $currentId) : null;
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
    $url = 'slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1', 'settings' => '1', 'seconds' => $duration, 'transition' => $key]);
    $transitionLinks .= '<a class="slideshow-choice' . ($transition === $key ? ' is-active' : '') . '" href="' . $escape($url) . '"' . ($transition === $key ? ' aria-current="true"' : '') . '>' . $escape($label) . '</a>';
}
$durationLinks = '';
foreach ($durationLabels as $seconds => $label) {
    $url = 'slideshow.php?' . http_build_query(['show' => $currentId, 'paused' => '1', 'settings' => '1', 'seconds' => $seconds, 'transition' => $transition]);
    $durationLinks .= '<a class="slideshow-choice' . ($duration === $seconds ? ' is-active' : '') . '" href="' . $escape($url) . '"' . ($duration === $seconds ? ' aria-current="true"' : '') . '>' . $escape($label) . '</a>';
}

// One still image remains on screen for the selected interval; the next request is a new slide.
echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
    . $refreshMeta . '<meta name="theme-color" content="#101511">'
    . '<title>' . $escape($appName) . ' — Shared photo slideshow</title>' . $stylesheets
    . '</head><body class="slideshow-screen"><div class="slideshow-shell">'
    . '<header class="slideshow-topbar"><a class="slideshow-brand" href="index.php" aria-label="' . $escape($appName) . ' home">'
    . '<span class="slideshow-brand-mark" aria-hidden="true">V</span><span class="slideshow-brand-copy"><span class="slideshow-brand-name">' . $escape($appName) . '</span><span class="slideshow-brand-caption">← Back to Home</span></span></a>'
    . '<div class="slideshow-live"><span class="slideshow-live-dot" aria-hidden="true"></span><span>' . ($paused ? 'PAUSED' : 'PHOTO PRESENTATION') . '</span></div></header>'
    . '<main class="slideshow-stage" aria-label="Public photo slideshow"><div class="slideshow-stage-glow" aria-hidden="true"></div>'
    . '<figure class="slideshow-frame transition--' . $escape($transition) . '"><img class="slideshow-photo" src="' . $escape($photoUrl) . '" alt="Photograph shared by a visitor and approved for public display"></figure>'
    . '<div class="slideshow-photo-index"><span class="slideshow-index-dot"></span> PHOTO ' . str_pad((string) $slidePosition, 2, '0', STR_PAD_LEFT)
    . ' / ' . str_pad((string) $slideTotal, 2, '0', STR_PAD_LEFT) . ' <span class="slideshow-index-separator">/</span> COMMUNITY GALLERY</div></main>'
    . '<footer class="slideshow-control-area"><div class="slideshow-toolbar"><div class="slideshow-toolbar-note"><strong>One photograph at a time.</strong><span>Public consent · Admin approved</span></div>'
    . '<nav class="slideshow-controls" aria-label="Slideshow controls">'
    . ($hasPrevious ? '<a class="slideshow-control slideshow-control-secondary slideshow-control-previous" href="' . $escape($previousUrl) . '" aria-label="Show previous photograph">← <span>Previous</span></a>' : '<span class="slideshow-control slideshow-control-secondary is-disabled" aria-disabled="true">← <span>Previous</span></span>')
    . '<a class="slideshow-control slideshow-control-secondary" href="' . $escape($controlUrl) . '" aria-label="' . $escape($controlLabel) . '"><span aria-hidden="true">' . $controlSymbol . '</span><span>' . $escape($paused ? 'Play' : 'Pause') . '</span></a>'
    . '<a class="slideshow-control slideshow-control-primary" href="' . $escape($nextUrl) . '" aria-label="Show next photograph"><span>Next photo</span><span aria-hidden="true">→</span></a>'
    . '<a class="slideshow-control slideshow-control-secondary slideshow-control-settings" href="' . $escape($settingsUrl) . '" aria-label="Open slideshow settings">⚙ <span>Settings</span></a>'
    . '<a class="slideshow-control slideshow-control-exit slideshow-control-stop" href="index.php" title="Stop slideshow and return home">Stop · Home</a>'
    . '</nav></div>'
    . ($settingsOpen ? '<section class="slideshow-settings is-open" id="slideshow-settings" aria-label="Slideshow settings">'
        . '<div class="slideshow-settings-heading"><div><span class="slideshow-setting-label">DISPLAY SETTINGS</span><h2>Photo presentation settings</h2></div><a class="slideshow-control slideshow-settings-close" href="' . $escape($settingsCloseUrl) . '">Close settings</a></div>'
        . '<div class="slideshow-setting-group"><span class="slideshow-setting-label">TRANSITION</span><div class="slideshow-choices">' . $transitionLinks . '</div></div>'
        . '<div class="slideshow-setting-group"><span class="slideshow-setting-label">TIME PER PHOTO</span><div class="slideshow-choices">' . $durationLinks . '</div></div>'
        . '<div class="slideshow-setting-info"><span><strong>Image fit</strong> Full photo · contain · no crop</span><span><strong>Screen mode</strong> Windowed by default · press F11 only when you want fullscreen</span></div>'
        . '<p class="slideshow-settings-note">The slideshow pauses while settings are open. Settings never activate fullscreen.</p>'
        . '<a class="slideshow-control slideshow-control-primary slideshow-settings-resume" href="' . $escape($resumeUrl) . '">Continue presentation <span aria-hidden="true">→</span></a></section>' : '')
    . '<div class="slideshow-bottomline"><span>Shared display · Approved public photographs only</span><span>Presentation stops automatically after the final photo</span></div>'
    . '</footer></div>'
    . ($isFinished ? '<div class="slideshow-finish-overlay" role="dialog" aria-modal="true" aria-labelledby="slideshow-finish-title"><section class="slideshow-finish-card">'
        . '<span class="slideshow-finish-mark" aria-hidden="true">✓</span><p class="slideshow-eyebrow">END OF PRESENTATION</p><h1 id="slideshow-finish-title">You’ve seen every shared moment.</h1>'
        . '<p>The community slideshow has reached its final approved photograph.</p><div class="slideshow-finish-actions"><a class="slideshow-control slideshow-control-primary" href="index.php">Go to Home</a><a class="slideshow-control slideshow-control-secondary" href="slideshow.php">Play again</a></div>'
        . '</section></div>' : '')
    . '</body></html>';
