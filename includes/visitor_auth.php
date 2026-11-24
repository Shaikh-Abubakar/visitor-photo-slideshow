<?php
declare(strict_types=1);

/**
 * Visitor authentication helper (Step 4): identify a returning visitor, keep the identity in
 * the PHP session, end the session, and throttle repeated wrong codes.
 *
 * Load after includes/bootstrap.php and includes/security.php, and start the session first
 * (session_start_secure()):
 *
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';
 *   session_start_secure();
 *
 * Later (protected) visitor pages begin with:
 *
 *   $visitor = visitor_require_login();   // sends an unidentified visitor to identify.php and stops
 *
 * No HTML lives here. The visitor code is NEVER stored: not in the session, not in the database,
 * not in a log. It is hashed with visitor_code_hash() from includes/visitors.php (Step 3), which
 * is the one and only hashing rule, so a returning visitor produces exactly the stored hash.
 */

// Direct web requests to this file do nothing: it must be loaded by a page.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'visitors.php';

/** Session key holding the identified visitor: ['id' => int, 'name' => string, 'authenticated_at' => int]. */
const VISITOR_SESSION_KEY = 'visitor';

/** An identification is valid for this long (seconds), then the visitor must enter the code again. */
const VISITOR_SESSION_LIFETIME_SECONDS = 7200;

/** Wrong codes allowed per browser session inside one window before it is locked. */
const VISITOR_IDENTIFY_MAX_FAILURES_PER_SESSION = 5;

/**
 * Wrong codes allowed per client address inside one window. Higher than the session limit because
 * several people (for example a college computer lab) can share one address.
 */
const VISITOR_IDENTIFY_MAX_FAILURES_PER_CLIENT = 30;

/** Length of the counting window and of the lock (seconds). */
const VISITOR_IDENTIFY_WINDOW_SECONDS = 900;

// ---------------------------------------------------------------------------
// Looking a visitor up by code
// ---------------------------------------------------------------------------

/**
 * Is this text shaped like a visitor code from Step 3? After normalisation (capitals, no hyphens
 * or white space) a code is exactly 32 characters from A-Z and 2-7 (20 bytes in Base32).
 */
function visitor_code_is_well_formed(string $code): bool
{
    if (strlen($code) > 200) {
        return false; // refuse absurd input before running any pattern on it
    }
    return preg_match('/^[A-Z2-7]{32}$/', visitor_code_normalize($code)) === 1;
}

/**
 * Finds the visitor that owns a code, or null when there is none.
 *
 * The submitted code is hashed in PHP with visitor_code_hash() (SHA-256 of the normalised code)
 * and only the hash is sent to the database, in a prepared statement. Nothing in the database is
 * compared in unhashed form. A malformed code and an unknown code give the same answer: null.
 *
 * @return null|array{id: int, name: string}
 */
function visitor_find_by_code(PDO $pdo, string $code): ?array
{
    if (!visitor_code_is_well_formed($code)) {
        return null;
    }

    $statement = $pdo->prepare('SELECT id, name FROM visitors WHERE visitor_code_hash = ? LIMIT 1');
    $statement->execute([visitor_code_hash($code)]);
    $row = $statement->fetch();

    if (!is_array($row)) {
        return null;
    }
    return ['id' => (int) $row['id'], 'name' => (string) $row['name']];
}

// ---------------------------------------------------------------------------
// The visitor session
// ---------------------------------------------------------------------------

/** All functions below read or write $_SESSION, so the session must be running. */
function visitor_require_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new LogicException('The visitor session helpers need an active session (call session_start_secure() first).');
    }
}

/**
 * Marks a visitor as identified. Call it only after the code has been verified.
 *
 * The session id is replaced (session_regenerate_id) so an id an attacker may have planted before
 * the identification is worthless afterwards (session fixation), and the old session file is
 * deleted. The session keeps only the visitor's database id, name and the time; never the code
 * and never the hash. The CSRF token is replaced as well.
 */
function visitor_login(int $visitorId, string $name): void
{
    visitor_require_session();

    session_regenerate_id(true);

    $_SESSION[VISITOR_SESSION_KEY] = [
        'id'               => $visitorId,
        'name'             => $name,
        'authenticated_at' => time(),
    ];
    // A counter of wrong codes belongs to the previous, unidentified state.
    unset($_SESSION['identify_throttle']);
    csrf_rotate();
}

/**
 * The identified visitor of this session, or null. Anything malformed or older than
 * VISITOR_SESSION_LIFETIME_SECONDS counts as "not identified" and is removed.
 *
 * @return null|array{id: int, name: string}
 */
function visitor_current(): ?array
{
    visitor_require_session();

    $visitor = $_SESSION[VISITOR_SESSION_KEY] ?? null;
    if (
        !is_array($visitor)
        || !is_int($visitor['id'] ?? null)
        || $visitor['id'] < 1
        || !is_string($visitor['name'] ?? null)
        || !is_int($visitor['authenticated_at'] ?? null)
        || time() - $visitor['authenticated_at'] > VISITOR_SESSION_LIFETIME_SECONDS
    ) {
        unset($_SESSION[VISITOR_SESSION_KEY]);
        return null;
    }
    return ['id' => $visitor['id'], 'name' => $visitor['name']];
}

/** Is a visitor identified in this session? */
function visitor_is_authenticated(): bool
{
    return visitor_current() !== null;
}

/** Sends the browser to another address with a GET request (303) and stops. */
function visitor_auth_redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

/**
 * Guard for protected visitor pages: returns the identified visitor, or sends an unidentified
 * visitor to the identification page and stops the script. Call it before any output.
 *
 * @return array{id: int, name: string}
 */
function visitor_require_login(string $identifyPage = 'identify.php'): array
{
    $visitor = visitor_current();
    if ($visitor === null) {
        visitor_auth_redirect($identifyPage);
    }
    return $visitor;
}

/**
 * Ends the visitor session: every value in the session is removed (the identity, the CSRF token,
 * a pending registration result with its code) and the session id is replaced, which deletes the
 * old session file. The browser keeps a fresh, empty session.
 */
function visitor_logout(): void
{
    visitor_require_session();

    $_SESSION = [];
    csrf_rotate();
    session_regenerate_id(true);
    $params = session_get_cookie_params();
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}

// ---------------------------------------------------------------------------
// Throttling wrong codes (no database table)
// ---------------------------------------------------------------------------
//
// Two counters, both "N wrong codes in a window of 15 minutes":
//   1. in the PHP session (limit 5)   - stops a person who keeps typing wrong codes;
//   2. in a small file per client address in the system temp folder (limit 30)
//      - still counts when the attacker throws the session cookie away between attempts.
// Reaching either limit locks identification until the window ends. While locked, the code is not
// even looked up. A successful identification does not reset the address counter (otherwise an
// attacker with one valid code could reset it at will); it simply expires with its window.

/** Seconds the session is still locked for, or 0. */
function visitor_throttle_session_wait(): int
{
    $state = $_SESSION['identify_throttle'] ?? null;
    if (!is_array($state) || !is_int($state['count'] ?? null) || !is_int($state['start'] ?? null)) {
        return 0;
    }

    $remaining = $state['start'] + VISITOR_IDENTIFY_WINDOW_SECONDS - time();
    if ($remaining <= 0) {
        unset($_SESSION['identify_throttle']);
        return 0;
    }
    return $state['count'] >= VISITOR_IDENTIFY_MAX_FAILURES_PER_SESSION ? $remaining : 0;
}

/** Counts one wrong code in the session. */
function visitor_throttle_session_fail(): void
{
    $state = $_SESSION['identify_throttle'] ?? null;
    if (
        !is_array($state)
        || !is_int($state['count'] ?? null)
        || !is_int($state['start'] ?? null)
        || time() - $state['start'] >= VISITOR_IDENTIFY_WINDOW_SECONDS
    ) {
        $state = ['count' => 0, 'start' => time()];
    }
    $state['count']++;
    $_SESSION['identify_throttle'] = $state;
}

/** Folder for the per-address counter files (system temp folder: works on Windows and Ubuntu). */
function visitor_throttle_client_directory(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vps_identify_throttle';
}

/**
 * Path of the counter file of the current client address, or null when the folder cannot be used.
 * Only REMOTE_ADDR is used (headers such as X-Forwarded-For can be forged by the visitor). The file
 * name is a hash of the address, so no address appears in a file name.
 */
function visitor_throttle_client_file(): ?string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($address) || filter_var($address, FILTER_VALIDATE_IP) === false) {
        $address = 'unknown';
    }

    $directory = visitor_throttle_client_directory();
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('Visitor identification throttle: the counter folder in the temp directory could not be created.');
        return null;
    }
    return $directory . DIRECTORY_SEPARATOR . hash('sha256', $address) . '.txt';
}

/**
 * Reads (and, when $recordFailure is true, increases) the wrong-code counter of the current client
 * address, under a file lock. The file holds "count start" (two whole numbers).
 * If the file cannot be used the function answers "no failures": the session counter still applies.
 *
 * @return array{count: int, start: int}
 */
function visitor_throttle_client_state(bool $recordFailure): array
{
    $none = ['count' => 0, 'start' => 0];

    $file = visitor_throttle_client_file();
    if ($file === null) {
        return $none;
    }
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        error_log('Visitor identification throttle: a counter file could not be opened.');
        return $none;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return $none;
        }

        $parts = explode(' ', trim((string) stream_get_contents($handle)));
        $count = 0;
        $start = 0;
        if (count($parts) === 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])) {
            $count = (int) $parts[0];
            $start = (int) $parts[1];
        }

        $now = time();
        if ($start === 0 || $now - $start >= VISITOR_IDENTIFY_WINDOW_SECONDS) {
            $count = 0;
            $start = $now;
        }

        if ($recordFailure) {
            $count++;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $count . ' ' . $start);
            fflush($handle);
        }

        flock($handle, LOCK_UN);
        return ['count' => $count, 'start' => $start];
    } finally {
        fclose($handle);
    }
}

/** Removes counter files whose window is long over (called now and then, so the folder stays small). */
function visitor_throttle_client_cleanup(): void
{
    $files = glob(visitor_throttle_client_directory() . DIRECTORY_SEPARATOR . '*.txt');
    if ($files === false) {
        return;
    }
    $limit = time() - 2 * VISITOR_IDENTIFY_WINDOW_SECONDS;
    foreach ($files as $file) {
        $modified = @filemtime($file);
        if ($modified !== false && $modified < $limit) {
            @unlink($file);
        }
    }
}

/** Seconds identification is locked for (session or client address), or 0 when it is allowed. */
function visitor_throttle_wait_seconds(): int
{
    visitor_require_session();

    $wait = visitor_throttle_session_wait();

    $client = visitor_throttle_client_state(false);
    if ($client['count'] >= VISITOR_IDENTIFY_MAX_FAILURES_PER_CLIENT) {
        $wait = max($wait, $client['start'] + VISITOR_IDENTIFY_WINDOW_SECONDS - time());
    }
    return max(0, $wait);
}

/** Counts one wrong code against the session and against the client address. */
function visitor_throttle_record_failure(): void
{
    visitor_require_session();

    visitor_throttle_session_fail();
    visitor_throttle_client_state(true);

    if (random_int(1, 50) === 1) {
        visitor_throttle_client_cleanup();
    }
}
