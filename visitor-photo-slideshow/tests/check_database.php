<?php
declare(strict_types=1);

/**
 * Database connection check (Step 2). Command line only:
 *
 *   php tests/check_database.php
 *
 * It loads the bootstrap, connects, runs SELECT 1 and checks that the five tables
 * from database/schema.sql exist. It only reads: it never inserts, updates or deletes.
 * It never prints the database user name, the password or any other secret.
 *
 * Exit status: 0 = every check passed, 1 = a check failed.
 */

// A web request (Apache, php -S) gets an empty 403 answer and nothing is run.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/** The tables created by database/schema.sql. */
const REQUIRED_TABLES = ['visitors', 'consents', 'photos', 'admins', 'settings'];

function report(string $status, string $message): void
{
    echo str_pad('[' . $status . ']', 7) . $message . PHP_EOL;
}

/** Prints the final result and stops. */
function finish(bool $passed, string $message): never
{
    echo PHP_EOL . 'RESULT: ' . ($passed ? 'PASS' : 'FAIL') . ' - ' . $message . PHP_EOL;
    exit($passed ? 0 : 1);
}

function hint_for(string $reason): string
{
    return match ($reason) {
        'driver_missing'   => 'Enable the PHP extension pdo_mysql (Windows: extension=pdo_mysql in php.ini; Ubuntu: sudo apt install php-mysql).',
        'unreachable'      => 'Is MySQL running? Check DB_HOST and DB_PORT. Use 127.0.0.1 rather than localhost.',
        'access_denied'    => 'Check DB_USER and DB_PASSWORD, that DB_NAME is spelled correctly and the database exists, and that this user was created for 127.0.0.1 with access to it (see README, "Initial database setup").',
        'unknown_database' => 'Create the database first, then check DB_NAME (see README, "Initial database setup").',
        default            => 'See the PHP error log for the technical details.',
    };
}

echo 'Visitor Photo Slideshow - database connection check' . PHP_EOL;
echo str_repeat('=', 51) . PHP_EOL . PHP_EOL;

// 1. Common bootstrap and configuration ------------------------------------
try {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
} catch (AppConfigException $e) {
    report('FAIL', 'Configuration problem: ' . $e->getMessage());
    finish(false, 'fix the problem described above (README, "Configuration").');
} catch (Throwable $e) {
    report('FAIL', 'The bootstrap could not be loaded (' . get_class($e) . '). See the PHP error log.');
    finish(false, 'the application could not start.');
}

report('PASS', 'Configuration loaded and validated.');
$configFile = app_config_file();
echo '       Configuration file: ' . ($configFile ?? 'none found (using environment variables and defaults only)') . PHP_EOL;
echo PHP_EOL . 'Settings (the database user name and password are never printed):' . PHP_EOL;

$hidden = ['DB_USER', 'DB_PASSWORD'];
$sources = app_config_sources();
foreach ($sources as $name => $source) {
    $value = app_config($name);
    if (in_array($name, $hidden, true)) {
        $shown = '(hidden)';
    } elseif (is_bool($value)) {
        $shown = $value ? 'true' : 'false';
    } else {
        $shown = (string) $value;
    }
    echo '  ' . str_pad($name, 28) . $shown . str_repeat(' ', max(2, 34 - strlen($shown))) . $source . PHP_EOL;
}
echo PHP_EOL;

// 2. Connection --------------------------------------------------------------
try {
    $pdo = db_connection();
} catch (DatabaseConnectionException $e) {
    report('FAIL', 'Could not connect: ' . $e->getMessage());
    echo '       Hint: ' . hint_for($e->reason()) . PHP_EOL;
    finish(false, 'no database connection.');
}
report('PASS', 'Connected to the database server (version ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . ').');

// 3. SELECT 1 ------------------------------------------------------------------
try {
    $row = $pdo->query('SELECT 1 AS ok')->fetch();
    if (!is_array($row) || (int) ($row['ok'] ?? 0) !== 1) {
        report('FAIL', 'SELECT 1 did not return 1.');
        finish(false, 'the database does not answer queries correctly.');
    }
} catch (PDOException $e) {
    report('FAIL', 'SELECT 1 failed (SQLSTATE ' . $e->getCode() . ').');
    finish(false, 'the database does not answer queries correctly.');
}
report('PASS', 'SELECT 1 returned 1.');

// 4. Required tables -------------------------------------------------------------
try {
    $existing = array_map('strtolower', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    report('FAIL', 'Could not list the tables (SQLSTATE ' . $e->getCode() . ').');
    finish(false, 'the tables could not be checked.');
}
$missing = array_diff(REQUIRED_TABLES, $existing);
if ($missing !== []) {
    report('FAIL', 'Missing table(s): ' . implode(', ', $missing) . '.');
    echo '       Hint: import database/schema.sql into an empty database (README, "Initial database setup").' . PHP_EOL;
    finish(false, 'the database is not set up.');
}
report('PASS', 'All ' . count(REQUIRED_TABLES) . ' required tables exist (' . implode(', ', REQUIRED_TABLES) . ').');

// 5. Upload folder (information only: it never changes the result) ---------------
$storage = (string) app_config('UPLOAD_STORAGE_PATH');
if (!is_dir($storage)) {
    report('WARN', 'The upload folder does not exist yet: ' . $storage);
} elseif (!is_writable($storage)) {
    report('WARN', 'The upload folder is not writable by the user running this script (the web server may use another account): ' . $storage);
} else {
    report('PASS', 'The upload folder exists and is writable by the user running this script.');
}

finish(true, 'configuration and database connection are working.');
