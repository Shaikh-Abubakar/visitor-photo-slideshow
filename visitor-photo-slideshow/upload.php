<?php
declare(strict_types=1);

/**
 * Photo upload page (Step 5).
 *
 * What happens here
 *   GET   upload.php               the upload form (an unidentified visitor is sent to identify.php)
 *   POST  upload.php               in this order: identified visitor? -> request not too large? ->
 *                                  CSRF token -> consent ticked -> files (count, size, real type)
 *                                  -> store files privately and record them in the database ->
 *                                  REDIRECT to upload.php?done=1 (Post-Redirect-Get)
 *   GET   upload.php?done=1        the result (how many photographs were saved), shown once
 *
 * The owner of every photograph is the visitor of the server-side session. The form has no visitor
 * field, and nothing the browser sends can change the owner.
 *
 * This file builds all its HTML with PHP. There is no CSS and no JavaScript.
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
    echo '<title>' . html_escape($title) . '</title>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
    echo '<h1>' . html_escape((string) app_config('APP_NAME')) . '</h1>' . "\n";
}

function upload_page_end(): void
{
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
 * @param null|array{uploaded: int, rejected: list<string>, created: int} $result
 */
function upload_render_form(array $visitor, array $errors, ?array $result, int $status): void
{
    upload_page_start('Upload photographs', $status);

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

    echo '<h2>Upload photographs</h2>' . "\n";
    echo '<p>You are identified as ' . html_escape($visitor['name']) . ' (visitor number '
        . html_escape((string) $visitor['id']) . '). <a href="' . html_escape(UPLOAD_IDENTIFY_PAGE)
        . '">Identification and end of session</a></p>' . "\n";

    if ($result !== null) {
        if ($result['uploaded'] > 0) {
            echo '<p><strong>' . html_escape((string) $result['uploaded']) . ' photograph'
                . ($result['uploaded'] === 1 ? ' was' : 's were') . ' uploaded successfully.</strong></p>' . "\n";
        }
        if ($result['rejected'] !== []) {
            echo '<p><strong>These files were not uploaded:</strong></p>' . "\n";
            echo '<ul>' . "\n";
            foreach ($result['rejected'] as $line) {
                echo '<li>' . html_escape((string) $line) . '</li>' . "\n";
            }
            echo '</ul>' . "\n";
        }
    }

    if ($errors !== []) {
        echo '<p><strong>Your photographs were not uploaded:</strong></p>' . "\n";
        echo '<ul>' . "\n";
        foreach ($errors as $error) {
            echo '<li>' . html_escape($error) . '</li>' . "\n";
        }
        echo '</ul>' . "\n";
    }

    echo '<p>You can choose up to ' . html_escape((string) $maxFiles) . ' photographs at once. Each may be up to '
        . html_escape(upload_format_size($maxSize)) . '. Accepted formats: ' . html_escape(implode(', ', $labels)) . '.</p>' . "\n";

    echo '<form method="post" action="' . html_escape(UPLOAD_PAGE) . '" enctype="multipart/form-data">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<p><label for="photos">Photographs</label><br>' . "\n";
    echo '<input type="file" id="photos" name="photos[]" multiple accept="' . html_escape(implode(',', array_keys($allowed))) . '"></p>' . "\n";
    echo '<p><input type="checkbox" id="consent" name="consent" value="yes"> '
        . '<label for="consent">' . html_escape(upload_consent_text()) . '</label></p>' . "\n";
    echo '<p>You must tick this box every time you upload photographs. Photographs are not shown without your consent. '
        . '(Consent text version ' . html_escape(UPLOAD_CONSENT_VERSION) . ')</p>' . "\n";
    echo '<p><button type="submit">Upload photographs</button></p>' . "\n";
    echo '</form>' . "\n";

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

send_security_headers();
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
    } elseif (($_POST['consent'] ?? null) !== 'yes') {
        // 3. Consent is never automatic: the box must be ticked for every upload.
        $status = 422;
        $errors[] = 'Please tick the consent box. Photographs cannot be uploaded without your consent.';
    } else {
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
                $outcome = upload_process(db_connection(), $visitor['id'], $files, $storage);
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

upload_render_form($visitor, $errors, $result, $status);
