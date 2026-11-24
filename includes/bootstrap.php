<?php
declare(strict_types=1);

/**
 * Common application bootstrap (Step 2).
 *
 * Every page and script starts with:
 *
 *   require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
 *
 * (written from a file in a sub-folder such as admin/; from the project root use __DIR__ instead of dirname(__DIR__)).
 *
 * It does three separate jobs, each in its own file:
 *   1. configuration  -> config/config.php   (app_config(), app_path(), app_url())
 *   2. database       -> config/database.php (db_connection())
 *   3. initialisation -> this file           (error handling, time zone)
 *
 * It does not connect to the database: db_connection() does that when first called.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

error_reporting(E_ALL);
ini_set('log_errors', '1');
// Stack traces would include function arguments (such as connection details). Leave them out.
ini_set('zend.exception_ignore_args', '1');
// Matches the UTC time zone the database connection uses.
date_default_timezone_set('UTC');

// Until the configuration is known, never show errors on a web page.
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
}

require_once APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

/**
 * Last-resort handler for exceptions nobody caught, on web requests only.
 * Visitors get a short neutral message. Details go to the server log, and are
 * shown on the page only when APP_DEBUG is true.
 */
function bootstrap_handle_uncaught(Throwable $e): void
{
    error_log(sprintf('Uncaught %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    $debug = false;
    try {
        $debug = app_config('APP_DEBUG') === true;
    } catch (Throwable) {
        // The configuration itself is broken: keep the message neutral.
    }

    // Never return exception messages to a browser: they can contain SQL, paths or configuration details.
    // Developers can inspect the server-side PHP error log instead.
    echo 'Something went wrong. Please try again later.';
}

if (PHP_SAPI !== 'cli') {
    set_exception_handler('bootstrap_handle_uncaught');
}

// Load and check every setting now, so a configuration mistake shows up immediately
// and not halfway through a page. (On the command line the exception reaches the caller.)
app_config('APP_ENV');

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
}
