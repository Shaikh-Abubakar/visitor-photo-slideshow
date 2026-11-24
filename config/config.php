<?php
declare(strict_types=1);

/**
 * Configuration loader (Step 2).
 *
 * Every setting comes from, in this order of priority:
 *   1. a real environment variable (for example set in Apache or in the shell),
 *   2. the optional local file ".env" (see .env.example),
 *   3. the default written in this file.
 * An empty value counts as "not set", so the next source is used.
 *
 * Usage (after includes/bootstrap.php has been loaded):
 *   app_config('DB_HOST')   the validated value of one setting
 *   app_path('storage')     a filesystem path inside the project
 *   app_url('admin/')       a web address built from APP_URL
 *
 * Filesystem paths and web addresses are kept apart on purpose: a path is
 * never used in a URL and a URL is never used to open a file.
 *
 * This file only defines functions. It reads nothing until app_config() is called.
 */

// Direct web requests to this file do nothing: it must be loaded by includes/bootstrap.php.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/**
 * A setting is missing or invalid. Messages name the setting but NEVER contain
 * its value, so a password cannot leak through an error message.
 */
final class AppConfigException extends RuntimeException
{
}

/**
 * Absolute path of something inside the project folder.
 * Give the part below the project folder with "/" separators, for example
 * app_path('storage/uploads'). The result uses the separator of the current
 * operating system, so it works on Windows and Ubuntu.
 */
function app_path(string $relative = ''): string
{
    $relative = trim(str_replace('\\', '/', $relative), '/');
    if ($relative === '') {
        return APP_ROOT;
    }
    return APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

/** Web address built from APP_URL (which has no trailing slash). */
function app_url(string $path = ''): string
{
    $base = (string) app_config('APP_URL');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

/** The validated value of one setting. Unknown names are a programming error. */
function app_config(string $name): string|int|bool
{
    $values = config_load()['values'];
    if (!array_key_exists($name, $values)) {
        throw new InvalidArgumentException('Unknown configuration setting: ' . $name);
    }
    return $values[$name];
}

/** Where each setting came from: 'environment', '.env file' or 'default'. Never contains values. */
function app_config_sources(): array
{
    return config_load()['sources'];
}

/** Path of the .env file that was read, or null when there is none. */
function app_config_file(): ?string
{
    return config_load()['file'];
}

// ---------------------------------------------------------------------------
// Everything below is internal: use app_config() and the helpers above.
// ---------------------------------------------------------------------------

/**
 * Is $path an absolute path on the operating system whose separator is $separator?
 * The separator is a parameter only so that both rules can be checked on one machine.
 */
function config_is_absolute_path(string $path, string $separator = DIRECTORY_SEPARATOR): bool
{
    if ($separator === '\\') {
        // Windows: "C:\folder", "C:/folder" or a network path "\\server\share".
        $isDrivePath = strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
        $isNetworkPath = strncmp($path, '\\\\', 2) === 0;
        return $isDrivePath || $isNetworkPath;
    }
    // Ubuntu and other Unix-like systems: must start with "/".
    return $path !== '' && $path[0] === '/';
}

/** Uses the separator of the operating system and removes a trailing separator (but keeps a root such as "/" or "C:\"). */
function config_normalize_path(string $path, string $separator = DIRECTORY_SEPARATOR): string
{
    if ($separator === '\\') {
        $path = str_replace('/', '\\', $path);
    }
    $trimmed = rtrim($path, $separator);
    if ($trimmed === '' || preg_match('/^[A-Za-z]:$/', $trimmed) === 1) {
        return $path;
    }
    return $trimmed;
}

/** "true"/"false" style text to a bool, or null when it is not recognised. */
function config_parse_bool(string $value): ?bool
{
    return match (strtolower($value)) {
        '1', 'true', 'yes', 'on' => true,
        '0', 'false', 'no', 'off' => false,
        default => null,
    };
}

/** Whole number inside a range, or null. */
function config_parse_int(string $value, int $min, int $max): ?int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    return $number === false ? null : $number;
}

function config_error(string $name, string $problem): AppConfigException
{
    return new AppConfigException($name . ' ' . $problem);
}

/** A real environment variable, or null when it is not set. */
function config_environment_value(string $name): ?string
{
    $value = getenv($name);
    if (is_string($value)) {
        return $value;
    }
    // Some setups (for example Apache SetEnv) expose variables only here.
    if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
        return $_SERVER[$name];
    }
    if (isset($_ENV[$name]) && is_string($_ENV[$name])) {
        return $_ENV[$name];
    }
    return null;
}

/**
 * Which .env file to read: the absolute path in the real environment variable
 * APP_ENV_FILE (for a file kept outside the web folder), otherwise ".env" in
 * the project folder, otherwise none.
 */
function config_find_env_file(): ?string
{
    $custom = config_environment_value('APP_ENV_FILE');
    if ($custom !== null && $custom !== '') {
        if (!config_is_absolute_path($custom)) {
            throw config_error('APP_ENV_FILE', 'must be an absolute path.');
        }
        if (!is_file($custom) || !is_readable($custom)) {
            throw config_error('APP_ENV_FILE', 'points to a file that does not exist or cannot be read.');
        }
        return $custom;
    }

    $default = app_path('.env');
    return is_file($default) ? $default : null;
}

/**
 * Reads a .env file into an array of NAME => value. Rules:
 *   - one NAME=value per line; NAME is upper case letters, digits and "_";
 *   - blank lines and lines starting with "#" are ignored;
 *   - no inline comments: everything after "=" is the value;
 *   - a value wrapped in matching "double" or 'single' quotes loses the quotes;
 *   - there are no escape sequences, so backslashes in Windows paths stay as typed.
 * Error messages give the line number only, never the line itself.
 */
function config_parse_env_file(string $file): array
{
    $contents = @file_get_contents($file);
    if ($contents === false) {
        throw new AppConfigException('The configuration file exists but cannot be read. Check its file permissions.');
    }
    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3); // UTF-8 byte order mark added by some Windows editors
    }

    $values = [];
    foreach (preg_split('/\r\n|\r|\n/', $contents) as $index => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $position = strpos($line, '=');
        $name = $position === false ? '' : rtrim(substr($line, 0, $position));
        if ($position === false || preg_match('/^[A-Z][A-Z0-9_]*$/', $name) !== 1) {
            throw new AppConfigException(sprintf(
                'Line %d of the configuration file is not in NAME=value form (NAME in capital letters).',
                $index + 1
            ));
        }

        $value = ltrim(substr($line, $position + 1));
        $length = strlen($value);
        if ($length >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$length - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $values[$name] = $value;
    }
    return $values;
}

/** Loads and validates every setting once per request. Failures are not cached. */
function config_load(): array
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    $file = config_find_env_file();
    $fileValues = $file !== null ? config_parse_env_file($file) : [];

    $values = [];
    $sources = [];

    // Reads one raw setting: environment, then .env file, then the default.
    $read = static function (string $name, ?string $default) use ($fileValues, &$sources): ?string {
        $raw = config_environment_value($name);
        $source = 'environment';
        if ($raw === null) {
            $raw = $fileValues[$name] ?? null;
            $source = '.env file';
        }
        if ($raw === null || $raw === '') {
            $sources[$name] = 'default';
            return $default;
        }
        $sources[$name] = $source;
        return $raw;
    };

    // --- Application ---------------------------------------------------
    $appName = $read('APP_NAME', 'Visitor Photo Slideshow');
    if (preg_match('/^[^\x00-\x1F\x7F]{1,100}$/u', $appName) !== 1) {
        throw config_error('APP_NAME', 'must be 1 to 100 characters of valid text without control characters.');
    }
    $values['APP_NAME'] = $appName;

    $appEnv = strtolower($read('APP_ENV', 'production'));
    if (!in_array($appEnv, ['development', 'testing', 'production'], true)) {
        throw config_error('APP_ENV', 'must be development, testing or production.');
    }
    $values['APP_ENV'] = $appEnv;

    $appUrl = rtrim($read('APP_URL', 'http://localhost'), '/');
    $urlParts = parse_url($appUrl);
    $urlIsValid = filter_var($appUrl, FILTER_VALIDATE_URL) !== false
        && is_array($urlParts)
        && in_array($urlParts['scheme'] ?? '', ['http', 'https'], true)
        && !isset($urlParts['query'])
        && !isset($urlParts['fragment'])
        && !isset($urlParts['user'])
        && !isset($urlParts['pass']);
    if (!$urlIsValid) {
        throw config_error('APP_URL', 'must be a full web address such as http://localhost/visitor-photo-slideshow (http or https, no query string).');
    }
    $values['APP_URL'] = $appUrl;

    $appDebug = config_parse_bool($read('APP_DEBUG', 'false'));
    if ($appDebug === null) {
        throw config_error('APP_DEBUG', 'must be true or false.');
    }
    if ($appDebug && $appEnv === 'production') {
        throw config_error('APP_DEBUG', 'must be false when APP_ENV is production (debug output would reveal internal details).');
    }
    $values['APP_DEBUG'] = $appDebug;

    // --- Database ------------------------------------------------------
    // Letters, digits and a few symbols only: the values go into the connection string.
    $dbHost = $read('DB_HOST', '127.0.0.1');
    if (preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $dbHost) !== 1) {
        throw config_error('DB_HOST', 'must be a host name or IP address (letters, digits, ".", "-", "_" and ":" only).');
    }
    $values['DB_HOST'] = $dbHost;

    $dbPort = config_parse_int($read('DB_PORT', '3306'), 1, 65535);
    if ($dbPort === null) {
        throw config_error('DB_PORT', 'must be a whole number from 1 to 65535.');
    }
    $values['DB_PORT'] = $dbPort;

    $dbName = $read('DB_NAME', 'visitor_photo_slideshow');
    if (preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $dbName) !== 1) {
        throw config_error('DB_NAME', 'must be 1 to 64 letters, digits, "_", "$" or "-".');
    }
    $values['DB_NAME'] = $dbName;

    $dbUser = $read('DB_USER', null);
    if ($dbUser === null) {
        throw config_error('DB_USER', 'is not set. Set it in the environment or in the .env file.');
    }
    if (preg_match('/^[^\x00-\x1F\x7F]{1,80}$/u', $dbUser) !== 1) {
        throw config_error('DB_USER', 'must be 1 to 80 characters without control characters.');
    }
    $values['DB_USER'] = $dbUser;

    // The password is never checked or shown. An empty password is allowed.
    $values['DB_PASSWORD'] = $read('DB_PASSWORD', '');

    // --- Uploads -------------------------------------------------------
    $storagePath = $read('UPLOAD_STORAGE_PATH', null);
    if ($storagePath === null) {
        $storagePath = app_path('storage/uploads');
    } else {
        if (preg_match('/[\x00-\x1F\x7F]/', $storagePath) === 1 || !config_is_absolute_path($storagePath)) {
            throw config_error(
                'UPLOAD_STORAGE_PATH',
                'must be an absolute path for this operating system, for example D:\\private\\uploads (Windows) or /srv/private/uploads (Ubuntu). Leave it empty to use storage/uploads inside the project.'
            );
        }
        $storagePath = config_normalize_path($storagePath);
    }
    $values['UPLOAD_STORAGE_PATH'] = $storagePath;

    // The upper limit is the largest value the INT UNSIGNED column photos.file_size can hold.
    $maxFileSize = config_parse_int($read('UPLOAD_MAX_FILE_SIZE_BYTES', '5242880'), 1, 4294967295);
    if ($maxFileSize === null) {
        throw config_error('UPLOAD_MAX_FILE_SIZE_BYTES', 'must be a whole number of bytes from 1 to 4294967295.');
    }
    $values['UPLOAD_MAX_FILE_SIZE_BYTES'] = $maxFileSize;

    $maxFiles = config_parse_int($read('UPLOAD_MAX_FILES', '10'), 1, PHP_INT_MAX);
    if ($maxFiles === null) {
        throw config_error('UPLOAD_MAX_FILES', 'must be a whole number of 1 or more.');
    }
    $values['UPLOAD_MAX_FILES'] = $maxFiles;

    // Step 5: the allowed image types, as a comma-separated list. Only the three types the
    // project supports can be named; the list can only narrow them (for example "image/jpeg,image/png").
    $mimeList = [];
    foreach (explode(',', strtolower($read('UPLOAD_ALLOWED_MIME_TYPES', 'image/jpeg,image/png,image/webp'))) as $mimeType) {
        $mimeType = trim($mimeType);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw config_error('UPLOAD_ALLOWED_MIME_TYPES', 'must be a comma-separated list made only of image/jpeg, image/png and image/webp.');
        }
        $mimeList[$mimeType] = $mimeType;
    }
    $values['UPLOAD_ALLOWED_MIME_TYPES'] = implode(',', $mimeList);

    $loaded = ['values' => $values, 'sources' => $sources, 'file' => $file];
    return $loaded;
}
