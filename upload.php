<?php
declare(strict_types=1);

/**
 * Photo upload page (Step 5).
 *
 * What happens here
 *   GET   upload.php               the upload form (an unidentified visitor is sent to identify.php)
 *   POST  upload.php               in this order: identified visitor? -> request not too large? ->
 *                                  CSRF token -> visibility choice -> public consent when Public ->
 *                                  files (count, size, real type) -> store and record the photos ->
 *                                  REDIRECT to upload.php?done=1 (Post-Redirect-Get)
 *   GET   upload.php?done=1        the result (how many photographs were saved), shown once
 *
 * The owner of every photograph is the visitor of the server-side session. The form has no visitor
 * field, and nothing the browser sends can change the owner.
 *
 * This file builds all its HTML with PHP. The page uses the shared CSS design system and no JavaScript.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';

/** Relative addresses: they work in a sub-folder (XAMPP) and at the site root (Ubuntu). */
const UPLOAD_PAGE = 'upload.php';
const UPLOAD_RESULT_PAGE = 'upload.php?done=1';
const UPLOAD_IDENTIFY_PAGE = 'identify.php';

/** How long a saved result waits to be shown, in seconds. */
const UPLOAD_RESULT_LIFETIME_SECONDS = 300;

/** First part of every page. Sends the HTTP status and the start of the HTML. */
function upload_page_start(string $title, int $status): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="en">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="utf-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<link rel="stylesheet" href="assets/css/style.css?v=9">' . "\n";
    echo '<title>' . html_escape($title) . '</title>' . "\n";
    echo '</head>' . "\n";
    echo '<body class="app-body">' . "\n";
    echo '<div class="app-site-shell">' . "\n";
    echo '<header class="app-header"><p class="app-brand">' . html_escape((string) app_config('APP_NAME')) . '</p><span class="app-header-tag">Visitor Photo Slideshow</span></header>' . "\n";
    echo '<main class="app-main upload-page">' . "\n";
}

function upload_page_end(): void
{
    echo '</main>' . "\n";
    echo '</div>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/**
 * The saved result of the last upload, or null when there is none or it is too old (an old one is removed).
 *
 * @return null|array{uploaded: int, rejected: list<string>, created: int}
 */
function upload_pending_result(): ?array
{
    $result = $_SESSION['upload_result'] ?? null;
    if (
        !is_array($result)
        || !is_int($result['uploaded'] ?? null)
        || !is_array($result['rejected'] ?? null)
        || !is_int($result['created'] ?? null)
        || time() - $result['created'] > UPLOAD_RESULT_LIFETIME_SECONDS
    ) {
        unset($_SESSION['upload_result']);
        return null;
    }
    return $result;
}

/**
 * Shows the upload form, with the result of the last upload and the problems found (if any).
 *
 * @param array{name: string, id: int} $visitor
 * @param list<string> $errors
 * @param null|array{uploaded: int, rejected: list<string>, created: int, visibility?: string} $result
 */
function upload_render_form(array $visitor, array $errors, ?array $result, int $status, string $visibilityValue = 'private', bool $consentChecked = false): void
{
    upload_page_start('Share Your Memories', $status);

    $maxFiles = (int) app_config('UPLOAD_MAX_FILES');
    $maxSize = (int) app_config('UPLOAD_MAX_FILE_SIZE_BYTES');
    $allowed = upload_allowed_types();
    $labels = [];
    foreach (array_keys($allowed) as $mime) {
        $labels[] = match ($mime) {
            'image/jpeg' => 'JPEG',
            'image/png'  => 'PNG',
            'image/webp' => 'WebP',
            default      => $mime,
        };
    }

    echo '<section class="content-heading upload-heading">' . "\n";
    echo '<p class="section-eyebrow"><span aria-hidden="true">✦</span> Your memories, together</p>' . "\n";
    echo '<h1 class="page-title">Share Your Memories</h1>' . "\n";
    echo '<p class="page-subtitle">Add your favourite photographs to your personal gallery. Your files are checked securely and stored privately.</p>' . "\n";
    echo '</section>' . "\n";

    echo '<div class="upload-account-bar">' . "\n";
    echo '<p class="upload-identity"><span class="identity-mark" aria-hidden="true">' . '✦' . '</span>';
    echo '<span>Uploading as <strong>' . html_escape($visitor['name']) . '</strong></span></p>' . "\n";
    echo '<nav class="upload-page-links" aria-label="Visitor links"><a href="gallery.php">My photo gallery</a><a href="' . html_escape(UPLOAD_IDENTIFY_PAGE) . '">Session options</a></nav>' . "\n";
    echo '</div>' . "\n";

    if ($result !== null) {
        if ($result['uploaded'] > 0) {
            echo '<div class="notice notice-success upload-notice" role="status" aria-live="polite">' . "\n";
            echo '<span class="notice-icon" aria-hidden="true">✓</span><div><strong>' . html_escape((string) $result['uploaded']) . ' photograph' . ($result['uploaded'] === 1 ? ' was' : 's were') . ' uploaded successfully.</strong>';
            $savedVisibility = ($result['visibility'] ?? 'private') === 'public' ? 'public' : 'private';
            $resultCopy = $savedVisibility === 'public'
                ? 'These photographs are Public. They will appear on the shared slideshow after valid consent and administrator approval.'
                : 'These photographs are Private. Only you can view them in your identified visitor gallery.';
            echo '<p>' . html_escape($resultCopy) . '</p></div>' . "\n";
            echo '</div>' . "\n";
        }
        if ($result['rejected'] !== []) {
            echo '<div class="notice notice-error upload-notice" role="alert"><div><strong>Some files were not uploaded:</strong><ul>' . "\n";
            foreach ($result['rejected'] as $line) {
                echo '<li>' . html_escape((string) $line) . '</li>' . "\n";
            }
            echo '</ul></div></div>' . "\n";
        }
        if ($result['uploaded'] > 0) {
            echo '<section class="upload-next-steps" aria-labelledby="upload-next-title">' . "\n";
            echo '<div><p class="card-kicker">WHAT WOULD YOU LIKE TO DO NEXT?</p><h2 id="upload-next-title">Your memories are ready</h2>';
            echo '<p>Your personal slideshow includes these new photographs and photos from previous visits. Private photos remain visible only to you.</p></div>' . "\n";
            echo '<div class="upload-next-actions">' . "\n";
            echo '<a class="button button-primary" href="my_slideshow.php"><span aria-hidden="true">▶</span> Play my photo slideshow</a>' . "\n";
            echo '<a class="button button-secondary" href="#upload-card-title"><span aria-hidden="true">＋</span> Upload more photos</a>' . "\n";
            if (($result['visibility'] ?? 'private') === 'public') {
                echo '<a class="button button-quiet" href="slideshow.php">Open shared public display</a>' . "\n";
                echo '<p class="upload-next-note">Your new Public photos will join the shared display only after consent is recorded and an administrator approves them.</p>' . "\n";
            }
            echo '</div></section>' . "\n";
        }
    }

    if ($errors !== []) {
        echo '<div class="notice notice-error upload-notice" role="alert"><div><strong>Your photographs were not uploaded:</strong><ul>' . "\n";
        foreach ($errors as $error) {
            echo '<li>' . html_escape($error) . '</li>' . "\n";
        }
        echo '</ul></div></div>' . "\n";
    }

    echo '<section class="upload-card" aria-labelledby="upload-card-title">' . "\n";
    echo '<div class="upload-card-heading"><div><p class="card-kicker">PHOTO UPLOAD</p><h2 id="upload-card-title">Choose your photographs</h2><p>Select image files from your device. The visibility choice below applies to every photo in this upload.</p></div><span class="upload-card-symbol" aria-hidden="true">▧</span></div>' . "\n";

    echo '<form class="upload-form" method="post" action="' . html_escape(UPLOAD_PAGE) . '" enctype="multipart/form-data">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<label class="upload-dropzone" for="photos">' . "\n";
    echo '<span class="upload-cloud-icon" aria-hidden="true">↑</span>' . "\n";
    echo '<strong class="upload-drop-title">Select photos to upload</strong>' . "\n";
    echo '<span class="upload-drop-copy">Choose image files from your phone, tablet or computer. This visibility choice applies to every photo in this upload. To use different choices, upload those photos separately.</span>' . "\n";
    echo '<input class="upload-file-input" type="file" id="photos" name="photos[]" multiple accept="' . html_escape(implode(',', array_keys($allowed))) . '" required>' . "\n";
    echo '<span class="upload-file-hint">Selected file names appear beside the file picker when your browser supports it.</span>' . "\n";
    echo '</label>' . "\n";

    echo '<div class="upload-limits" aria-label="Upload limits">' . "\n";
    echo '<div><span class="limit-label">Supported formats</span><strong>' . html_escape(implode(' · ', $labels)) . '</strong></div>' . "\n";
    echo '<div><span class="limit-label">Maximum file size</span><strong>' . html_escape(upload_format_size($maxSize)) . ' per photo</strong></div>' . "\n";
    echo '<div><span class="limit-label">Files per upload</span><strong>Up to ' . html_escape((string) $maxFiles) . ' photos</strong></div>' . "\n";
    echo '</div>' . "\n";

    echo '<fieldset class="upload-visibility"><legend>Who can see all photographs in this upload?</legend>' . "\n";
    echo '<label class="visibility-option" for="visibility_private"><input type="radio" id="visibility_private" name="visibility" value="private"' . ($visibilityValue === 'private' ? ' checked' : '') . ' required><span><strong>Private</strong><small>Only you can view these photos in your personal gallery. They will never appear on the public slideshow.</small></span></label>' . "\n";
    echo '<label class="visibility-option" for="visibility_public"><input type="radio" id="visibility_public" name="visibility" value="public"' . ($visibilityValue === 'public' ? ' checked' : '') . ' required><span><strong>Public</strong><small>Everyone can see these photos on the shared slideshow only after you give consent and an administrator approves them.</small></span></label>' . "\n";
    echo '</fieldset>' . "\n";

    echo '<div class="consent-panel"><div class="consent-panel-heading"><span aria-hidden="true">ⓘ</span><strong>Public photo sharing consent</strong></div>' . "\n";
    echo '<p>' . html_escape(upload_consent_text()) . '</p>' . "\n";
    echo '<p class="consent-detail">This consent is required only when you choose Public. Choosing Private does not create a public-display consent record. Consent text version ' . html_escape(UPLOAD_CONSENT_VERSION) . '.</p>' . "\n";
    echo '<label class="consent-check" for="consent"><input type="checkbox" id="consent" name="consent" value="yes"' . ($consentChecked ? ' checked' : '') . '><span>I agree to public display of the photos in this upload (required only for Public).</span></label>' . "\n";
    echo '</div>' . "\n";

    echo '<button class="button button-primary upload-submit" type="submit"><span aria-hidden="true">↑</span> Upload photographs</button>' . "\n";
    echo '<p class="upload-security-note"><span aria-hidden="true">●</span> Your upload is associated with your signed-in visitor session.</p>' . "\n";
    echo '</form>' . "\n";
    echo '</section>' . "\n";

    echo '<p class="upload-bottom-link">Already uploaded photos? <a href="gallery.php">Open your photo gallery</a></p>' . "\n";
    upload_page_end();
}

// ---------------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed.';
    exit;
}

send_security_headers(false, true);
session_start_secure();

// 1. Authentication. An unidentified visitor is sent to identify.php and nothing below runs.
//    The visitor id comes from the server-side session and from nowhere else.
$visitor = visitor_require_login(UPLOAD_IDENTIFY_PAGE);

$status = 200;
$errors = [];

if ($method === 'POST') {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($_POST === [] && $_FILES === [] && $contentLength > upload_post_max_bytes()) {
        // PHP throws away the whole form when the request is larger than post_max_size.
        $status = 413;
        $errors[] = 'The photographs together are larger than the server accepts. Please choose fewer or smaller photographs.';
    } elseif (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        // 2. CSRF. Rejected: no file is read, nothing is stored.
        if (upload_pending_result() !== null) {
            // A double click or the Back button after a successful upload: show that result instead.
            header('Location: ' . UPLOAD_RESULT_PAGE, true, 303);
            exit;
        }
        $status = 403;
        $errors[] = 'The form could not be verified (it may have expired). Please try again.';
    } elseif (!is_string($_POST['visibility'] ?? null) || !in_array($_POST['visibility'], UPLOAD_VISIBILITIES, true)) {
        $status = 422;
        $errors[] = 'Please choose Private or Public visibility for these photographs.';
    } elseif ($_POST['visibility'] === 'public' && ($_POST['consent'] ?? null) !== 'yes') {
        // Public visibility requires an explicit agreement on every public upload.
        $status = 422;
        $errors[] = 'Please agree to the public-display consent, or choose Private instead.';
    } else {
        $visibilityValue = $_POST['visibility'];
        $consentChecked = ($_POST['consent'] ?? null) === 'yes';
        $files = upload_collect_files($_FILES['photos'] ?? null);
        if ($files === null) {
            $status = 400;
            $errors[] = 'The request was not understood. Please use the form below.';
        } else {
            try {
                $storage = upload_storage_directory();
                if ($storage === null) {
                    throw new RuntimeException('The private storage folder cannot be used.');
                }
                // 4-10: validate, store privately, record in the database, clean up after a failure.
                $outcome = upload_process(db_connection(), $visitor['id'], $files, $storage, ['visibility' => $visibilityValue]);
            } catch (Throwable $e) {
                error_log(sprintf('Photo upload could not start: %s: %s', get_class($e), $e->getMessage()));
                $outcome = [
                    'uploaded' => 0, 'rejected' => [], 'technical' => true,
                    'error' => 'Uploads are not available because of a technical problem. Please try again later.',
                ];
            }

            if ($outcome['uploaded'] > 0) {
                // Success: use up the CSRF token, keep the result in the session, then Post-Redirect-Get.
                csrf_rotate();
                $_SESSION['upload_result'] = [
                    'uploaded' => $outcome['uploaded'],
                    'rejected' => $outcome['rejected'],
                    'created'  => time(),
                    'visibility' => $visibilityValue,
                ];
                header('Location: ' . UPLOAD_RESULT_PAGE, true, 303);
                exit;
            }

            $status = $outcome['technical'] ? 500 : 422;
            if ($outcome['error'] !== null) {
                $errors[] = $outcome['error'];
            }
            foreach ($outcome['rejected'] as $line) {
                $errors[] = $line;
            }
        }
    }
}

// A saved result is shown once, on a normal page view.
$result = null;
if ($status === 200) {
    $result = upload_pending_result();
    unset($_SESSION['upload_result']);
}

upload_render_form($visitor, $errors, $result, $status, $visibilityValue ?? 'private', $consentChecked ?? false);
