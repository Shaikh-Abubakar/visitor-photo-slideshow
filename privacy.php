<?php
declare(strict_types=1);

/**
 * Visitor privacy controls (Step 9).
 * A visitor may withdraw public-display consent for all of their upload sessions.
 * Historical consent rows are retained; withdrawn_at records the withdrawal time.
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';

session_start_secure();
send_security_headers();
header('Content-Type: text/html; charset=utf-8');
$visitor = visitor_require_login('identify.php');
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $message = 'The form could not be verified. Reload this page and try again.';
    } else {
        try {
            $pdo = db_connection();
            $pdo->beginTransaction();
            $q = $pdo->prepare(
                'UPDATE consents SET withdrawn_at = UTC_TIMESTAMP() '
                . 'WHERE visitor_id = :visitor AND withdrawn_at IS NULL'
            );
            $q->execute([':visitor' => $visitor['id']]);
            $changed = $q->rowCount();
            $pdo->commit();
            csrf_rotate();
            $message = $changed > 0
                ? 'Your public-display consent has been withdrawn. Your photographs will no longer appear in the public slideshow. Historical consent records have been retained.'
                : 'There is no active public-display consent to withdraw.';
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Visitor consent withdrawal failed: ' . get_class($e) . ': ' . $e->getMessage());
            http_response_code(500);
            $message = 'Your request could not be completed. Please try again later.';
        }
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacy controls</title></head><body>';
echo '<h1>Privacy controls</h1>';
echo '<p>Signed in as ' . html_escape($visitor['name']) . '.</p>';
if ($message !== '') {
    echo '<p role="status">' . html_escape($message) . '</p>';
}
echo '<p>Withdrawing consent removes all photographs from your public slideshow immediately. Your private gallery remains available to you. The consent history is retained with a withdrawal timestamp.</p>';
echo '<form method="post"><input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '"><button type="submit">Withdraw public-display consent</button></form>';
echo '<p><a href="gallery.php">Return to my gallery</a></p>';
echo '</body></html>';
