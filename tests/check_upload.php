<?php
declare(strict_types=1);

/**
 * Step 5 logic check. Command line only:
 *
 *   php tests/check_upload.php
 *
 * It runs the real upload_process() of includes/uploads.php against the real database and a
 * temporary storage folder. All database changes happen inside ONE transaction that is rolled back
 * at the end (only the AUTO_INCREMENT counters move on), and the temporary folder is deleted, so
 * nothing remains. The test files are tiny images that are part of this script.
 *
 * It cannot test the web page (login redirect, CSRF, consent box, session cookie) or the browser:
 * do those by hand (README, "Photo upload (Step 5)"). Note that the command line may use a different
 * php.ini than Apache, so the PHP upload limits it reports can differ from the web server's.
 *
 * Exit status: 0 = every check passed, 1 = a check failed.
 */

// A web request (Apache, php -S) gets an empty 403 answer and nothing is run.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'visitors.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'uploads.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo '[PASS] ' . $label . PHP_EOL;
    } else {
        $failed++;
        echo '[FAIL] ' . $label . PHP_EOL;
    }
}

function note(string $text): void
{
    echo '[INFO] ' . $text . PHP_EOL;
}

/** Number of files below a folder (recursively). */
function count_files(string $directory): int
{
    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $count++;
        }
    }
    return $count;
}

function remove_tree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}

/** A file descriptor like the ones upload_collect_files() makes, backed by a real temporary file. */
function make_upload(string $sourceDirectory, string $originalName, string $bytes, int $error = UPLOAD_ERR_OK): array
{
    static $counter = 0;
    $counter++;
    $path = $sourceDirectory . DIRECTORY_SEPARATOR . 'src' . $counter . '.tmp';
    file_put_contents($path, $bytes);
    return ['name' => $originalName, 'tmp_name' => $path, 'error' => $error, 'size' => strlen($bytes)];
}

function count_rows(PDO $pdo, string $table): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

$jpg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAAEAAQDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDkKKKK8U/TD//Z', true);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAFElEQVR4nGM8ISfHAANMDEgANwcANHYBDK9qubUAAAAASUVORK5CYII=', true);
$webp = base64_decode('UklGRjoAAABXRUJQVlA4IC4AAACwAQCdASoEAAQAAUAmJaACdLoABDAAAP7x3I/4DdfFtMv/vYL/3YL/3YL/WwAA', true);
$gif = base64_decode('R0lGODdhBAAEAIAAAAAAAAAACwAAAAABAAEAAAICQABCBxIsCCAgAA7', true);

echo 'Visitor Photo Slideshow - Step 5 upload logic check' . PHP_EOL . PHP_EOL;

// --- environment ---------------------------------------------------------------
check('PHP Fileinfo extension is enabled', class_exists('finfo'));
check('PHP version is 8.2 or newer', PHP_VERSION_ID >= 80200);
note('configured limits: ' . app_config('UPLOAD_MAX_FILES') . ' files, ' . upload_format_size((int) app_config('UPLOAD_MAX_FILE_SIZE_BYTES'))
    . ' each, types ' . app_config('UPLOAD_ALLOWED_MIME_TYPES'));
note('php.ini (command line): upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size')
    . ', max_file_uploads=' . ini_get('max_file_uploads') . ' (the web server may use different values)');
$configuredStorage = (string) app_config('UPLOAD_STORAGE_PATH');
if (is_dir($configuredStorage) && upload_storage_is_inside_project($configuredStorage)) {
    echo '[WARN] The private storage folder is inside the project folder. It is protected only by web-server rules; '
        . 'a folder outside the project (UPLOAD_STORAGE_PATH) is safer. See README.' . PHP_EOL;
}

try {
    $pdo = db_connection();
} catch (Throwable $e) {
    echo '[FAIL] Could not connect (' . get_class($e) . '). Run tests/check_database.php first.' . PHP_EOL;
    exit(1);
}

// --- schema as the code expects it ----------------------------------------------
$columns = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photos'"
)->fetchAll(PDO::FETCH_COLUMN);
$expected = ['id', 'visitor_id', 'consent_id', 'stored_filename', 'original_filename', 'mime_type', 'file_size', 'status', 'approval_status', 'uploaded_at'];
check('photos has the columns the upload code uses', array_diff($expected, $columns) === []);
$consentColumns = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consents'"
)->fetchAll(PDO::FETCH_COLUMN);
check('consents has id, visitor_id, consent_version, consented_at', array_diff(['id', 'visitor_id', 'consent_version', 'consented_at'], $consentColumns) === []);
$foreignKeys = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'photos'"
)->fetchColumn();
check('photos has its 2 foreign keys (visitor, consent)', $foreignKeys === 2);

// --- temporary folders ------------------------------------------------------------
$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vps_upload_check_' . bin2hex(random_bytes(6));
$storage = $base . DIRECTORY_SEPARATOR . 'private';
$source = $base . DIRECTORY_SEPARATOR . 'source';
mkdir($storage, 0700, true);
mkdir($source, 0700, true);
check('a temporary private storage folder can be used', upload_storage_directory($storage) === $storage);

$options = ['require_uploaded' => false, 'mover' => 'copy'];
$photosBefore = count_rows($pdo, 'photos');
$consentsBefore = count_rows($pdo, 'consents');
$pdo->beginTransaction();

try {
    $visitor = visitor_register($pdo, 'Upload Check');
    $visitorId = $visitor['id'];

    // 1. one valid photograph
    $one = upload_process($pdo, $visitorId, [make_upload($source, 'holiday.JPG', $jpg)], $storage, $options);
    check('1. one valid JPEG is accepted', $one['uploaded'] === 1 && $one['error'] === null && $one['rejected'] === []);
    check('1. one file exists in storage', count_files($storage) === 1);

    // 2. several valid photographs, in all three formats
    $many = upload_process($pdo, $visitorId, [
        make_upload($source, 'a.jpeg', $jpg),
        make_upload($source, 'b.png', $png),
        make_upload($source, 'c.webp', $webp),
    ], $storage, $options);
    check('2. a JPEG, a PNG and a WebP are accepted together', $many['uploaded'] === 3 && $many['error'] === null);
    check('2. four files are now in storage', count_files($storage) === 4);

    // 3 + 4 + 5. owner, metadata, random names, one consent per session
    $rows = $pdo->query(
        'SELECT p.*, c.visitor_id AS consent_visitor, c.consent_version FROM photos p JOIN consents c ON c.id = p.consent_id '
        . 'WHERE p.visitor_id = ' . (int) $visitorId . ' ORDER BY p.id'
    )->fetchAll();
    check('3. four photo rows belong to the visitor', count($rows) === 4);
    check('3. every photo row points at a consent of the same visitor', count(array_filter($rows, static fn ($r) => (int) $r['consent_visitor'] === $visitorId)) === 4);
    check('3. consent version is stored', count(array_filter($rows, static fn ($r) => $r['consent_version'] === UPLOAD_CONSENT_VERSION)) === 4);
    check('3. one consent row per upload session (2 sessions, 2 consent rows)', count(array_unique(array_column($rows, 'consent_id'))) === 2);
    check('4. original names are stored as metadata', array_column($rows, 'original_filename') === ['holiday.JPG', 'a.jpeg', 'b.png', 'c.webp']);
    check('5. stored names are 48 random hex characters plus an extension', count(array_filter($rows, static fn ($r) => preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $r['stored_filename']) === 1)) === 4);
    check('5. stored names are all different', count(array_unique(array_column($rows, 'stored_filename'))) === 4);
    check('5. no stored name contains the original name', count(array_filter($rows, static fn ($r) => stripos($r['stored_filename'], pathinfo($r['original_filename'], PATHINFO_FILENAME)) !== false && strlen(pathinfo($r['original_filename'], PATHINFO_FILENAME)) > 1)) === 0);
    check('stored MIME types come from the content', array_column($rows, 'mime_type') === ['image/jpeg', 'image/jpeg', 'image/png', 'image/webp']);
    check('stored file sizes are the real sizes', (int) $rows[1]['file_size'] === strlen($jpg) && (int) $rows[2]['file_size'] === strlen($png));
    check('initial status is the documented one', count(array_filter($rows, static fn ($r) => $r['status'] === UPLOAD_INITIAL_STATUS)) === 4);
    check('upload time is set', count(array_filter($rows, static fn ($r) => !empty($r['uploaded_at']))) === 4);
    $allExist = true;
    foreach ($rows as $row) {
        $allExist = $allExist && is_file(upload_stored_path($storage, $row['stored_filename']));
    }
    check('every stored file exists at the path built from its stored name', $allExist);

    // path tricks in the visitor's file name
    $trick = upload_process($pdo, $visitorId, [make_upload($source, '../../evil.jpg', $jpg)], $storage, $options);
    $trickRow = $pdo->query('SELECT original_filename, stored_filename FROM photos WHERE visitor_id = ' . (int) $visitorId . ' ORDER BY id DESC LIMIT 1')->fetch();
    check('4. a file name with ../ is cleaned to its last part and stays metadata', $trick['uploaded'] === 1 && $trickRow['original_filename'] === 'evil.jpg' && strpos($trickRow['stored_filename'], '.') === 48);
    check('upload_stored_path refuses a name that is not a random name', (function () use ($storage): bool {
        try {
            upload_stored_path($storage, '../../etc/passwd');
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    })());
    $filesNow = count_files($storage);

    // 6. unsupported formats
    $bad = upload_process($pdo, $visitorId, [
        make_upload($source, 'anim.gif', $gif),
        make_upload($source, 'notes.txt', 'hello world'),
        make_upload($source, 'shell.php', '<?php echo 1;'),
    ], $storage, $options);
    check('6. a GIF, a text file and a PHP file are all rejected', $bad['uploaded'] === 0 && count($bad['rejected']) === 3);

    // 9. invalid content / type tricks
    $tricks = upload_process($pdo, $visitorId, [
        make_upload($source, 'fake.png', 'this is not a png'),
        make_upload($source, 'fake2.jpg', $png),
        make_upload($source, 'broken.png', substr($png, 0, 8)),
        make_upload($source, 'empty.jpg', ''),
        make_upload($source, 'toobig.jpg', 'x', UPLOAD_ERR_INI_SIZE),
        make_upload($source, 'partial.jpg', $jpg, UPLOAD_ERR_PARTIAL),
    ], $storage, $options);
    check('9. text named .png, PNG named .jpg, a cut-off PNG, an empty file and PHP upload errors are all rejected', $tricks['uploaded'] === 0 && count($tricks['rejected']) === 6);

    // a mix: valid files are accepted, the invalid one is reported
    $mix = upload_process($pdo, $visitorId, [make_upload($source, 'good.png', $png), make_upload($source, 'bad.gif', $gif)], $storage, $options);
    check('a mixed submission stores the valid photograph and reports the other', $mix['uploaded'] === 1 && count($mix['rejected']) === 1);
    $filesNow++;

    // 7. larger than the size limit
    $limit = (int) app_config('UPLOAD_MAX_FILE_SIZE_BYTES');
    $big = upload_process($pdo, $visitorId, [make_upload($source, 'big.jpg', $jpg . str_repeat('A', $limit))], $storage, $options);
    check('7. a file larger than the size limit is rejected', $big['uploaded'] === 0 && count($big['rejected']) === 1);

    // 8. more than the file limit
    $maxFiles = (int) app_config('UPLOAD_MAX_FILES');
    $tooMany = [];
    for ($i = 0; $i <= $maxFiles; $i++) {
        $tooMany[] = make_upload($source, 'p' . $i . '.png', $png);
    }
    $many2 = upload_process($pdo, $visitorId, $tooMany, $storage, $options);
    check('8. one file more than the limit rejects the whole submission', $many2['uploaded'] === 0 && $many2['error'] !== null);
    $exactly = [];
    for ($i = 0; $i < $maxFiles; $i++) {
        $exactly[] = make_upload($source, 'q' . $i . '.png', $png);
    }
    $ok = upload_process($pdo, $visitorId, $exactly, $storage, $options);
    check('8. exactly the limit is accepted', $ok['uploaded'] === $maxFiles);
    $filesNow += $maxFiles;

    $none = upload_process($pdo, $visitorId, [make_upload($source, '', '', UPLOAD_ERR_NO_FILE)], $storage, $options);
    check('no file chosen gives a message and stores nothing', $none['uploaded'] === 0 && $none['error'] !== null);

    check('rejections stored nothing: file count in storage is as expected', count_files($storage) === $filesNow);

    // collection of the $_FILES shape
    check('upload_collect_files accepts the shape of name="photos[]"', upload_collect_files([
        'name' => ['a.jpg', 'b.png'], 'tmp_name' => ['/x', '/y'], 'error' => [0, 0], 'size' => [1, 2],
    ]) !== null);
    check('upload_collect_files refuses nested arrays', upload_collect_files([
        'name' => [['a.jpg']], 'tmp_name' => [['/x']], 'error' => [[0]], 'size' => [[1]],
    ]) === null);

    // 13. failed database insertion leaves no orphaned files
    $rowsBeforeFailure = count_rows($pdo, 'photos');
    $filesBeforeFailure = count_files($storage);
    $failing = upload_process($pdo, PHP_INT_MAX, [make_upload($source, 'x1.jpg', $jpg), make_upload($source, 'x2.png', $png)], $storage, $options);
    check('13. an insert that the database refuses (unknown visitor) reports a technical failure', $failing['uploaded'] === 0 && $failing['technical'] === true);
    check('13. the files stored for that submission were removed again', count_files($storage) === $filesBeforeFailure);
    check('13. no photo row was added', count_rows($pdo, 'photos') === $rowsBeforeFailure);

    $fixedName = str_repeat('a', 48) . '.png';
    $clash = upload_process($pdo, $visitorId, [make_upload($source, 'y1.png', $png), make_upload($source, 'y2.png', $png)], $storage, $options + [
        'name_generator' => static fn (string $extension): string => $fixedName,
    ]);
    check('13. when the second file cannot be stored, the first one is removed too', $clash['uploaded'] === 0 && $clash['technical'] === true && count_files($storage) === $filesBeforeFailure);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    remove_tree($base);
}

check('after the rollback the photo count is what it was before', count_rows($pdo, 'photos') === $photosBefore);
check('after the rollback the consent count is what it was before', count_rows($pdo, 'consents') === $consentsBefore);
check('the temporary folder was removed', !is_dir($base));

echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
