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
 * The page uses the shared same-origin stylesheet. It contains no JavaScript.
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
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<link rel="stylesheet" href="assets/css/style.css?v=9">' . "\n";
    echo '<title>' . html_escape($title) . '</title>' . "\n";
    echo '</head>' . "\n";
    echo '<body class="app-body">' . "\n";
    echo '<div class="app-site-shell">' . "\n";
    echo '<header class="app-header"><p class="app-brand">' . html_escape((string) app_config('APP_NAME')) . '</p><span class="app-header-tag">Visitor Photo Slideshow</span></header>' . "\n";
    echo '<main class="app-main auth-page auth-page--register">' . "\n";
}

function page_end(): void
{
    echo '</main>' . "\n";
    echo '</div>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/** Shows the registration form, with the problems found (if any) and the name typed so far. */
function render_form(array $errors, string $nameValue, ?string $notice, int $status, bool $nameExists = false): void
{
    page_start('Visitor registration', $status);

    echo '<section class="auth-shell" aria-labelledby="registration-title">' . "\n";
    echo '<div class="auth-heading">' . "\n";
    echo '<span class="auth-eyebrow">A warm welcome</span>' . "\n";
    echo '<h1 class="page-title" id="registration-title">Welcome to Our Visitor Photo Slideshow</h1>' . "\n";
    echo '<p class="page-subtitle">Register with your name to receive a secure visitor code. Keep that code safe; you will use it to return to your photographs on a future visit.</p>' . "\n";
    echo '</div>' . "\n";

    echo '<section class="auth-card" aria-label="Visitor registration form">' . "\n";
    echo '<div class="auth-card-heading"><span class="auth-card-icon" aria-hidden="true">✦</span><div><h2 class="section-title">Create your visitor pass</h2><p class="auth-card-description">All fields marked with <span class="required-indicator">*</span> are required.</p></div></div>' . "\n";

    if ($notice !== null) {
        echo '<div class="alert-info auth-alert" role="status">' . html_escape($notice) . '</div>' . "\n";
    }
    if ($errors !== []) {
        echo '<div class="alert-error auth-alert" role="alert">' . "\n";
        echo '<strong>Registration was not completed.</strong>' . "\n";
        echo '<ul>' . "\n";
        foreach ($errors as $error) {
            echo '<li>' . html_escape($error) . '</li>' . "\n";
        }
        echo '</ul>' . "\n";
        echo '</div>' . "\n";
    }

    echo '<form class="auth-form" method="post" action="' . html_escape(REGISTER_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="register">' . "\n";
    echo '<div class="form-field">' . "\n";
    echo '<label class="form-label" for="visitor_name">Your name <span class="required-indicator" aria-hidden="true">*</span></label>' . "\n";
    echo '<input type="text" id="visitor_name" name="name" value="' . html_escape($nameValue)
        . '" maxlength="' . VISITOR_NAME_MAX_LENGTH . '" autocomplete="name" placeholder="Enter your full name" required aria-required="true">' . "\n";
    echo '<small class="form-help">Use the name you would like associated with your photographs.</small>' . "\n";
    echo '</div>' . "\n";
    if ($nameExists) {
        echo '<div class="notice notice-warning duplicate-visitor-warning" role="alert"><strong>This name is already registered.</strong><p>If you have visited before, do not create another visitor record. Use your original visitor code to identify yourself and open your existing gallery.</p><p><a href="identify.php">I already have a visitor code</a></p></div>' . "\n";
        echo '<label class="duplicate-visitor-confirm"><input type="checkbox" name="confirm_new_visitor" value="yes"><span>I am a different visitor with the same name. Create a separate visitor pass for me.</span></label>' . "\n";
    }
    echo '<button class="auth-submit" type="submit">Register <span aria-hidden="true">→</span></button>' . "\n";
    echo '</form>' . "\n";

    echo '<p class="auth-switch">Already registered? <a href="identify.php">Identify yourself here</a>.</p>' . "\n";
    echo '<p class="auth-privacy-note"><span aria-hidden="true">◆</span> Your visitor code is shown only after successful registration.</p>' . "\n";
    echo '</section>' . "\n";
    echo '</section>' . "\n";

    page_end();
}

/** Shows the success message and the new visitor code. */
function render_result(array $result): void
{
    page_start('Registration successful', 200);

    $minutes = intdiv(REGISTRATION_RESULT_LIFETIME_SECONDS, 60);

    echo '<section class="auth-shell auth-shell--result" aria-labelledby="registration-success-title">' . "\n";
    echo '<div class="auth-heading">' . "\n";
    echo '<span class="auth-eyebrow">You are all set</span>' . "\n";
    echo '<h1 class="page-title" id="registration-success-title">Registration successful</h1>' . "\n";
    echo '<p class="page-subtitle">Thank you, ' . html_escape($result['name']) . '. Your visitor pass has been created.</p>' . "\n";
    echo '</div>' . "\n";
    echo '<section class="auth-card auth-card--success" aria-label="Your visitor code">' . "\n";
    echo '<div class="success-mark" aria-hidden="true">✓</div>' . "\n";
    echo '<h2 class="section-title">Your visitor code</h2>' . "\n";
    echo '<p class="auth-card-description">Keep this code somewhere safe. You will need it to identify yourself on future visits.</p>' . "\n";
    echo '<div class="visitor-code-panel"><code class="visitor-code">' . html_escape($result['code']) . '</code></div>' . "\n";
    echo '<div class="alert-info auth-alert" role="status"><strong>Important:</strong> Save your code now. The database stores only a one-way hash, so this code cannot be recovered if you lose it.</div>' . "\n";
    echo '<p class="auth-expiry-note">This page keeps showing your code for ' . $minutes
        . ' minutes in this browser, including after a refresh. After that, it is removed from the page.</p>' . "\n";

    echo '<form class="auth-form auth-form--result" method="post" action="' . html_escape(REGISTER_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="clear">' . "\n";
    echo '<button class="button-secondary auth-submit" type="submit">I have saved my code — register another visitor</button>' . "\n";
    echo '</form>' . "\n";
    echo '<p class="auth-switch"><a href="identify.php">Continue to visitor identification</a></p>' . "\n";
    echo '</section>' . "\n";
    echo '</section>' . "\n";

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

send_security_headers(false, true);
session_start_secure();

$errors = [];
$nameValue = '';
$notice = null;
$nameExists = false;
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
                    $pdo = db_connection();
                    $nameExists = visitor_name_exists($pdo, $check['name']);
                    if ($nameExists && ($_POST['confirm_new_visitor'] ?? null) !== 'yes') {
                        $notice = 'A visitor record with this name already exists. If it is yours, identify yourself with your original visitor code. If you are a different person with the same name, confirm below to create a separate pass.';
                        $registered = false;
                    } else {
                        $registered = visitor_register($pdo, $check['name']);
                    }
                } catch (Throwable $e) {
                    // Details go to the server log only. The visitor code is never part of a message.
                    error_log(sprintf('Visitor registration failed: %s: %s', get_class($e), $e->getMessage()));
                    $registered = null;
                }

                if ($registered === false) {
                    // Show a duplicate-name warning; a name is not proof of identity.
                    $status = 200;
                } elseif ($registered === null) {
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
    render_form($errors, $nameValue, $notice, $status, $nameExists);
}
