<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'layout.php';

admin_require_login();
send_security_headers(false, true);
header('Content-Type: text/html; charset=utf-8');

$pdo = db_connection();
$term = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$limit = 20;
$where = $term === '' ? '' : ' WHERE v.name LIKE :term';
$countQuery = $pdo->prepare('SELECT COUNT(*) FROM visitors v' . $where);
if ($term !== '') {
    $countQuery->execute([':term' => '%' . $term . '%']);
} else {
    $countQuery->execute();
}
$total = (int) $countQuery->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));
$page = min($page, $totalPages);

$listQuery = $pdo->prepare(
    'SELECT v.id, v.name, v.created_at, COUNT(ph.id) AS photo_count
     FROM visitors v
     LEFT JOIN photos ph ON ph.visitor_id = v.id' .
    ($term !== '' ? ' WHERE v.name LIKE :term' : '') .
    ' GROUP BY v.id, v.name, v.created_at
     ORDER BY v.id DESC LIMIT :limit OFFSET :offset'
);
if ($term !== '') {
    $listQuery->bindValue(':term', '%' . $term . '%', PDO::PARAM_STR);
}
$listQuery->bindValue(':limit', $limit, PDO::PARAM_INT);
$listQuery->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
$listQuery->execute();
$visitors = $listQuery->fetchAll();

admin_render_start(
    'Visitors',
    'visitors',
    'Find registered visitors and open the photos associated with each visitor.'
);

echo '<section class="admin-panel admin-list-panel"><div class="admin-panel-heading"><div><p class="admin-eyebrow">VISITOR DIRECTORY</p><h2>Registered visitors</h2></div><span class="admin-count-chip">' . $total . ' total</span></div>';
echo '<form method="get" action="visitors.php" class="admin-search-form"><label for="visitor-search">Search by visitor name</label>';
echo '<div class="admin-search-controls"><input id="visitor-search" type="search" name="q" maxlength="100" value="' . admin_e($term) . '" placeholder="Enter a visitor name"><button type="submit">Search visitors</button>';
if ($term !== '') {
    echo '<a class="button button-secondary admin-clear-search" href="visitors.php">Clear</a>';
}
echo '</div></form>';
echo '<p class="admin-results-caption">Showing page ' . $page . ' of ' . $totalPages . '.</p>';

if ($visitors !== []) {
    echo '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Visitor name</th><th>Registration date</th><th>Uploaded photos</th><th>Photo records</th></tr></thead><tbody>';
    foreach ($visitors as $visitor) {
        echo '<tr><td><span class="admin-table-primary">' . admin_e((string) $visitor['name']) . '</span></td>';
        echo '<td>' . admin_e((string) $visitor['created_at']) . '</td>';
        echo '<td><span class="admin-number-chip">' . (int) $visitor['photo_count'] . '</span></td>';
        echo '<td><a class="admin-text-link" href="photos.php?visitor=' . (int) $visitor['id'] . '">View photos <span aria-hidden="true">→</span></a></td></tr>';
    }
    echo '</tbody></table></div>';
} else {
    echo '<div class="admin-empty-state"><span class="admin-empty-icon" aria-hidden="true">♙</span><strong>No visitors found</strong><span>' . ($term !== '' ? 'Try a different name or clear the search.' : 'Registered visitors will appear here.') . '</span></div>';
}

if ($totalPages > 1) {
    echo '<nav class="admin-pagination" aria-label="Visitor list pages">';
    for ($number = 1; $number <= $totalPages; $number++) {
        $url = '?q=' . rawurlencode($term) . '&amp;page=' . $number;
        echo '<a class="admin-page-link' . ($number === $page ? ' is-current' : '') . '" href="' . $url . '"' . ($number === $page ? ' aria-current="page"' : '') . '>' . $number . '</a>';
    }
    echo '</nav>';
}

echo '</section>';
admin_render_end();
