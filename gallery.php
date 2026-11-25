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
 * The page lists only photographs the visitor may see: their own active Private photos and their own
 * Public photos with a matching consent record. Each image is loaded through photo.php, which repeats
 * the same owner and visibility checks.
 * No file path or stored file name is ever written into the page.
 *
 * This file builds all its HTML with PHP. The page uses the shared CSS design system and no JavaScript.
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
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<link rel="stylesheet" href="assets/css/style.css?v=9">' . "\n";
    echo '<title>' . html_escape($title) . '</title>' . "\n";
    echo '</head>' . "\n";
    echo '<body class="app-body">' . "\n";
    echo '<div class="app-site-shell">' . "\n";
    echo '<header class="app-header"><p class="app-brand">' . html_escape((string) app_config('APP_NAME')) . '</p><span class="app-header-tag">Visitor Photo Slideshow</span></header>' . "\n";
    echo '<main class="app-main gallery-page">' . "\n";
}

function gallery_page_end(): void
{
    echo '</main>' . "\n";
    echo '</div>' . "\n";
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}

/** The links and the logout button shown above every gallery view. */
function gallery_render_menu(array $visitor): void
{
    echo '<div class="gallery-toolbar">' . "\n";
    echo '<p class="gallery-identity"><span class="identity-mark" aria-hidden="true">' . '✦' . '</span><span>Welcome, <strong>' . html_escape($visitor['name']) . '</strong></span></p>' . "\n";
    echo '<nav class="gallery-toolbar-links" aria-label="Gallery actions"><a class="button button-secondary" href="my_slideshow.php"><span aria-hidden="true">▶</span> Play my slideshow</a><a class="button button-secondary" href="' . html_escape(GALLERY_UPLOAD_PAGE) . '"><span aria-hidden="true">＋</span> Upload photos</a><a href="privacy.php">Privacy controls</a></nav>' . "\n";
    // Logout remains a CSRF-protected POST; it is deliberately not converted into a normal link.
    echo '<form class="gallery-logout-form" method="post" action="' . html_escape(GALLERY_IDENTIFY_PAGE) . '">' . "\n";
    echo '<input type="hidden" name="csrf_token" value="' . html_escape(csrf_token()) . '">' . "\n";
    echo '<input type="hidden" name="action" value="logout">' . "\n";
    echo '<button class="button button-quiet" type="submit">End session</button>' . "\n";
    echo '</form>' . "\n";
    echo '</div>' . "\n";
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
    echo '<div class="gallery-pagination" aria-label="Gallery pagination">' . "\n";
    echo '<p class="gallery-count">Showing page <strong>' . html_escape((string) $page) . '</strong> of ' . html_escape((string) $pages)
        . ' <span>·</span> ' . html_escape((string) $total) . ' photograph' . ($total === 1 ? '' : 's') . ' total</p>' . "\n";
    if ($pages > 1) {
        echo '<nav class="pagination-links" aria-label="Photo pages">' . "\n";
        if ($page > 1) {
            echo '<a class="pagination-link" href="' . html_escape(gallery_page_link($page - 1)) . '">← Previous</a>';
        } else {
            echo '<span class="pagination-link is-disabled">← Previous</span>';
        }
        if ($page < $pages) {
            echo '<a class="pagination-link" href="' . html_escape(gallery_page_link($page + 1)) . '">Next →</a>';
        } else {
            echo '<span class="pagination-link is-disabled">Next →</span>';
        }
        echo '</nav>' . "\n";
    }
    echo '</div>' . "\n";
}

/**
 * @param array{id: int, name: string} $visitor
 * @param list<array{id: int, original_filename: string, stored_filename: string, status: string, uploaded_at: string}> $photos
 */
function gallery_render(array $visitor, array $photos, int $page, int $pages, int $total, string $storageDirectory): void
{
    gallery_page_start('My Photo Gallery', 200);
    echo '<section class="content-heading gallery-heading"><p class="section-eyebrow"><span aria-hidden="true">✦</span> Your personal collection</p>' . "\n";
    echo '<h1 class="page-title">My Photo Gallery</h1>' . "\n";
    echo '<p class="page-subtitle">All your active Private and Public photographs currently available in your personal gallery.</p></section>' . "\n";
    gallery_render_menu($visitor);

    if ($total === 0) {
        echo '<section class="gallery-empty-state"><div class="empty-gallery-icon" aria-hidden="true">▧</div>' . "\n";
        echo '<h2>Your gallery is waiting for its first memory</h2>' . "\n";
        echo '<p>Private photographs and Public photographs you upload will appear here once they are available to your account.</p>' . "\n";
        echo '<a class="button button-primary" href="' . html_escape(GALLERY_UPLOAD_PAGE) . '"><span aria-hidden="true">＋</span> Upload your first photos</a>' . "\n";
        echo '</section>' . "\n";
        gallery_page_end();
        return;
    }

    echo '<div class="gallery-intro-row"><div><h2 class="gallery-section-title">Your photographs</h2><p class="gallery-section-copy">Only your own active photographs are shown here. Private photos stay owner-only; Public photos also need consent and administrator approval to appear on the shared slideshow.</p></div>';
    echo '<span class="gallery-total-pill">' . html_escape((string) $total) . ' ' . ($total === 1 ? 'photo' : 'photos') . '</span></div>' . "\n";
    gallery_render_navigation($page, $pages, $total);

    echo '<section class="photo-grid" aria-label="Your photographs">' . "\n";
    foreach ($photos as $photo) {
        $address = GALLERY_PHOTO_ENDPOINT . '?id=' . $photo['id'];
        // Check the private file remains inside storage; paths and generated names are never rendered.
        $fileIsThere = gallery_resolve_file($storageDirectory, $photo['stored_filename']) !== null;

        echo '<article class="photo-card">' . "\n";
        echo '<div class="photo-card-image">' . "\n";
        if ($fileIsThere) {
            echo '<a class="photo-image-link" href="' . html_escape($address) . '" aria-label="Open photograph ' . html_escape($photo['original_filename']) . '">';
            echo '<img src="' . html_escape($address) . '" alt="' . html_escape('Photograph: ' . $photo['original_filename']) . '" width="160" loading="lazy">';
            echo '</a>' . "\n";
        } else {
            echo '<div class="photo-unavailable" role="status"><span aria-hidden="true">▧</span><span>Image not available</span></div>' . "\n";
        }
        echo '</div>' . "\n";
        echo '<div class="photo-card-details">' . "\n";
        echo '<div class="photo-card-title-row"><h3 title="' . html_escape($photo['original_filename']) . '">' . html_escape($photo['original_filename']) . '</h3><span class="photo-status">' . html_escape(gallery_status_label($photo['status'])) . '</span></div>' . "\n";
        if ($photo['visibility'] === 'private') {
            $visibilityLabel = 'Private — only you can see this';
            $visibilityClass = 'photo-visibility-private';
        } elseif ($photo['withdrawn_at'] !== null) {
            $visibilityLabel = 'Public consent withdrawn — owner only';
            $visibilityClass = 'photo-visibility-withdrawn';
        } elseif ($photo['status'] === 'hidden' || $photo['approval_status'] === 'rejected') {
            $visibilityLabel = 'Public — hidden or rejected';
            $visibilityClass = 'photo-visibility-withdrawn';
        } elseif ($photo['approval_status'] === 'approved' && $photo['status'] === 'active') {
            $visibilityLabel = 'Public — approved for shared slideshow';
            $visibilityClass = 'photo-visibility-public';
        } else {
            $visibilityLabel = 'Public — awaiting administrator approval';
            $visibilityClass = 'photo-visibility-pending';
        }
        echo '<p class="photo-visibility-label ' . $visibilityClass . '">' . html_escape($visibilityLabel) . '</p>' . "\n";
        echo '<p class="photo-upload-date"><span aria-hidden="true">◷</span> ' . html_escape(gallery_format_date($photo['uploaded_at'])) . '</p>' . "\n";
        echo '</div>' . "\n";
        echo '</article>' . "\n";
    }
    echo '</section>' . "\n";

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
send_security_headers(true, true);
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
    echo '<section class="content-heading gallery-heading"><h1 class="page-title">My Photo Gallery</h1></section>' . "\n";
    echo '<div class="notice notice-error" role="alert"><strong>Your photographs could not be shown because of a technical problem. Please try again later.</strong></div>' . "\n";
    gallery_page_end();
    exit;
}

gallery_render($visitor, $photos, $page, gallery_page_count($total), $total, (string) app_config('UPLOAD_STORAGE_PATH'));
