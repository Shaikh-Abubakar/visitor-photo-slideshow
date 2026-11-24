<?php
declare(strict_types=1);

/**
 * Visitor registration page (Step 3).
 *
 * What happens here
 *   GET   register.php                 the registration form
 *   POST  register.php  (register)     validate the name, create the visitor, then REDIRECT to
 *                                      register.php?registered=1 (Post-Redirect-Get)
 *   GET   register.php?registered=1    the result: the visitor code, shown from the session
 *   POST  register.php  (clear)        "register another visitor": forgets the result in the session
 *
 * The visitor code is shown from the PHP session, not from the database (the database only holds
 * its hash). It stays in the session for REGISTRATION_RESULT_LIFETIME_SECONDS so the result page can
 * be refreshed without creating a second visitor or a different code, and is then discarded.
 *
 * This file builds all its HTML with PHP. There is no CSS and no JavaScript.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';

/** How long the result (and so the visitor code) stays available in the session, in seconds. */
const REGISTRATION_RESULT_LIFETIME_SECONDS = 900;

/** Relative address of this page: works in a sub-folder (XAMPP) and at the site root (Ubuntu). */
const REGISTER_PAGE = 'register.php';
const REGISTER_RESULT_PAGE = 'register.php?registered=1';

/**
 * The registration result waiting in the session, or null when there is none or it is too old
 * (an old one is removed). Expected shape: ['name' => string, 'code' => string, 'created' => int].
 *
 * @return null|array{name: string, code: string, created: int}
 */
function registration_pending_result(): ?array
{
    $result = $_SESSION['registration_result'] ?? null;
    if (
        !is_array($result)
        || !is_string($result['name'] ?? null)
        || !is_string($result['code'] ?? null)
        || !is_int($result['created'] ?? null)
        || time() - $result['created'] > REGISTRATION_RESULT_LIFETIME_SECONDS
    ) {
        unset($_SESSION['registration_result']);
        return null;
    }
    return $result;
}

/** Sends the browser to another address with a GET request (303) and stops. */
function redirect_to(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

/** First part of every page. Sends the HTTP status and the start of the HTML. */
function page_start(string $title, int $status): void
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

function page_end(): void
{
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/** Shows the registration form, with the problems found (if any) and the name typed so far. */
function render_form(array $errors, string $nameValue, ?string $notice, int $status): void
{
    page_start('Visitor registration', $status);

    echo '<h2>Visitor registration</h2>' . "\n";
    echo '<p>First visit? Enter your name to register. You will receive a personal visitor code.</p>' . "\n";

    if ($notice !== null) {
        echo '<p>' . html_escape($notice) . '</p>' . "\n";
    }
    if ($errors !== []) {
        echo '<p><strong>Registration was not completed:</strong></p>' . "\n";
        echo '<ul>' . "\n";
        foreach ($errors as $error) {
            echo '<li>' . html_escape($error) . '</li>' . "\n";
        }
        echo '</ul>' . "\n";
    }

    echo '<form method="post" action="' . html_escape(REGISTER_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="register">' . "\n";
    echo '<p><label for="visitor_name">Your name</label><br>' . "\n";
    echo '<input type="text" id="visitor_name" name="name" value="' . html_escape($nameValue)
        . '" maxlength="' . VISITOR_NAME_MAX_LENGTH . '" autocomplete="name"></p>' . "\n";
    echo '<p><button type="submit">Register</button></p>' . "\n";
    echo '</form>' . "\n";

    page_end();
}

/** Shows the success message and the new visitor code. */
function render_result(array $result): void
{
    page_start('Registration successful', 200);

    $minutes = intdiv(REGISTRATION_RESULT_LIFETIME_SECONDS, 60);

    echo '<h2>Registration successful</h2>' . "\n";
    echo '<p>Thank you, ' . html_escape($result['name']) . '. You are now registered.</p>' . "\n";
    echo '<p>Your visitor code is:</p>' . "\n";
    echo '<p><code><strong>' . html_escape($result['code']) . '</strong></code></p>' . "\n";
    echo '<p><strong>Save this code now.</strong> Write it down or copy it to a safe place. '
        . 'You will need it to identify yourself on future visits.</p>' . "\n";
    echo '<p>The system keeps only a one-way scrambled form of the code, so it cannot show the code '
        . 'to you again. If you lose it, it cannot be recovered.</p>' . "\n";
    echo '<p>For your convenience this page keeps showing the code for ' . $minutes
        . ' minutes in this browser (you may refresh it). After that the code is removed from the page.</p>' . "\n";

    echo '<form method="post" action="' . html_escape(REGISTER_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="clear">' . "\n";
    echo '<p><button type="submit">I have saved my code - register another visitor</button></p>' . "\n";
    echo '</form>' . "\n";

    page_end();
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

$errors = [];
$nameValue = '';
$notice = null;
$status = 200;

if ($method === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        // Rejected: nothing is read from the form and nothing is stored.
        if (registration_pending_result() !== null) {
            // The visitor was registered a moment ago (double click, or the Back button
            // after registering): show that result instead of an error.
            redirect_to(REGISTER_RESULT_PAGE);
        }
        $status = 403;
        $errors[] = 'The form could not be verified (it may have expired). Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'clear') {
            unset($_SESSION['registration_result']);
            csrf_rotate();
            redirect_to(REGISTER_PAGE);
        } elseif ($action !== 'register') {
            $status = 400;
            $errors[] = 'The request was not understood. Please use the form below.';
        } elseif (registration_pending_result() !== null) {
            // Never register a second visitor while a result is still waiting to be saved.
            redirect_to(REGISTER_RESULT_PAGE);
        } else {
            $check = visitor_name_validate($_POST['name'] ?? null);
            $nameValue = $check['name'];

            if ($check['errors'] !== []) {
                $status = 422;
                $errors = $check['errors'];
            } else {
                try {
                    $registered = visitor_register(db_connection(), $check['name']);
                } catch (Throwable $e) {
                    // Details go to the server log only. The visitor code is never part of a message.
                    error_log(sprintf('Visitor registration failed: %s: %s', get_class($e), $e->getMessage()));
                    $registered = null;
                }

                if ($registered === null) {
                    $status = 500;
                    $errors[] = 'Registration could not be completed because of a technical problem. Please try again later.';
                } else {
                    // Success: use up the CSRF token, keep the result in the session, then Post-Redirect-Get.
                    // The session id is deliberately NOT renewed here: registering changes no privileges, and
                    // renewing it deletes the session file that a simultaneous second request (a double click)
                    // is already waiting for, which lets that request see the old, still valid token.
                    csrf_rotate();
                    $_SESSION['registration_result'] = [
                        'name'    => $check['name'],
                        'code'    => $registered['code'],
                        'created' => time(),
                    ];
                    redirect_to(REGISTER_RESULT_PAGE);
                }
            }
        }
    }
}

$pending = registration_pending_result();
if ($pending !== null && $status === 200) {
    render_result($pending);
} else {
    if (isset($_GET['registered']) && $status === 200) {
        $notice = 'The result of the registration is no longer available. If you did not save your visitor code, '
            . 'please register again.';
    }
    render_form($errors, $nameValue, $notice, $status);
}
