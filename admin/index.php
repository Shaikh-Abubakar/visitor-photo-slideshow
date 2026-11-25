<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'layout.php';

admin_require_login();
send_security_headers(true, true);
header('Content-Type: text/html; charset=utf-8');

$pdo = db_connection();
$statistics = [
    'Registered visitors' => (int) $pdo->query('SELECT COUNT(*) FROM visitors')->fetchColumn(),
    'Uploaded photos' => (int) $pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
    'Awaiting review' => (int) $pdo->query("SELECT COUNT(*) FROM photos WHERE visibility = 'public' AND approval_status = 'pending'")->fetchColumn(),
    'Approved photos' => (int) $pdo->query("SELECT COUNT(*) FROM photos WHERE visibility = 'public' AND approval_status = 'approved' AND status = 'active'")->fetchColumn(),
    'Hidden / rejected' => (int) $pdo->query("SELECT COUNT(*) FROM photos WHERE visibility = 'public' AND (status = 'hidden' OR approval_status = 'rejected')")->fetchColumn(),
];

$recentVisitorsQuery = $pdo->query('SELECT name, created_at FROM visitors ORDER BY id DESC LIMIT 5');
$recentVisitors = $recentVisitorsQuery->fetchAll();
$recentPhotosQuery = $pdo->query(
    'SELECT ph.id, ph.uploaded_at, ph.status, ph.approval_status,
            v.name AS visitor_name, c.consent_version, c.withdrawn_at
     FROM photos ph
     INNER JOIN visitors v ON v.id = ph.visitor_id
     LEFT JOIN consents c ON c.id = ph.consent_id AND c.visitor_id = ph.visitor_id
     WHERE ph.visibility = \'public\'
     ORDER BY ph.id DESC LIMIT 6'
);
$recentPhotos = $recentPhotosQuery->fetchAll();

admin_render_start(
    'Dashboard',
    'dashboard',
    'A clear overview of visitor activity, photo submissions, and review status.'
);

echo '<section class="admin-stat-grid" aria-label="Application statistics">';
$statMeta = [
    'Registered visitors' => ['Visitors registered', 'stat-blue', '♙'],
    'Uploaded photos' => ['Photos submitted', 'stat-violet', '▧'],
    'Awaiting review' => ['Need your attention', 'stat-amber', '◷'],
    'Approved photos' => ['Ready for display', 'stat-green', '✓'],
    'Hidden / rejected' => ['Not displayed publicly', 'stat-rose', '−'],
];
foreach ($statistics as $label => $value) {
    [$description, $tone, $symbol] = $statMeta[$label];
    echo '<article class="admin-stat-card ' . $tone . '"><div class="admin-stat-top"><span>' . admin_e($label) . '</span><span class="admin-stat-icon" aria-hidden="true">' . $symbol . '</span></div>';
    echo '<strong class="admin-stat-value">' . $value . '</strong><p>' . admin_e($description) . '</p></article>';
}
echo '</section>';

echo '<section class="admin-content-grid">';
echo '<article class="admin-panel admin-recent-visitors"><div class="admin-panel-heading"><div><p class="admin-eyebrow">LATEST ACTIVITY</p><h2>Recent visitors</h2></div><a class="admin-text-link" href="visitors.php">View all <span aria-hidden="true">→</span></a></div>';
if ($recentVisitors !== []) {
    echo '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Visitor</th><th>Registered</th></tr></thead><tbody>';
    foreach ($recentVisitors as $visitor) {
        echo '<tr><td><span class="admin-table-primary">' . admin_e((string) $visitor['name']) . '</span></td><td>' . admin_e((string) $visitor['created_at']) . '</td></tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<div class="admin-empty-state"><strong>No visitors yet</strong><span>New visitor registrations will appear here.</span></div>';
}
echo '</article>';

echo '<article class="admin-panel admin-review-summary"><div class="admin-panel-heading"><div><p class="admin-eyebrow">MODERATION</p><h2>Recent photo uploads</h2></div><a class="admin-text-link" href="photos.php">Review photos <span aria-hidden="true">→</span></a></div>';
if ($recentPhotos !== []) {
    echo '<div class="admin-recent-photo-list">';
    foreach ($recentPhotos as $photo) {
        $hasConsent = (string) $photo['consent_version'] === SLIDESHOW_PUBLIC_CONSENT_VERSION && $photo['withdrawn_at'] === null;
        $approval = (string) $photo['approval_status'];
        echo '<article class="admin-recent-photo"><img src="photo.php?id=' . (int) $photo['id'] . '" alt="Photo submitted by ' . admin_e((string) $photo['visitor_name']) . '">';
        echo '<div class="admin-recent-photo-info"><strong>' . admin_e((string) $photo['visitor_name']) . '</strong><span>' . admin_e((string) $photo['uploaded_at']) . '</span>';
        echo '<div class="admin-badge-row"><span class="' . admin_status_class($approval) . '">' . admin_e(ucfirst($approval)) . '</span>';
        echo '<span class="' . admin_status_class($hasConsent ? 'valid' : ($photo['withdrawn_at'] !== null ? 'withdrawn' : 'invalid')) . '">' . ($hasConsent ? 'Consent valid' : ($photo['withdrawn_at'] !== null ? 'Withdrawn' : 'No valid consent')) . '</span></div></div></article>';
    }
    echo '</div>';
} else {
    echo '<div class="admin-empty-state"><strong>No photos submitted</strong><span>Photo submissions will appear here for review.</span></div>';
}
echo '</article></section>';

echo '<section class="admin-guidance-panel"><span class="admin-guidance-icon" aria-hidden="true">i</span><div><strong>Public slideshow eligibility</strong><p>Only photos explicitly marked Public are considered. A Public photo is displayed to everyone only when its consent is valid and not withdrawn, its status is active, and administrator approval is approved. Private photos remain owner-only.</p></div></section>';

admin_render_end();
