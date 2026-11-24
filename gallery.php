<?php
declare(strict_types=1);

/**
 * Visitor photo gallery (Step 6).
 *
 *   GET gallery.php            page 1 of the identified visitor's photographs
 *   GET gallery.php?page=N     page N (an invalid number means page 1; a number past the end shows the last page)
 *
 * An unidentified visitor is sent to identify.php and nothing below runs. The visitor whose
 * photographs are listed is the one in the server-side session: there is no visitor parameter, so
 * nothing in the address or in a form can show somebody else's photographs.
 *
 * The page lists only photographs the visitor may see (gallery_fetch_page(): own, status 'active',
 * matching consent record). Each image is loaded through photo.php, which repeats those checks.
 * No file path or stored file name is ever written into the page.
 *
 * This file builds all its HTML with PHP. There is no CSS and no JavaScript.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitor_auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';

/** Relative addresses: they work in a sub-folder (XAMPP) and at the site root (Ubuntu). */
const GALLERY_PAGE = 'gallery.php';
const GALLERY_PHOTO_ENDPOINT = 'photo.php';
const GALLERY_UPLOAD_PAGE = 'upload.php';
const GALLERY_IDENTIFY_PAGE = 'identify.php';

/** Width (in pixels) of a thumbnail: a plain HTML attribute, not CSS. The browser keeps the proportions. */
const GALLERY_THUMBNAIL_WIDTH = 160;

/** First part of every page. Sends the HTTP status and the start of the HTML. */
function gallery_page_start(string $title, int $status): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="en">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="utf-8">' . "\n";
    echo '<title>' . html_escape($title) . '</title>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";
    echo '<h1>' . html_escape((string) app_config('APP_NAME')) . '</h1>' . "\n";
}

function gallery_page_end(): void
{
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/** The links and the logout button shown above every gallery view. */
function gallery_render_menu(array $visitor): void
{
    echo '<p>You are identified as ' . html_escape($visitor['name']) . ' (visitor number '
        . html_escape((string) $visitor['id']) . ').</p>' . "\n";
    echo '<p><a href="' . html_escape(GALLERY_UPLOAD_PAGE) . '">Upload more photographs</a></p>' . "\n";

    // Logout is a POST with the CSRF token (a plain link could be triggered by another web site).
    // identify.php already ends the session for action=logout, so no logout code is repeated here.
    echo '<form method="post" action="' . html_escape(GALLERY_IDENTIFY_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="logout">' . "\n";
    echo '<p><button type="submit">End my session</button></p>' . "\n";
    echo '</form>' . "\n";
}

/** "2026-11-24 10:15 UTC" from a database DATETIME (stored in UTC). */
function gallery_format_date(string $databaseDate): string
{
    $time = strtotime($databaseDate . ' UTC');
    return $time === false ? $databaseDate : gmdate('Y-m-d H:i', $time) . ' UTC';
}

/** The words shown for a photos.status value (only 'active' is ever listed). */
function gallery_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Active',
        default  => $status,
    };
}

/** Page address for a page number. Page 1 has no parameter. */
function gallery_page_link(int $page): string
{
    return $page <= 1 ? GALLERY_PAGE : GALLERY_PAGE . '?page=' . $page;
}

/**
 * Previous / Next links with the position, as ordinary PHP-generated links.
 * At the first or last page the missing direction is plain text, not a link.
 */
function gallery_render_navigation(int $page, int $pages, int $total): void
{
    echo '<p>Page ' . html_escape((string) $page) . ' of ' . html_escape((string) $pages)
        . ' (' . html_escape((string) $total) . ' photograph' . ($total === 1 ? '' : 's') . ' in total)</p>' . "\n";

    if ($pages <= 1) {
        return;
    }

    $previous = $page > 1
        ? '<a href="' . html_escape(gallery_page_link($page - 1)) . '">Previous</a>'
        : 'Previous';
    $next = $page < $pages
        ? '<a href="' . html_escape(gallery_page_link($page + 1)) . '">Next</a>'
        : 'Next';
    echo '<p>' . $previous . ' | ' . $next . '</p>' . "\n";
}

/**
 * @param array{id: int, name: string} $visitor
 * @param list<array{id: int, original_filename: string, stored_filename: string, status: string, uploaded_at: string}> $photos
 */
function gallery_render(array $visitor, array $photos, int $page, int $pages, int $total, string $storageDirectory): void
{
    gallery_page_start('My photographs', 200);
    echo '<h2>My photographs</h2>' . "\n";
    gallery_render_menu($visitor);

    if ($total === 0) {
        echo '<p><strong>You have no photographs to show yet.</strong> '
            . 'Photographs you upload with your consent appear here.</p>' . "\n";
        echo '<p><a href="' . html_escape(GALLERY_UPLOAD_PAGE) . '">Upload your first photographs</a></p>' . "\n";
        gallery_page_end();
        return;
    }

    echo '<p>Only your own photographs are listed. Photographs that have been hidden are not shown.</p>' . "\n";
    gallery_render_navigation($page, $pages, $total);

    echo '<table border="1" cellpadding="6">' . "\n";
    echo '<tr><th>Photograph</th><th>File name</th><th>Uploaded</th><th>Status</th></tr>' . "\n";
    foreach ($photos as $photo) {
        $address = GALLERY_PHOTO_ENDPOINT . '?id=' . $photo['id'];
        // Is the file really there? Only the answer (yes/no) is used; the path is never shown.
        $fileIsThere = gallery_resolve_file($storageDirectory, $photo['stored_filename']) !== null;

        echo '<tr>';
        echo '<td>';
        if ($fileIsThere) {
            echo '<a href="' . html_escape($address) . '"><img src="' . html_escape($address) . '" alt="'
                . html_escape('Photograph ' . $photo['original_filename']) . '" width="' . GALLERY_THUMBNAIL_WIDTH
                . '" loading="lazy"></a>';
        } else {
            echo 'The image file is not available.';
        }
        echo '</td>';
        echo '<td>' . html_escape($photo['original_filename']) . '</td>';
        echo '<td>' . html_escape(gallery_format_date($photo['uploaded_at'])) . '</td>';
        echo '<td>' . html_escape(gallery_status_label($photo['status'])) . '</td>';
        echo '</tr>' . "\n";
    }
    echo '</table>' . "\n";

    gallery_render_navigation($page, $pages, $total);
    gallery_page_end();
}

// ---------------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed.';
    exit;
}

// The gallery shows images, so (and only so) it allows images from this site.
send_security_headers(true);
session_start_secure();

// 1. Authentication. The visitor id comes from the server-side session and from nowhere else.
$visitor = visitor_require_login(GALLERY_IDENTIFY_PAGE);

try {
    $pdo = db_connection();
    $total = gallery_count_photos($pdo, $visitor['id']);
    // 2. The page number is validated and forced into range; only that page's rows are read.
    $page = gallery_clamp_page(gallery_requested_page($_GET['page'] ?? null), $total);
    $photos = $total > 0 ? gallery_fetch_page($pdo, $visitor['id'], $page) : [];
} catch (Throwable $e) {
    // Details go to the server log only.
    error_log(sprintf('Gallery could not be loaded: %s: %s', get_class($e), $e->getMessage()));
    gallery_page_start('My photographs', 500);
    echo '<h2>My photographs</h2>' . "\n";
    echo '<p><strong>Your photographs could not be shown because of a technical problem. Please try again later.</strong></p>' . "\n";
    gallery_page_end();
    exit;
}

gallery_render($visitor, $photos, $page, gallery_page_count($total), $total, (string) app_config('UPLOAD_STORAGE_PATH'));
