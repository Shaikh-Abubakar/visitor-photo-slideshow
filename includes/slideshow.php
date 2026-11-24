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
 * Find the next eligible photograph after an id, wrapping to the first eligible photo.
 * At most one row is fetched per query; no collection of photo metadata is loaded into memory.
 * Invalid/missing files are skipped. A bounded number of attempts prevents an endless loop.
 *
 * @return null|array{id:int}
 */
function slideshow_find_next_photo(PDO $pdo, int $afterId): ?array
{
    $registeredEligibleSql =
        ' FROM photos p '
        . 'INNER JOIN visitors v ON v.id = p.visitor_id '
        . 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id '
        . ' WHERE p.status = :status AND c.consent_version = :version';

    $countStatement = $pdo->prepare('SELECT COUNT(*)' . $registeredEligibleSql);
    $countStatement->execute([
        ':status' => GALLERY_VISIBLE_STATUS,
        ':version' => SLIDESHOW_PUBLIC_CONSENT_VERSION,
    ]);
    $remaining = (int) $countStatement->fetchColumn();
    if ($remaining < 1) {
        return null;
    }

    $nextStatement = $pdo->prepare(
        'SELECT p.id, p.stored_filename, p.mime_type' . $registeredEligibleSql
        . ' AND p.id > :after_id ORDER BY p.id ASC LIMIT 1'
    );
    $firstStatement = $pdo->prepare(
        'SELECT p.id, p.stored_filename, p.mime_type' . $registeredEligibleSql
        . ' ORDER BY p.id ASC LIMIT 1'
    );

    for ($attempt = 0; $attempt < $remaining; $attempt++) {
        if ($afterId > 0) {
            $nextStatement->execute([
                ':status' => GALLERY_VISIBLE_STATUS,
                ':version' => SLIDESHOW_PUBLIC_CONSENT_VERSION,
                ':after_id' => $afterId,
            ]);
            $row = $nextStatement->fetch();
        } else {
            $row = false;
        }
        if (!is_array($row)) {
            $firstStatement->execute([
                ':status' => GALLERY_VISIBLE_STATUS,
                ':version' => SLIDESHOW_PUBLIC_CONSENT_VERSION,
            ]);
            $row = $firstStatement->fetch();
        }
        if (!is_array($row)) {
            return null;
        }

        $photoId = (int) $row['id'];
        $path = gallery_resolve_file((string) app_config('UPLOAD_STORAGE_PATH'), (string) $row['stored_filename']);
        if ($path !== null) {
            $actualMime = gallery_detect_mime($path);
            if ($actualMime !== null && hash_equals((string) $row['mime_type'], $actualMime)) {
                $imageInfo = @getimagesize($path);
                if (is_array($imageInfo) && (int) ($imageInfo[0] ?? 0) > 0 && (int) ($imageInfo[1] ?? 0) > 0) {
                    return ['id' => $photoId];
                }
                error_log('Slideshow photo ' . $photoId . ': image dimensions could not be read.');
            } else {
                error_log('Slideshow photo ' . $photoId . ': stored image type did not match its database record.');
            }
        } else {
            error_log('Slideshow photo ' . $photoId . ': image file is missing or is not safely inside private storage.');
        }
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
        . 'WHERE p.id = :id AND p.status = :status AND c.consent_version = :version LIMIT 1'
    );
    $statement->execute([
        ':id' => $photoId,
        ':status' => GALLERY_VISIBLE_STATUS,
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
