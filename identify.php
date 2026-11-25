<?php
declare(strict_types=1);

/**
 * Returning visitor identification page (Step 4).
 *
 * What happens here
 *   GET   identify.php                  the form; an already identified visitor goes straight to their slideshow
 *   POST  identify.php  (identify)      verify the code, establish the session, then redirect directly to my_slideshow.php
 *                                         wrong code -> identify.php               (message from the session)
 *   POST  identify.php  (logout)        ends the visitor session
 *
 * The submitted code is hashed with the Step 3 rule (visitor_code_hash) and only the hash is
 * looked up in visitors.visitor_code_hash. The code is never stored, never put back into the page
 * and never written to the session. No new visitor and no new code is ever created here.
 *
 * A valid visitor never sees a separate welcome/Play interstitial: identification starts their
 * personal slideshow directly. The page uses the shared same-origin stylesheet and no JavaScript.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';

/** Relative addresses: they work in a sub-folder (XAMPP) and at the site root (Ubuntu). */
const IDENTIFY_PAGE = 'identify.php';
const IDENTIFY_REGISTER_PAGE = 'register.php';

/** First part of every page. Sends the HTTP status and the start of the HTML. */
function identify_page_start(string $title, int $status): void
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
    echo '<main class="app-main auth-page auth-page--identify">' . "\n";
}

function identify_page_end(): void
{
    echo '</main>' . "\n";
    echo '</div>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/** Shows the identification form, with a message (if any). The typed code is never shown again. */
function identify_render_form(?string $message, int $status): void
{
    identify_page_start('Visitor identification', $status);

    echo '<section class="auth-shell" aria-labelledby="identification-title">' . "\n";
    echo '<div class="auth-heading">' . "\n";
    echo '<span class="auth-eyebrow">Good to see you again</span>' . "\n";
    echo '<h1 class="page-title" id="identification-title">Welcome Back</h1>' . "\n";
    echo '<p class="page-subtitle">Enter the visitor code you received when you registered. We will use it to securely find your visitor profile and photographs.</p>' . "\n";
    echo '</div>' . "\n";

    echo '<section class="auth-card" aria-label="Returning visitor identification form">' . "\n";
    echo '<div class="auth-card-heading"><span class="auth-card-icon auth-card-icon--return" aria-hidden="true">↗</span><div><h2 class="section-title">Identify your visit</h2><p class="auth-card-description">Your visitor code is the only information needed here.</p></div></div>' . "\n";

    if ($message !== null) {
        $messageIsNotice = $message === 'Your session has ended.';
        $messageClass = $messageIsNotice ? 'alert-info' : 'alert-error';
        $messageRole = $messageIsNotice ? 'status' : 'alert';
        echo '<div class="' . $messageClass . ' auth-alert" role="' . $messageRole . '">'
            . html_escape($message) . '</div>' . "\n";
    }

    echo '<form class="auth-form" method="post" action="' . html_escape(IDENTIFY_PAGE) . '" autocomplete="off">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="identify">' . "\n";
    echo '<div class="form-field">' . "\n";
    echo '<label class="form-label" for="visitor_code">Your visitor code <span class="required-indicator" aria-hidden="true">*</span></label>' . "\n";
    echo '<input type="text" id="visitor_code" name="visitor_code" value="" maxlength="64" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Enter your visitor code" required aria-required="true">' . "\n";
    echo '<small class="form-help">You can enter the code with or without hyphens, and in upper- or lower-case letters.</small>' . "\n";
    echo '</div>' . "\n";
    echo '<button class="auth-submit" type="submit">Identify and continue <span aria-hidden="true">→</span></button>' . "\n";
    echo '</form>' . "\n";

    echo '<p class="auth-switch">New to the slideshow? <a href="' . html_escape(IDENTIFY_REGISTER_PAGE) . '">Register as a visitor</a>.</p>' . "\n";
    echo '<p class="auth-privacy-note"><span aria-hidden="true">◆</span> Your code is checked securely and is not displayed again after submission.</p>' . "\n";
    echo '</section>' . "\n";
    echo '</section>' . "\n";

    identify_page_end();
}

/** The sentence shown for a flash message kept in the session by a redirected request. */
function identify_flash_message(string $flash): ?string
{
    switch ($flash) {
        case 'invalid':
            // One neutral sentence: it does not say whether a name or an account exists.
            return 'That visitor code was not recognised. Please check it and try again.';
        case 'locked':
            $wait = visitor_throttle_wait_seconds();
            if ($wait <= 0) {
                return null; // the lock ended while the page was loading
            }
            return 'Too many unsuccessful attempts. Please wait about ' . max(1, (int) ceil($wait / 60))
                . ' minute(s) and try again.';
        case 'signed_out':
            return 'Your session has ended.';
        default:
            return null;
    }
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

$status = 200;
$message = null;

if ($method === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        // Rejected: nothing is read from the form, nothing is looked up, nothing is counted.
        $status = 403;
        $message = 'The form could not be verified (it may have expired). Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'logout') {
            visitor_logout();
            $_SESSION['identify_flash'] = 'signed_out';
            visitor_auth_redirect(IDENTIFY_PAGE);
        } elseif ($action !== 'identify') {
            $status = 400;
            $message = 'The request was not understood. Please use the form below.';
        } elseif (visitor_current() !== null) {
            // Already identified: switching to another visitor needs "End my session" first.
            visitor_auth_redirect(IDENTIFY_PAGE);
        } elseif (visitor_throttle_wait_seconds() > 0) {
            // Locked: the code is not even looked up, so a locked client learns nothing.
            $_SESSION['identify_flash'] = 'locked';
            visitor_auth_redirect(IDENTIFY_PAGE);
        } else {
            $submitted = $_POST['visitor_code'] ?? null;
            $visitor = null;
            $technicalProblem = false;

            if (is_string($submitted)) {
                try {
                    $visitor = visitor_find_by_code(db_connection(), $submitted);
                } catch (Throwable $e) {
                    // Details go to the server log only. The submitted code is never part of a message.
                    error_log(sprintf('Visitor identification failed: %s: %s', get_class($e), $e->getMessage()));
                    $technicalProblem = true;
                }
            }

            if ($technicalProblem) {
                $status = 500;
                $message = 'Identification could not be completed because of a technical problem. Please try again later.';
            } elseif ($visitor === null) {
                // Missing field, malformed code and unknown code all end here, with the same answer.
                visitor_throttle_record_failure();
                $_SESSION['identify_flash'] = 'invalid';
                visitor_auth_redirect(IDENTIFY_PAGE);
            } else {
                // Success: new session id, identity into the session, then Post-Redirect-Get.
                visitor_login($visitor['id'], $visitor['name']);
                // Automatically replay the returning visitor's saved photos after code verification.
                visitor_auth_redirect('my_slideshow.php?welcome=1');
            }
        }
    }
}

// Only a normal page view uses up a stored message; an error page keeps it for the next view.
$flash = null;
if ($status === 200 && isset($_SESSION['identify_flash']) && is_string($_SESSION['identify_flash'])) {
    $flash = $_SESSION['identify_flash'];
    unset($_SESSION['identify_flash']);
    $message = identify_flash_message($flash);
}

$current = visitor_current();
if ($current !== null && $status === 200) {
    // An authenticated returning visitor must never see a second "Play previous photos" prompt.
    // Visiting this page again (including browser Back) resumes the personal slideshow directly.
    visitor_auth_redirect('my_slideshow.php?welcome=1');
}

if ($message === null && $status === 200 && isset($_GET['identified'])) {
    $message = 'You are not identified. Please enter your visitor code.';
}
identify_render_form($message, $status);
