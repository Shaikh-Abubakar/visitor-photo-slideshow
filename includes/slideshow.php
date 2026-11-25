<?php
declare(strict_types=1);

/**
 * Public slideshow selection and file validation (Step 7).
 * Load after bootstrap.php, uploads.php and gallery.php.
 * The public screen needs no visitor login, but every displayed photo must belong to
 * an existing registered visitor and have that visitor's explicit public-display consent.
 */
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/** Consent text version whose wording explicitly permits public slideshow display. */
const SLIDESHOW_PUBLIC_CONSENT_VERSION = '1.0';

/**
 * Find the next eligible public photograph in ascending id order.
 * The first call (afterId=0) starts at the oldest image. Later calls never wrap:
 * returning null means the presentation has reached its final slide.
 * Invalid/missing files are skipped while remaining within the eligible set.
 *
 * @return null|array{id:int}
 */
function slideshow_find_next_photo(PDO $pdo, int $afterId): ?array
{
    $base = ' FROM photos p '
        . 'INNER JOIN visitors v ON v.id = p.visitor_id '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . " WHERE p.visibility = 'public' AND p.status = :status AND p.approval_status = :approval AND c.consent_version = :version AND c.withdrawn_at IS NULL";
    $first = $pdo->prepare('SELECT p.id, p.stored_filename, p.mime_type' . $base . ' ORDER BY p.id ASC LIMIT 1');
    $next = $pdo->prepare('SELECT p.id, p.stored_filename, p.mime_type' . $base . ' AND p.id > :after_id ORDER BY p.id ASC LIMIT 1');
    $params = [
        ':status' => GALLERY_VISIBLE_STATUS,
        ':approval' => 'approved',
        ':version' => SLIDESHOW_PUBLIC_CONSENT_VERSION,
    ];
    $count = $pdo->prepare('SELECT COUNT(*)' . $base);
    $count->execute($params);
    $remaining = (int) $count->fetchColumn();
    if ($remaining < 1) return null;

    for ($attempt = 0; $attempt < $remaining; $attempt++) {
        if ($afterId > 0) {
            $next->execute($params + [':after_id' => $afterId]);
            $row = $next->fetch();
        } else {
            $first->execute($params);
            $row = $first->fetch();
        }
        if (!is_array($row)) return null;

        $photoId = (int) $row['id'];
        $path = gallery_resolve_file((string) app_config('UPLOAD_STORAGE_PATH'), (string) $row['stored_filename']);
        if ($path !== null) {
            $actualMime = gallery_detect_mime($path);
            if ($actualMime !== null && hash_equals((string) $row['mime_type'], $actualMime)) {
                $imageInfo = @getimagesize($path);
                if (is_array($imageInfo) && (int) ($imageInfo[0] ?? 0) > 0 && (int) ($imageInfo[1] ?? 0) > 0) {
                    return ['id' => $photoId];
                }
            }
        }
        error_log('Slideshow photo ' . $photoId . ' was skipped because its image file failed validation.');
        $afterId = $photoId;
    }

    return null;
}

/** Find one eligible photo by id and verify its private file before streaming. */
function slideshow_find_photo_for_serving(PDO $pdo, int $photoId): ?array
{
    $statement = $pdo->prepare(
        'SELECT p.id, p.stored_filename, p.mime_type FROM photos p '
        . 'INNER JOIN visitors v ON v.id = p.visitor_id '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . "WHERE p.id = :id AND p.visibility = 'public' AND p.status = :status AND p.approval_status = :approval AND c.consent_version = :version AND c.withdrawn_at IS NULL LIMIT 1"
    );
    $statement->execute([
        ':id' => $photoId,
        ':status' => GALLERY_VISIBLE_STATUS,
        ':approval' => 'approved',
        ':version' => SLIDESHOW_PUBLIC_CONSENT_VERSION,
    ]);
    $row = $statement->fetch();
    if (!is_array($row)) {
        return null;
    }

    $path = gallery_resolve_file((string) app_config('UPLOAD_STORAGE_PATH'), (string) $row['stored_filename']);
    if ($path === null) {
        return null;
    }
    $mime = gallery_detect_mime($path);
    if ($mime === null || !hash_equals((string) $row['mime_type'], $mime)) {
        return null;
    }
    $imageInfo = @getimagesize($path);
    if (!is_array($imageInfo) || (int) ($imageInfo[0] ?? 0) < 1 || (int) ($imageInfo[1] ?? 0) < 1) {
        return null;
    }
    $size = filesize($path);
    if ($size === false) {
        return null;
    }

    return ['id' => (int) $row['id'], 'path' => $path, 'mime' => $mime, 'size' => $size];
}


/** Find the previous eligible public photo without wrapping past the first slide. */
function slideshow_find_previous_photo(PDO $pdo, int $beforeId): ?array
{
    if ($beforeId < 1) return null;
    $base = ' FROM photos p INNER JOIN visitors v ON v.id = p.visitor_id '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . " WHERE p.visibility = 'public' AND p.status = :status AND p.approval_status = :approval AND c.consent_version = :version AND c.withdrawn_at IS NULL";
    $previous = $pdo->prepare('SELECT p.id, p.stored_filename, p.mime_type' . $base . ' AND p.id < :before_id ORDER BY p.id DESC');
    $previous->execute([
        ':status' => GALLERY_VISIBLE_STATUS,
        ':approval' => 'approved',
        ':version' => SLIDESHOW_PUBLIC_CONSENT_VERSION,
        ':before_id' => $beforeId,
    ]);
    while ($row = $previous->fetch()) {
        $path = gallery_resolve_file((string) app_config('UPLOAD_STORAGE_PATH'), (string) $row['stored_filename']);
        if ($path === null) continue;
        $mime = gallery_detect_mime($path);
        if ($mime === null || !hash_equals((string) $row['mime_type'], $mime)) continue;
        $dimensions = @getimagesize($path);
        if (is_array($dimensions) && (int) ($dimensions[0] ?? 0) > 0 && (int) ($dimensions[1] ?? 0) > 0) {
            return ['id' => (int) $row['id']];
        }
    }
    return null;
}
