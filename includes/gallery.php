<?php
declare(strict_types=1);

/**
 * Visitor photo gallery logic (Step 6).
 *
 * No HTML and no session code lives here. gallery.php (the list) and photo.php (the image) both use
 * these functions, so the SAME visibility rule decides what is listed and what may be downloaded.
 *
 * Load after includes/bootstrap.php and includes/uploads.php:
 *
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';
 *   require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';
 *
 * The visitor id is ALWAYS a parameter that the page took from the server-side session. Nothing in
 * this file reads $_GET, $_POST or the session.
 *
 * The visibility rule (see ASSUMPTIONS.md, section I)
 *   A photograph is visible to its owner when ALL of these are true:
 *     1. photos.visitor_id is the identified visitor;
 *     2. photos.status is 'active' (the schema knows only 'active' and 'hidden');
 *     3. a consents row exists with id = photos.consent_id AND visitor_id = photos.visitor_id.
 *   The database already forces point 3 with a foreign key, but the INNER JOIN checks it again, so a
 *   photograph whose consent record has gone missing is never shown.
 */

// Direct web requests to this file do nothing: it must be loaded by a page.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/** Photographs per gallery page. */
const GALLERY_PER_PAGE = 20;

/** The only photos.status value that is listed and served. 'hidden' is never shown. */
const GALLERY_VISIBLE_STATUS = 'active';

/** Image types photo.php will send, with the extension of the stored file. */
const GALLERY_SERVED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * A positive whole number from a request value (a photo id or a page number), or null.
 * Accepts only the digits 0-9, no sign, no spaces, no decimals, no leading zeros, at most 15 digits,
 * so "7" is accepted and "07", "-7", "7.0", "7abc", "7" followed by a line break, "", an array and
 * "99999999999999999999" are not. (\z, not $: in PHP "$" also matches before a final line break.)
 */
function gallery_parse_positive_int(mixed $raw): ?int
{
    if (!is_string($raw) || preg_match('/^[1-9][0-9]{0,14}\z/', $raw) !== 1) {
        return null;
    }
    return (int) $raw;
}

/** Number of the page asked for: anything invalid means page 1. */
function gallery_requested_page(mixed $raw): int
{
    return gallery_parse_positive_int($raw) ?? 1;
}

/** Number of pages for a number of photographs (at least 1, so an empty gallery still has "page 1"). */
function gallery_page_count(int $total, int $perPage = GALLERY_PER_PAGE): int
{
    return max(1, (int) ceil($total / $perPage));
}

/** A requested page forced into the range 1 .. last page. */
function gallery_clamp_page(int $page, int $total, int $perPage = GALLERY_PER_PAGE): int
{
    return min(max(1, $page), gallery_page_count($total, $perPage));
}

/**
 * How many photographs this visitor may see (all pages together). One COUNT query, nothing loaded.
 */
function gallery_count_photos(PDO $pdo, int $visitorId): int
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM photos p '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . 'WHERE p.visitor_id = ? AND p.status = ?'
    );
    $statement->execute([$visitorId, GALLERY_VISIBLE_STATUS]);
    return (int) $statement->fetchColumn();
}

/**
 * One page of this visitor's visible photographs, newest first.
 *
 * LIMIT/OFFSET fetch only the rows of the page. ORDER BY p.id DESC follows the index on
 * photos.visitor_id (InnoDB adds the primary key to it), and a newer upload always has a higher id,
 * so "newest first" needs no extra sort. $page must already be inside 1 .. last page.
 *
 * @return list<array{id: int, original_filename: string, stored_filename: string, status: string, uploaded_at: string}>
 */
function gallery_fetch_page(PDO $pdo, int $visitorId, int $page, int $perPage = GALLERY_PER_PAGE): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $statement = $pdo->prepare(
        'SELECT p.id, p.original_filename, p.stored_filename, p.status, p.uploaded_at '
        . 'FROM photos p '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . 'WHERE p.visitor_id = :visitor AND p.status = :status '
        . 'ORDER BY p.id DESC '
        . 'LIMIT :limit OFFSET :offset'
    );
    $statement->bindValue(':visitor', $visitorId, PDO::PARAM_INT);
    $statement->bindValue(':status', GALLERY_VISIBLE_STATUS, PDO::PARAM_STR);
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $rows[] = [
            'id'                => (int) $row['id'],
            'original_filename' => (string) $row['original_filename'],
            'stored_filename'   => (string) $row['stored_filename'],
            'status'            => (string) $row['status'],
            'uploaded_at'       => (string) $row['uploaded_at'],
        ];
    }
    return $rows;
}

/**
 * One photograph, but ONLY if it belongs to $visitorId and passes the visibility rule. Otherwise null.
 * "Does not exist", "belongs to somebody else" and "is hidden" are deliberately the same answer
 * (null), so nobody can find out which photo numbers exist by trying them.
 *
 * @return null|array{id: int, stored_filename: string, mime_type: string}
 */
function gallery_find_visible_photo(PDO $pdo, int $photoId, int $visitorId): ?array
{
    $statement = $pdo->prepare(
        'SELECT p.id, p.stored_filename, p.mime_type FROM photos p '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . 'WHERE p.id = ? AND p.visitor_id = ? AND p.status = ? '
        . 'LIMIT 1'
    );
    $statement->execute([$photoId, $visitorId, GALLERY_VISIBLE_STATUS]);
    $row = $statement->fetch();

    if (!is_array($row)) {
        return null;
    }
    return [
        'id'              => (int) $row['id'],
        'stored_filename' => (string) $row['stored_filename'],
        'mime_type'       => (string) $row['mime_type'],
    ];
}

/**
 * The real path of a stored photograph, or null when it cannot be served safely.
 *
 * Defence in depth, in this order:
 *   1. upload_stored_path() accepts only the exact shape of a generated name (48 hex characters and
 *      jpg/png/webp), so "..", "/", "\" and any other text never reach the file system;
 *   2. realpath() resolves the result (and any symbolic link) to the true location;
 *   3. that true location must be INSIDE the real private storage folder (compared with a trailing
 *      separator, so "/uploads-other" does not pass as "/uploads"; case-insensitive on Windows);
 *   4. it must be a regular, readable file.
 * The path is never shown to the visitor and never put in a URL.
 */
function gallery_resolve_file(string $storageDirectory, string $storedName): ?string
{
    try {
        $candidate = upload_stored_path($storageDirectory, $storedName);
    } catch (InvalidArgumentException) {
        return null;
    }

    $root = realpath($storageDirectory);
    $real = realpath($candidate);
    if ($root === false || $real === false) {
        return null; // the folder or the file does not exist
    }

    $rootCompare = $root;
    $realCompare = $real;
    if (DIRECTORY_SEPARATOR === '\\') {
        $rootCompare = strtolower($rootCompare);
        $realCompare = strtolower($realCompare);
    }
    if (!str_starts_with($realCompare, rtrim($rootCompare, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        return null;
    }
    if (!is_file($real) || !is_readable($real)) {
        return null;
    }
    return $real;
}

/**
 * The real content type of a file (PHP Fileinfo looks at the bytes, not at the name), or null when it
 * is not one of the image types photo.php serves.
 */
function gallery_detect_mime(string $realPath): ?string
{
    if (!class_exists('finfo')) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($realPath);
    return is_string($mime) && isset(GALLERY_SERVED_TYPES[$mime]) ? $mime : null;
}
