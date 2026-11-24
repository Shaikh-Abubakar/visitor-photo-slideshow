<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed.';
    exit;
}

send_security_headers();
header('Cross-Origin-Resource-Policy: same-origin');
header('Content-Type: text/plain; charset=utf-8');

$rawId = $_GET['id'] ?? null;
if (!is_string($rawId) || preg_match('/^[1-9][0-9]{0,14}\z/', $rawId) !== 1) {
    http_response_code(404);
    echo 'Photograph not found.';
    exit;
}

try {
    $photo = slideshow_find_photo_for_serving(db_connection(), (int) $rawId);
} catch (Throwable $e) {
    error_log('Public slideshow photo serving failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    echo 'Photograph temporarily unavailable.';
    exit;
}

if ($photo === null) {
    http_response_code(404);
    echo 'Photograph not found.';
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
http_response_code(200);
header('Content-Type: ' . $photo['mime']);
header('Content-Length: ' . $photo['size']);
header('Content-Disposition: inline; filename="slideshow-photo-' . $photo['id'] . '.' . GALLERY_SERVED_TYPES[$photo['mime']] . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
if ($method === 'GET') {
    if (readfile($photo['path']) === false) {
        error_log('Slideshow photo ' . $photo['id'] . ': readfile() failed.');
    }
}
exit;
