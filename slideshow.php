<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php';

// Only the slideshow is allowed to load its own stylesheet and same-origin photo endpoint.
send_security_headers(true, true);
header('Content-Type: text/html; charset=utf-8');

$duration = (int) app_config('SLIDESHOW_DEFAULT_DURATION_SECONDS');
$rawAfter = $_GET['after'] ?? null;
$afterId = is_string($rawAfter) && preg_match('/^(0|[1-9][0-9]{0,14})\z/', $rawAfter) === 1
    ? (int) $rawAfter
    : 0;

try {
    $photo = slideshow_find_next_photo(db_connection(), $afterId);
} catch (Throwable $e) {
    error_log('Slideshow selection failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Slideshow unavailable</title><link rel="stylesheet" href="slideshow.css?v=3"></head>'
        . '<body class="slideshow-empty"><main class="slideshow-message"><h1>Slideshow temporarily unavailable</h1>'
        . '<p>Please check the application database connection.</p></main></body></html>';
    exit;
}

if ($photo === null) {
    header('Refresh: 30');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta http-equiv="refresh" content="30"><title>Photo slideshow</title>'
        . '<link rel="stylesheet" href="slideshow.css?v=3"></head>'
        . '<body class="slideshow-empty"><main class="slideshow-message">'
        . '<h1>No approved photographs are available.</h1>'
        . '<p>The public screen does not need to register. Photos appear here only after a registered visitor uploads them with public-display consent.</p>'
        . '<p>This screen will check again in 30 seconds.</p>'
        . '</main></body></html>';
    exit;
}

$nextUrl = 'slideshow.php?after=' . $photo['id'];
$photoUrl = 'slideshow_photo.php?id=' . $photo['id'];
$escapedNextUrl = htmlspecialchars($nextUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$escapedPhotoUrl = htmlspecialchars($photoUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
    . '<meta http-equiv="refresh" content="' . $duration . ';url=' . $escapedNextUrl . '">'
    . '<title>Photo slideshow</title><link rel="stylesheet" href="slideshow.css?v=3"></head>'
    . '<body class="slideshow-screen"><main class="slideshow-stage">'
    . '<img class="slideshow-photo" src="' . $escapedPhotoUrl . '" alt="Approved visitor photograph">'
    . '</main></body></html>';
