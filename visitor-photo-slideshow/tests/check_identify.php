<?php
declare(strict_types=1);

/**
 * Step 4 logic check. Command line only:
 *
 *   php tests/check_identify.php
 *
 * It exercises the lookup and throttle functions of includes/visitor_auth.php against the real
 * database. Everything it writes to the database happens inside ONE transaction that is rolled
 * back at the end, so no visitor row remains (only the AUTO_INCREMENT counter moves on, which is
 * harmless). It cannot test the web page, the session cookie or the browser: do those by hand
 * (README, "Returning visitor identification").
 *
 * Exit status: 0 = every check passed, 1 = a check failed.
 */

// A web request (Apache, php -S) gets an empty 403 answer and nothing is run.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo '[PASS] ' . $label . PHP_EOL;
    } else {
        $failed++;
        echo '[FAIL] ' . $label . PHP_EOL;
    }
}

function count_visitors(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM visitors')->fetchColumn();
}

echo 'Visitor Photo Slideshow - Step 4 logic check' . PHP_EOL . PHP_EOL;

try {
    $pdo = db_connection();
} catch (Throwable $e) {
    echo '[FAIL] Could not connect (' . get_class($e) . '). Run tests/check_database.php first.' . PHP_EOL;
    exit(1);
}

$before = count_visitors($pdo);
$pdo->beginTransaction();

try {
    // --- lookup ------------------------------------------------------------
    $registered = visitor_register($pdo, 'Check Visitor');
    $code = $registered['code'];

    $found = visitor_find_by_code($pdo, $code);
    check('exact code finds the registered visitor id and name', $found === ['id' => $registered['id'], 'name' => 'Check Visitor']);
    check('lower-case code finds the same visitor', visitor_find_by_code($pdo, strtolower($code)) === $found);
    check('code without hyphens finds the same visitor', visitor_find_by_code($pdo, str_replace('-', '', $code)) === $found);
    check('code with surrounding spaces finds the same visitor', visitor_find_by_code($pdo, '  ' . $code . ' ') === $found);

    check('another well-formed code finds nobody', visitor_find_by_code($pdo, visitor_code_generate()) === null);
    check('empty text finds nobody', visitor_find_by_code($pdo, '') === null);
    check('short text finds nobody', visitor_find_by_code($pdo, 'ABCD-2345') === null);
    check('code with an extra character finds nobody', visitor_find_by_code($pdo, $code . 'A') === null);
    check('SQL-looking text finds nobody', visitor_find_by_code($pdo, "' OR '1'='1") === null);
    check('very long text finds nobody', visitor_find_by_code($pdo, str_repeat('A', 5000)) === null);
    check('format check accepts a generated code', visitor_code_is_well_formed($code));

    // --- the code itself is not stored --------------------------------------
    $row = $pdo->query('SELECT * FROM visitors WHERE id = ' . (int) $registered['id'])->fetch();
    $everything = is_array($row) ? implode('|', array_map('strval', $row)) : '';
    check('the stored hash has 64 characters', is_array($row) && strlen((string) $row['visitor_code_hash']) === 64);
    check('the stored hash equals visitor_code_hash(code)', is_array($row) && $row['visitor_code_hash'] === visitor_code_hash($code));
    check('the code (with or without hyphens) appears in no column of the row',
        $row !== false
        && !str_contains($everything, $code)
        && !str_contains($everything, visitor_code_normalize($code)));

    // --- looking up creates nothing -----------------------------------------
    check('lookups created no extra visitor (only the test row exists)', count_visitors($pdo) === $before + 1);

    // --- throttle: session counter (no session needed, it only uses $_SESSION) ---
    $_SESSION = [];
    check('session throttle: not locked at the start', visitor_throttle_session_wait() === 0);
    for ($i = 1; $i < VISITOR_IDENTIFY_MAX_FAILURES_PER_SESSION; $i++) {
        visitor_throttle_session_fail();
    }
    check('session throttle: not locked just below the limit', visitor_throttle_session_wait() === 0);
    visitor_throttle_session_fail();
    check('session throttle: locked at the limit', visitor_throttle_session_wait() > 0);
    $_SESSION['identify_throttle']['start'] = time() - VISITOR_IDENTIFY_WINDOW_SECONDS - 1;
    check('session throttle: unlocked when the window is over', visitor_throttle_session_wait() === 0);

    // --- throttle: client-address counter (test address 203.0.113.250, reserved for documentation) ---
    $_SERVER['REMOTE_ADDR'] = '203.0.113.250';
    $counterFile = visitor_throttle_client_file();
    if ($counterFile !== null && is_file($counterFile)) {
        unlink($counterFile);
    }
    check('client throttle: counter starts at 0', visitor_throttle_client_state(false)['count'] === 0);
    for ($i = 0; $i < VISITOR_IDENTIFY_MAX_FAILURES_PER_CLIENT; $i++) {
        visitor_throttle_client_state(true);
    }
    check('client throttle: counter reaches the limit', visitor_throttle_client_state(false)['count'] === VISITOR_IDENTIFY_MAX_FAILURES_PER_CLIENT);
    if ($counterFile !== null && is_file($counterFile)) {
        unlink($counterFile);
    }
} finally {
    $pdo->rollBack();
}

check('after the rollback the visitor count is what it was before', count_visitors($pdo) === $before);

echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
