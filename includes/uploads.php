<?php
declare(strict_types=1);

/**
 * Secure multiple photo upload logic (Step 5).
 *
 * No HTML and no session code lives here. upload.php (the page) checks the visitor session and the
 * CSRF token, then hands the uploaded files to upload_process(), which:
 *
 *   1. checks the number of files and, for each file, the PHP upload error, the size, the real
 *      content type (PHP Fileinfo), the file name extension and that the file is a real image;
 *   2. moves every accepted file into private storage under a random name;
 *   3. in ONE database transaction inserts a public-display consent row only for a PUBLIC upload,
 *      then inserts one photos row for every stored file; PRIVATE uploads have no public consent;
 *   4. on any failure rolls the transaction back AND deletes the files it just stored, because a
 *      database rollback does not remove files.
 *
 * The visitor id is a parameter: the page takes it from the server-side session, never from the form.
 * The visitor's file name is only cleaned and stored as metadata; it never becomes part of a path.
 */

// Direct web requests to this file do nothing: it must be loaded by a page.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/**
 * Version of the consent text below. Stored in consents.consent_version (VARCHAR(20)).
 * Change the number whenever the wording of upload_consent_text() changes.
 */
const UPLOAD_CONSENT_VERSION = '1.0';

/** Per-photo visibility choices. Private is the safe default. */
const UPLOAD_VISIBILITIES = ['private', 'public'];

/**
 * Initial value of photos.status. The independent approval_status starts as 'pending' for every
 * upload; an administrator must approve it before the public slideshow can display it. The visitor's
 * consent is stored separately and is never treated as administrator approval.
 */
const UPLOAD_INITIAL_STATUS = 'active';

/** How many unused random names are tried before giving up (a clash is practically impossible). */
const UPLOAD_NAME_MAX_ATTEMPTS = 5;

/** Longest original file name kept as metadata (photos.original_filename is VARCHAR(255)). */
const UPLOAD_ORIGINAL_NAME_MAX_LENGTH = 255;

/** The wording the visitor agrees to. Its version is UPLOAD_CONSENT_VERSION. */
function upload_consent_text(): string
{
    return 'I agree that the photographs I upload now may be shown publicly on the slideshow screen '
        . 'of this system. I confirm that I have the right to share them.';
}

/**
 * The image types that may be uploaded, from the setting UPLOAD_ALLOWED_MIME_TYPES, each with the
 * file name extensions that are accepted for it.
 *
 * @return array<string, list<string>>
 */
function upload_allowed_types(): array
{
    $known = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];
    $allowed = [];
    foreach (explode(',', (string) app_config('UPLOAD_ALLOWED_MIME_TYPES')) as $mime) {
        if (isset($known[$mime])) {
            $allowed[$mime] = $known[$mime];
        }
    }
    return $allowed;
}

/** Extension used for the stored file, chosen from the detected type (never from the visitor's name). */
function upload_stored_extension(string $mime): string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => throw new InvalidArgumentException('Unsupported image type.'),
    };
}

/** The image type constant (from getimagesize) that belongs to a MIME type. */
function upload_image_type_for(string $mime): int
{
    return match ($mime) {
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png'  => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
        default      => -1,
    };
}

/** A php.ini size such as "8M" or "64M" in bytes (0 or -1 mean "no limit" and give PHP_INT_MAX). */
function upload_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '0' || $value === '-1') {
        return PHP_INT_MAX;
    }
    $number = (int) $value;
    return match (strtolower(substr($value, -1))) {
        'g'     => $number * 1024 * 1024 * 1024,
        'm'     => $number * 1024 * 1024,
        'k'     => $number * 1024,
        default => $number,
    };
}

/** Largest request body PHP accepts (php.ini post_max_size), in bytes. */
function upload_post_max_bytes(): int
{
    return upload_ini_bytes((string) ini_get('post_max_size'));
}

/**
 * The private storage folder (UPLOAD_STORAGE_PATH, or storage/uploads inside the project).
 * The folder is created if it is missing (permissions 0750, never 0777) and must be writable.
 * Returns null when it cannot be used; the reason is written to the server log, not to the page.
 */
function upload_storage_directory(?string $path = null): ?string
{
    $directory = $path ?? (string) app_config('UPLOAD_STORAGE_PATH');

    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        error_log('Photo upload: the private storage folder does not exist and could not be created.');
        return null;
    }
    if (!is_writable($directory)) {
        error_log('Photo upload: the private storage folder is not writable by the web server account.');
        return null;
    }
    if (upload_storage_is_inside_project($directory)) {
        error_log('Photo upload: the private storage folder is inside the project folder. Set UPLOAD_STORAGE_PATH to a folder outside the web root (see README).');
    }
    return $directory;
}

/**
 * Is the storage folder inside the project folder (and so, usually, inside the web folder)?
 * That works only while the web server refuses to serve it; a folder OUTSIDE the project is safer.
 */
function upload_storage_is_inside_project(string $directory): bool
{
    $folder = realpath($directory);
    $project = realpath(APP_ROOT);
    if ($folder === false || $project === false) {
        return false;
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        $folder = strtolower($folder);
        $project = strtolower($project);
    }
    return $folder === $project || str_starts_with($folder, $project . DIRECTORY_SEPARATOR);
}

/** Random stored file name: 24 random bytes as 48 lower-case hex characters, a dot and the extension. */
function upload_random_filename(string $extension): string
{
    return bin2hex(random_bytes(24)) . '.' . $extension;
}

/**
 * Full path of a stored file inside the storage folder: <storage>/<first two characters>/<name>.
 * The name must have the exact shape of upload_random_filename(), so no other text, and in particular
 * no path separator or "..", can ever reach the file system.
 */
function upload_stored_path(string $storageDirectory, string $storedName): string
{
    if (preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $storedName) !== 1) {
        throw new InvalidArgumentException('Invalid stored file name.');
    }
    return $storageDirectory . DIRECTORY_SEPARATOR . substr($storedName, 0, 2) . DIRECTORY_SEPARATOR . $storedName;
}

/**
 * Turns the visitor's file name into safe metadata text: only the part after the last "/" or "\",
 * valid UTF-8 only, control characters removed, at most 255 characters, never empty.
 * The result is stored in the database and shown escaped; it is never used in a path.
 */
function upload_clean_original_name(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $position = strrpos($name, '/');
    if ($position !== false) {
        $name = substr($name, $position + 1);
    }
    if (!mb_check_encoding($name, 'UTF-8')) {
        $name = '';
    }
    $name = trim((string) preg_replace('/[\x00-\x1F\x7F\p{Cc}]+/u', '', $name));
    if (mb_strlen($name, 'UTF-8') > UPLOAD_ORIGINAL_NAME_MAX_LENGTH) {
        $name = mb_substr($name, 0, UPLOAD_ORIGINAL_NAME_MAX_LENGTH, 'UTF-8');
    }
    return $name === '' ? 'photo' : $name;
}

/**
 * Turns the $_FILES entry of a multiple file field into a plain list of files, or null when the
 * entry does not have the expected shape (someone sent something else than a file list).
 *
 * @return null|list<array{name: string, tmp_name: string, error: int, size: int}>
 */
function upload_collect_files(mixed $entry): ?array
{
    if (!is_array($entry)) {
        return null;
    }
    foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
        if (!array_key_exists($key, $entry)) {
            return null;
        }
    }

    // A single file field (not name="photos[]") arrives as plain values: treat it as a list of one.
    if (!is_array($entry['name'])) {
        $entry = [
            'name' => [$entry['name']], 'tmp_name' => [$entry['tmp_name']],
            'error' => [$entry['error']], 'size' => [$entry['size']],
        ];
    }

    $files = [];
    foreach ($entry['name'] as $index => $name) {
        $tmp = $entry['tmp_name'][$index] ?? null;
        $error = $entry['error'][$index] ?? null;
        $size = $entry['size'][$index] ?? null;
        if (!is_string($name) || !is_string($tmp) || !is_int($error) || !is_int($size)) {
            return null; // a nested array (photos[a][b]) or a missing part
        }
        $files[] = ['name' => $name, 'tmp_name' => $tmp, 'error' => $error, 'size' => $size];
    }
    return $files;
}

/** "5 MB" style text for a number of bytes. */
function upload_format_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }
    return max(1, intdiv($bytes, 1024)) . ' KB';
}

/**
 * Checks one uploaded file. Never trusts the file name, the size or the type sent by the browser.
 *
 * $requireUploaded is true for real requests (the file must be one PHP received through an HTTP upload);
 * only the command-line test passes false.
 *
 * @param array{name: string, tmp_name: string, error: int, size: int} $file
 * @param array<string, list<string>> $allowed
 * @return array{ok: bool, message: string, mime: string, extension: string, size: int, original_name: string}
 */
function upload_validate_file(array $file, array $allowed, int $maxSize, bool $requireUploaded = true): array
{
    $originalName = upload_clean_original_name($file['name']);
    $fail = static fn (string $message): array => [
        'ok' => false, 'message' => $message, 'mime' => '', 'extension' => '', 'size' => 0, 'original_name' => $originalName,
    ];

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $fail('The file is larger than the server accepts (limit ' . upload_format_size($maxSize) . ').');
        case UPLOAD_ERR_PARTIAL:
            return $fail('The file was only partly received. Please try again.');
        default:
            // NO_TMP_DIR, CANT_WRITE, EXTENSION: a problem of the server, not of the visitor.
            error_log('Photo upload: PHP reported upload error code ' . $file['error'] . '.');
            return $fail('The file could not be received because of a technical problem.');
    }

    $tmp = $file['tmp_name'];
    if ($tmp === '' || ($requireUploaded && !is_uploaded_file($tmp))) {
        return $fail('The file is not a valid upload.');
    }

    $size = @filesize($tmp);
    if ($size === false) {
        return $fail('The file could not be read.');
    }
    if ($size === 0) {
        return $fail('The file is empty.');
    }
    if ($size > $maxSize) {
        return $fail('The file is larger than ' . upload_format_size($maxSize) . '.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (!is_string($mime) || !isset($allowed[$mime])) {
        return $fail('Only JPEG, PNG and WebP photographs are accepted.');
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed[$mime], true)) {
        return $fail('The file name ending does not match the type of the file.');
    }

    $info = @getimagesize($tmp);
    if ($info === false || ($info[2] ?? 0) !== upload_image_type_for($mime)) {
        return $fail('The file is not a valid photograph.');
    }

    return ['ok' => true, 'message' => '', 'mime' => $mime, 'extension' => $extension, 'size' => $size, 'original_name' => $originalName];
}

/**
 * Processes one upload submission for one visitor.
 *
 * $visitorId must come from the server-side session. $files is the list from upload_collect_files().
 * Files that fail validation are rejected one by one (listed in "rejected"); the accepted ones are
 * stored all together or not at all: a technical failure while storing or inserting removes every
 * file of this submission again and stores no database row.
 *
 * Options (used by the command-line test; the page passes none):
 *   require_uploaded (bool)        default true
 *   mover (callable(from,to):bool) default move_uploaded_file
 *   name_generator (callable(ext):string) default upload_random_filename
 *   max_files, max_size (int)      default from the configuration
 *
 * If the caller already opened a transaction, it is left to the caller to commit or roll back.
 *
 * @param list<array{name: string, tmp_name: string, error: int, size: int}> $files
 * @param array<string, mixed> $options
 * @return array{uploaded: int, rejected: list<string>, error: null|string, technical: bool}
 */
function upload_process(PDO $pdo, int $visitorId, array $files, string $storageDirectory, array $options = []): array
{
    $result = ['uploaded' => 0, 'rejected' => [], 'error' => null, 'technical' => false];

    $maxFiles = (int) ($options['max_files'] ?? app_config('UPLOAD_MAX_FILES'));
    $maxSize = (int) ($options['max_size'] ?? app_config('UPLOAD_MAX_FILE_SIZE_BYTES'));
    $requireUploaded = (bool) ($options['require_uploaded'] ?? true);
    $mover = $options['mover'] ?? 'move_uploaded_file';
    $nameGenerator = $options['name_generator'] ?? 'upload_random_filename';
    $visibility = $options['visibility'] ?? 'private';
    if (!is_string($visibility) || !in_array($visibility, UPLOAD_VISIBILITIES, true)) {
        $result['error'] = 'Please choose whether these photographs are Private or Public.';
        return $result;
    }

    if (!class_exists('finfo')) {
        error_log('Photo upload: the PHP Fileinfo extension is not enabled.');
        return ['uploaded' => 0, 'rejected' => [], 'technical' => true,
            'error' => 'Uploads are not available because of a technical problem. Please try again later.'];
    }

    // 1. How many files? (An empty file field arrives as one entry with the error "no file".)
    $present = array_values(array_filter($files, static fn (array $f): bool => $f['error'] !== UPLOAD_ERR_NO_FILE));
    if ($present === []) {
        $result['error'] = 'Please choose at least one photograph.';
        return $result;
    }
    if (count($present) > $maxFiles) {
        $result['error'] = 'You can upload at most ' . $maxFiles . ' photographs at a time. Nothing was uploaded.';
        return $result;
    }

    // 2. Check each file.
    $allowed = upload_allowed_types();
    $accepted = [];
    foreach ($present as $file) {
        $check = upload_validate_file($file, $allowed, $maxSize, $requireUploaded);
        if ($check['ok']) {
            $accepted[] = ['tmp_name' => $file['tmp_name']] + $check;
        } else {
            $label = mb_strlen($check['original_name'], 'UTF-8') > 60
                ? mb_substr($check['original_name'], 0, 60, 'UTF-8') . '...'
                : $check['original_name'];
            $result['rejected'][] = $label . ': ' . $check['message'];
        }
    }
    if ($accepted === []) {
        $result['error'] = 'None of the photographs could be accepted.';
        return $result;
    }

    // 3. Store the accepted files, then record them, all or nothing.
    $moved = [];
    $ownsTransaction = false;
    try {
        foreach ($accepted as $index => $file) {
            $storedName = null;
            $path = '';
            for ($attempt = 1; $attempt <= UPLOAD_NAME_MAX_ATTEMPTS; $attempt++) {
                $candidate = (string) $nameGenerator(upload_stored_extension($file['mime']));
                $candidatePath = upload_stored_path($storageDirectory, $candidate); // refuses any other shape
                if (!file_exists($candidatePath)) {
                    $storedName = $candidate;
                    $path = $candidatePath;
                    break;
                }
            }
            if ($storedName === null) {
                throw new RuntimeException('No unused stored file name could be found.');
            }

            $shardDirectory = dirname($path);
            if (!is_dir($shardDirectory) && !@mkdir($shardDirectory, 0750, true) && !is_dir($shardDirectory)) {
                throw new RuntimeException('A storage sub-folder could not be created.');
            }
            if (!$mover($file['tmp_name'], $path)) {
                throw new RuntimeException('A file could not be moved into private storage.');
            }
            @chmod($path, 0640); // owner and group may read, nobody else; has no effect on Windows
            $moved[] = $path;
            $accepted[$index]['stored_name'] = $storedName;
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        // A public-display consent exists only when the visitor selected Public. A private photo
        // has a NULL consent_id and can never pass the public slideshow visibility query.
        $consentId = null;
        if ($visibility === 'public') {
            $pdo->prepare('INSERT INTO consents (visitor_id, consent_version) VALUES (?, ?)')
                ->execute([$visitorId, UPLOAD_CONSENT_VERSION]);
            $consentId = (int) $pdo->lastInsertId();
        }

        $insert = $pdo->prepare(
            'INSERT INTO photos (visitor_id, consent_id, visibility, stored_filename, original_filename, mime_type, file_size, status, approval_status) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($accepted as $file) {
            $insert->execute([
                $visitorId, $consentId, $visibility, $file['stored_name'], $file['original_name'],
                $file['mime'], $file['size'], UPLOAD_INITIAL_STATUS, 'pending',
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        // Details go to the server log only; the visitor code and file contents are never logged.
        error_log(sprintf('Photo upload failed: %s: %s', get_class($e), $e->getMessage()));

        if ($ownsTransaction && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (Throwable) {
                // nothing more can be done; the files are removed below
            }
        }
        // A rollback does not remove files: delete every file stored for this submission.
        foreach ($moved as $storedPath) {
            @unlink($storedPath);
        }

        return ['uploaded' => 0, 'rejected' => $result['rejected'], 'technical' => true,
            'error' => 'Your photographs could not be saved because of a technical problem. Nothing was uploaded. Please try again later.'];
    }

    $result['uploaded'] = count($accepted);
    return $result;
}
