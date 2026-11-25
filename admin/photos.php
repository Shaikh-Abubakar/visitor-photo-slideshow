<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'layout.php';

admin_require_login();
send_security_headers(true, true);
header('Content-Type: text/html; charset=utf-8');

$pdo = db_connection();
$message = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_csrf_check();
    $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!$photoId || !in_array($action, ['approve', 'hide'], true)) {
        http_response_code(400);
        exit('Invalid photo action.');
    }

    if ($action === 'approve') {
        if (!admin_public_consent($pdo, (int) $photoId)) {
            $message = 'This photo does not have valid, active public-display consent and cannot be approved.';
            $messageIsError = true;
        } else {
            $update = $pdo->prepare("UPDATE photos SET status = 'active', approval_status = 'approved' WHERE id = ? AND visibility = 'public'");
            $update->execute([(int) $photoId]);
            $message = 'Photo approved. It still requires valid visitor consent to appear in the public slideshow.';
        }
    } else {
        $update = $pdo->prepare("UPDATE photos SET status = 'hidden', approval_status = 'rejected' WHERE id = ? AND visibility = 'public'");
        $update->execute([(int) $photoId]);
        $message = 'Photo hidden and rejected. It will not appear in the public slideshow.';
    }
}

$visitorId = filter_var($_GET['visitor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$sql =
    'SELECT ph.id, ph.original_filename, ph.uploaded_at, ph.status, ph.approval_status,
            v.name AS visitor_name, c.consent_version, c.withdrawn_at
     FROM photos ph
     INNER JOIN visitors v ON v.id = ph.visitor_id
     LEFT JOIN consents c ON c.id = ph.consent_id AND c.visitor_id = ph.visitor_id
     WHERE ph.visibility = \'public\'' .
    ($visitorId ? ' AND ph.visitor_id = :visitor_id' : '') .
    ' ORDER BY ph.id DESC LIMIT 200';
$query = $pdo->prepare($sql);
if ($visitorId) {
    $query->bindValue(':visitor_id', $visitorId, PDO::PARAM_INT);
}
$query->execute();
$photos = $query->fetchAll();

admin_render_start(
    'Photo review',
    'photos',
    'Review only Public submissions. Private photos are owner-only and are not placed in the public review queue.'
);

if ($message !== '') {
    echo '<div class="admin-alert ' . ($messageIsError ? 'admin-alert-error' : 'admin-alert-success') . '" role="' . ($messageIsError ? 'alert' : 'status') . '">' . admin_e($message) . '</div>';
}
echo '<section class="admin-review-toolbar"><div><strong>Latest public photo submissions</strong><span>' . count($photos) . ' photo(s) shown · Maximum 200</span></div>';
if ($visitorId) {
    echo '<a class="button button-secondary" href="photos.php">Show all photos</a>';
}
echo '</section>';

if ($photos !== []) {
    echo '<section class="admin-photo-grid" aria-label="Photo submissions">';
    foreach ($photos as $photo) {
        $photoId = (int) $photo['id'];
        $hasConsent = (string) $photo['consent_version'] === SLIDESHOW_PUBLIC_CONSENT_VERSION && $photo['withdrawn_at'] === null;
        $consentLabel = $hasConsent ? 'Valid public consent' : ($photo['withdrawn_at'] !== null ? 'Consent withdrawn' : 'No valid public consent');
        $consentStatus = $hasConsent ? 'valid' : ($photo['withdrawn_at'] !== null ? 'withdrawn' : 'invalid');
        $approval = (string) $photo['approval_status'];
        $status = (string) $photo['status'];

        echo '<article class="admin-photo-card">';
        echo '<div class="admin-photo-preview"><img src="photo.php?id=' . $photoId . '" alt="Private review preview of photo submitted by ' . admin_e((string) $photo['visitor_name']) . '" loading="lazy"></div>';
        echo '<div class="admin-photo-card-body"><div class="admin-photo-card-heading"><div><p class="admin-eyebrow">PHOTO #' . $photoId . '</p><h2>' . admin_e((string) $photo['visitor_name']) . '</h2></div>';
        echo '<span class="' . admin_status_class($approval) . '">' . admin_e(ucfirst($approval)) . '</span></div>';
        echo '<p class="admin-photo-filename" title="' . admin_e((string) $photo['original_filename']) . '">' . admin_e((string) $photo['original_filename']) . '</p>';
        echo '<p class="admin-photo-date">Uploaded ' . admin_e((string) $photo['uploaded_at']) . '</p>';
        echo '<div class="admin-photo-statuses"><div><span>Visitor consent</span><strong class="' . admin_status_class($consentStatus) . '">' . admin_e($consentLabel) . '</strong></div>';
        echo '<div><span>Photo visibility</span><strong class="' . admin_status_class($status) . '">' . admin_e(ucfirst($status)) . '</strong></div></div>';
        echo '<form method="post" action="photos.php' . ($visitorId ? '?visitor=' . $visitorId : '') . '" class="admin-photo-actions" aria-label="Review actions for photo ' . $photoId . '">';
        echo '<input type="hidden" name="csrf_token" value="' . admin_e(csrf_token()) . '"><input type="hidden" name="photo_id" value="' . $photoId . '">';
        echo '<button type="submit" class="admin-action-approve" name="action" value="approve"' . (!$hasConsent ? ' disabled title="Valid active public-display consent is required"' : '') . '>✓ Approve</button>';
        echo '<button type="submit" class="admin-action-reject" name="action" value="hide">Hide / reject</button>';
        echo '</form></div></article>';
    }
    echo '</section>';
} else {
    echo '<div class="admin-empty-state admin-photo-empty"><span class="admin-empty-icon" aria-hidden="true">▧</span><strong>No photo submissions found</strong><span>New photos marked Public will appear here for review. Private photos never enter this public queue.</span></div>';
}

admin_render_end();
