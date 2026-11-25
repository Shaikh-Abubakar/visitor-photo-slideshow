<?php
declare(strict_types=1);

/**
 * Private photograph endpoint (Step 6).
 *
 *   GET photo.php?id=<photo number>
 *
 * Sends one photograph from private storage to the visitor who owns it, and to nobody else. This
 * endpoint is for the owner gallery; Public slideshow photos use slideshow_photo.php.
 * Checks, in this order (the first failure ends the request with a short plain-text answer):
 *
 *   1. method is GET or HEAD                          else 405
 *   2. a visitor is identified (server-side session)  else 401   (no redirect: this is an image)
 *   3. the id is a positive whole number              else 400
 *   4. the photograph exists, belongs to THIS visitor and has status 'active'; Private photos need
 *      no public consent, while Public photos need a matching consent record (one prepared query)
 *                                                   else 404   (same answer for "not yours")
 *   5. the stored name has the generated shape and the real file lies inside the private storage
 *      folder and exists                              else 404
 *   6. the real content of the file is JPEG, PNG or WebP and agrees with the database
 *                                                     else 404
 *   7. the file is sent with readfile() and safe headers.
 *
 * The visitor id comes from the session and from nowhere else; there is no visitor parameter. The
 * answers never contain a file path, a stored file name, an SQL error or the name of the owner.
 * The file is only ever sent as an image with a fixed image Content-Type, never run as a script.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';

/** Ends the request with a short plain-text answer. */
function photo_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    photo_fail(405, 'Method not allowed.');
}

send_security_headers();
// The photograph is private: no shared cache and no browser cache may keep it (see send_security_headers).
header('Cross-Origin-Resource-Policy: same-origin');
session_start_secure();

// 2. Authentication.
$visitor = visitor_current();
if ($visitor === null) {
    photo_fail(401, 'Please identify yourself first.');
}
// Release the session file now: the gallery loads many images at once and each request would
// otherwise wait for the previous one. Nothing below writes to the session.
session_write_close();

// 3. The photo number.
$photoId = gallery_parse_positive_int($_GET['id'] ?? null);
if ($photoId === null) {
    photo_fail(400, 'Invalid photograph number.');
}

// 4-6. Database record, file location, file type.
try {
    $photo = gallery_find_visible_photo(db_connection(), $photoId, $visitor['id']);
    if ($photo === null) {
        photo_fail(404, 'Photograph not found.');
    }

    $realPath = gallery_resolve_file((string) app_config('UPLOAD_STORAGE_PATH'), $photo['stored_filename']);
    if ($realPath === null) {
        // Only the photograph number is logged: never the path or the stored name.
        error_log('Photo ' . $photo['id'] . ': the image file is missing, unreadable or outside private storage.');
        photo_fail(404, 'Photograph not found.');
    }

    $mime = gallery_detect_mime($realPath);
    if ($mime === null || $mime !== $photo['mime_type']) {
        error_log('Photo ' . $photo['id'] . ': the file content is not the image type recorded in the database.');
        photo_fail(404, 'Photograph not found.');
    }

    $size = filesize($realPath);
    if ($size === false) {
        photo_fail(404, 'Photograph not found.');
    }
} catch (Throwable $e) {
    error_log(sprintf('Photo serving failed: %s: %s', get_class($e), $e->getMessage()));
    photo_fail(500, 'The photograph could not be shown because of a technical problem.');
}

// 7. Send it. Any buffered output (a stray space in an include, for example) would corrupt the image.
while (ob_get_level() > 0) {
    ob_end_clean();
}

http_response_code(200);
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
// nosniff is already sent by send_security_headers(); repeated here because it matters most for this file.
header('X-Content-Type-Options: nosniff');
// A fixed, generated file name: the stored name and the visitor's own file name are not revealed.
header('Content-Disposition: inline; filename="photo-' . $photo['id'] . '.' . GALLERY_SERVED_TYPES[$mime] . '"');
// If somebody opened the image address as a page, nothing in it could run or load anything.
header("Content-Security-Policy: default-src 'none'; sandbox");

if ($method === 'GET') {
    if (readfile($realPath) === false) {
        error_log('Photo ' . $photo['id'] . ': readfile() failed.');
    }
}
exit;
