<?php
declare(strict_types=1);

/**
 * Returning visitor identification page (Step 4).
 *
 * What happens here
 *   GET   identify.php                  the form (or, for an identified visitor, who they are)
 *   POST  identify.php  (identify)      check the visitor code, then REDIRECT (Post-Redirect-Get):
 *                                         right code -> identify.php?identified=1  (welcome page)
 *                                         wrong code -> identify.php               (message from the session)
 *   GET   identify.php?identified=1     the welcome page, shown from the session
 *   POST  identify.php  (logout)        ends the visitor session
 *
 * The submitted code is hashed with the Step 3 rule (visitor_code_hash) and only the hash is
 * looked up in visitors.visitor_code_hash. The code is never stored, never put back into the page
 * and never written to the session. No new visitor and no new code is ever created here.
 *
 * This file builds all its HTML with PHP. There is no CSS and no JavaScript.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';

/** Relative addresses: they work in a sub-folder (XAMPP) and at the site root (Ubuntu). */
const IDENTIFY_PAGE = 'identify.php';
const IDENTIFY_RESULT_PAGE = 'identify.php?identified=1';
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
    echo '<title>' . html_escape($title) . '</title>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
    echo '<h1>' . html_escape((string) app_config('APP_NAME')) . '</h1>' . "\n";
}

function identify_page_end(): void
{
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/** Shows the identification form, with a message (if any). The typed code is never shown again. */
function identify_render_form(?string $message, int $status): void
{
    identify_page_start('Visitor identification', $status);

    echo '<h2>Returning visitor</h2>' . "\n";
    echo '<p>Already registered? Enter your visitor code to identify yourself. '
        . 'You can type it in any letter case, with or without the hyphens.</p>' . "\n";

    if ($message !== null) {
        echo '<p><strong>' . html_escape($message) . '</strong></p>' . "\n";
    }

    echo '<form method="post" action="' . html_escape(IDENTIFY_PAGE) . '" autocomplete="off">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="identify">' . "\n";
    echo '<p><label for="visitor_code">Your visitor code</label><br>' . "\n";
    echo '<input type="text" id="visitor_code" name="visitor_code" value="" maxlength="64" '
        . 'autocomplete="off" autocapitalize="characters" spellcheck="false"></p>' . "\n";
    echo '<p><button type="submit">Identify me</button></p>' . "\n";
    echo '</form>' . "\n";

    echo '<p>First visit? <a href="' . html_escape(IDENTIFY_REGISTER_PAGE) . '">Register here</a>.</p>' . "\n";

    identify_page_end();
}

/** Shows the success message for an identified visitor, with the button that ends the session. */
function identify_render_welcome(array $visitor, bool $justIdentified): void
{
    identify_page_start('Visitor identified', 200);

    if ($justIdentified) {
        echo '<h2>Welcome back, ' . html_escape($visitor['name']) . '!</h2>' . "\n";
        echo '<p>You have been identified successfully. No new registration was made.</p>' . "\n";
    } else {
        echo '<h2>You are identified</h2>' . "\n";
        echo '<p>You are identified as ' . html_escape($visitor['name']) . '.</p>' . "\n";
    }
    echo '<p>Visitor number: ' . html_escape((string) $visitor['id']) . '</p>' . "\n";

    echo '<form method="post" action="' . html_escape(IDENTIFY_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="logout">' . "\n";
    echo '<p><button type="submit">End my session</button></p>' . "\n";
    echo '</form>' . "\n";

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

send_security_headers();
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
                visitor_auth_redirect(IDENTIFY_RESULT_PAGE);
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
    identify_render_welcome($current, isset($_GET['identified']));
} else {
    if ($message === null && $status === 200 && isset($_GET['identified'])) {
        $message = 'You are not identified. Please enter your visitor code.';
    }
    identify_render_form($message, $status);
}
