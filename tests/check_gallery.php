<?php
declare(strict_types=1);

/**
 * Step 6 logic check. Command line only:
 *
 *   php tests/check_gallery.php
 *
 * It runs the real functions of includes/gallery.php against the real database and a temporary
 * storage folder. All database changes happen inside ONE transaction that is rolled back at the end
 * (only the AUTO_INCREMENT counters move on), and the temporary folder is deleted, so nothing remains.
 *
 * What it checks: number and page validation, the visibility rule (owner, status, consent), the page
 * size and the Previous/Next arithmetic, and the safe path resolution (traversal, outside folders,
 * missing files). What it cannot check: the web pages themselves (login redirect, headers, images in
 * a browser). Do those by hand (README, "Visitor photo gallery (Step 6)").
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
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery.php';

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

function remove_tree(string $directory): void
{
    if (!is_dir($directory) || is_link($directory)) {
        @unlink($directory);
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        ($item->isDir() && !$item->isLink()) ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($directory);
}

function count_rows(PDO $pdo, string $table): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

/** Inserts one consent and returns its id. */
function make_consent(PDO $pdo, int $visitorId): int
{
    $pdo->prepare('INSERT INTO consents (visitor_id, consent_version) VALUES (?, ?)')->execute([$visitorId, UPLOAD_CONSENT_VERSION]);
    return (int) $pdo->lastInsertId();
}

/** Inserts one photo row and returns its id. */
function make_photo(PDO $pdo, int $visitorId, int $consentId, string $label, string $status = 'active', string $mime = 'image/png'): int
{
    static $counter = 0;
    $counter++;
    $stored = str_pad(dechex($counter), 48, 'c', STR_PAD_LEFT) . '.png';
    $pdo->prepare(
        'INSERT INTO photos (visitor_id, consent_id, visibility, stored_filename, original_filename, mime_type, file_size, status) VALUES (?, ?, \'public\', ?, ?, ?, ?, ?)'
    )->execute([$visitorId, $consentId, $stored, $label, $mime, 100, $status]);
    return (int) $pdo->lastInsertId();
}

/** Inserts an owner-only photo with no public consent record. */
function make_private_photo(PDO $pdo, int $visitorId, string $label, string $status = 'active'): int
{
    static $privateCounter = 0;
    $privateCounter++;
    $stored = str_pad(dechex(1000 + $privateCounter), 48, 'd', STR_PAD_LEFT) . '.png';
    $pdo->prepare(
        "INSERT INTO photos (visitor_id, consent_id, visibility, stored_filename, original_filename, mime_type, file_size, status) VALUES (?, NULL, 'private', ?, ?, 'image/png', 100, ?)"
    )->execute([$visitorId, $stored, $label, $status]);
    return (int) $pdo->lastInsertId();
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAAFElEQVR4nGM8ISfHAANMDEgANwcANHYBDK9qubUAAAAASUVORK5CYII=', true);

echo 'Visitor Photo Slideshow - Step 6 gallery logic check' . PHP_EOL . PHP_EOL;

// --- environment ---------------------------------------------------------------
check('PHP Fileinfo extension is enabled', class_exists('finfo'));
check('PHP version is 8.2 or newer', PHP_VERSION_ID >= 80200);
check('the gallery page size is 20', GALLERY_PER_PAGE === 20);

// --- 1. number validation (no database needed) ---------------------------------
check('photo id "7" is accepted', gallery_parse_positive_int('7') === 7);
foreach (['', '0', '-1', '+1', '1.5', '01', ' 1', '1 ', '1a', 'abc', '1e3', '0x1', '99999999999999999999', "1\n"] as $bad) {
    check('photo id ' . var_export($bad, true) . ' is refused', gallery_parse_positive_int($bad) === null);
}
check('photo id given as an array is refused', gallery_parse_positive_int(['1']) === null);
check('photo id that is missing (null) is refused', gallery_parse_positive_int(null) === null);
check('an invalid page number means page 1', gallery_requested_page('abc') === 1 && gallery_requested_page('0') === 1 && gallery_requested_page(null) === 1);

// --- 2. page arithmetic -----------------------------------------------------------
check('0 photos still make 1 page', gallery_page_count(0) === 1);
check('20 photos make 1 page, 21 make 2, 40 make 2, 41 make 3',
    gallery_page_count(20) === 1 && gallery_page_count(21) === 2 && gallery_page_count(40) === 2 && gallery_page_count(41) === 3);
check('a page past the end is forced to the last page; below 1 to page 1',
    gallery_clamp_page(99, 26) === 2 && gallery_clamp_page(0, 26) === 1 && gallery_clamp_page(5, 0) === 1);

try {
    $pdo = db_connection();
} catch (Throwable $e) {
    echo '[FAIL] Could not connect (' . get_class($e) . '). Run tests/check_database.php first.' . PHP_EOL;
    exit(1);
}

$photosBefore = count_rows($pdo, 'photos');
$consentsBefore = count_rows($pdo, 'consents');
$visitorsBefore = count_rows($pdo, 'visitors');
$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vps_gallery_check_' . bin2hex(random_bytes(4));

try {
    $pdo->beginTransaction();

    // --- 3. visibility rule and ownership ----------------------------------------
    $alice = visitor_register($pdo, 'Gallery Check Alice')['id'];
    $bob = visitor_register($pdo, 'Gallery Check Bob')['id'];
    $carol = visitor_register($pdo, 'Gallery Check Carol')['id'];

    $aliceEarlier = make_consent($pdo, $alice);
    $aliceLater = make_consent($pdo, $alice);
    $bobConsent = make_consent($pdo, $bob);

    $a1 = make_photo($pdo, $alice, $aliceEarlier, 'a1.png');
    $a2 = make_photo($pdo, $alice, $aliceEarlier, 'a2.png');
    $a3 = make_photo($pdo, $alice, $aliceLater, 'a3.png');
    $aHidden = make_photo($pdo, $alice, $aliceLater, 'a-hidden.png', 'hidden');
    $b1 = make_photo($pdo, $bob, $bobConsent, 'b1.png');
    $alicePrivate = make_private_photo($pdo, $alice, 'alice-private.png');

    check('Alice sees 4 active photographs including her Private photo (the hidden one is not counted)',
        gallery_count_photos($pdo, $alice) === 4);
    check('Bob sees exactly 1 photograph', gallery_count_photos($pdo, $bob) === 1);
    check('Carol (no photographs) sees 0', gallery_count_photos($pdo, $carol) === 0);

    $aliceRows = gallery_fetch_page($pdo, $alice, 1);
    $aliceIds = array_map(static fn (array $r): int => $r['id'], $aliceRows);
    check('Alice\'s page lists her photographs, including her Private photo, newest first', $aliceIds === [$alicePrivate, $a3, $a2, $a1]);
    check('Alice\'s page contains no photograph of Bob and no hidden photograph',
        !in_array($b1, $aliceIds, true) && !in_array($aHidden, $aliceIds, true));
    check('every listed row has status active', array_unique(array_map(static fn (array $r): string => $r['status'], $aliceRows)) === ['active']);

    check('Alice can open her own Public photograph (find_visible_photo)', (gallery_find_visible_photo($pdo, $a1, $alice)['id'] ?? 0) === $a1);
    check('Alice can open her own Private photograph', (gallery_find_visible_photo($pdo, $alicePrivate, $alice)['id'] ?? 0) === $alicePrivate);
    check('Bob cannot open Alice\'s Private photograph', gallery_find_visible_photo($pdo, $alicePrivate, $bob) === null);
    check('Alice cannot open Bob\'s photograph by its id', gallery_find_visible_photo($pdo, $b1, $alice) === null);
    check('Bob cannot open Alice\'s photograph by its id', gallery_find_visible_photo($pdo, $a1, $bob) === null);
    check('a hidden photograph cannot be opened, not even by its owner', gallery_find_visible_photo($pdo, $aHidden, $alice) === null);
    check('a photo id that does not exist gives null', gallery_find_visible_photo($pdo, PHP_INT_MAX, $alice) === null);
    check('a visitor id that does not exist sees nothing', gallery_count_photos($pdo, PHP_INT_MAX) === 0);

    // The foreign key normally makes a photo without a matching consent impossible. Switch it off only
    // for this connection to simulate a damaged record, then check that the gallery refuses the photo.
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $damaged = make_photo($pdo, $alice, 999999999, 'a-damaged.png');           // consent id that does not exist
    $crossed = make_photo($pdo, $alice, $bobConsent, 'a-crossed.png');         // consent that belongs to Bob
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    check('a photograph with no consent record is not counted or listed or opened',
        gallery_count_photos($pdo, $alice) === 4
        && !in_array($damaged, array_map(static fn (array $r): int => $r['id'], gallery_fetch_page($pdo, $alice, 1)), true)
        && gallery_find_visible_photo($pdo, $damaged, $alice) === null);
    check('a photograph that points at ANOTHER visitor\'s consent is not shown',
        gallery_find_visible_photo($pdo, $crossed, $alice) === null);

    // --- 4. pagination with real rows -------------------------------------------
    $dave = visitor_register($pdo, 'Gallery Check Dave')['id'];
    $daveConsent = make_consent($pdo, $dave);
    $daveIds = [];
    for ($i = 1; $i <= 45; $i++) {
        $daveIds[] = make_photo($pdo, $dave, $daveConsent, 'd' . $i . '.png');
    }
    check('Dave has 45 photographs in total', gallery_count_photos($pdo, $dave) === 45);
    check('45 photographs make 3 pages', gallery_page_count(45) === 3);
    $p1 = gallery_fetch_page($pdo, $dave, 1);
    $p2 = gallery_fetch_page($pdo, $dave, 2);
    $p3 = gallery_fetch_page($pdo, $dave, 3);
    check('page 1 has 20 rows, page 2 has 20, page 3 has 5', count($p1) === 20 && count($p2) === 20 && count($p3) === 5);
    $all = array_merge(array_column($p1, 'id'), array_column($p2, 'id'), array_column($p3, 'id'));
    check('the three pages together hold all 45 photographs, each once', count($all) === 45 && count(array_unique($all)) === 45);
    check('the order is newest first across page borders', $all === array_reverse($daveIds));
    check('a page past the end returns no rows (the page itself clamps before asking)', gallery_fetch_page($pdo, $dave, 4) === []);

    // --- 5. safe file resolution ---------------------------------------------------
    $storage = $base . DIRECTORY_SEPARATOR . 'private';
    $outside = $base . DIRECTORY_SEPARATOR . 'private-other';
    mkdir($storage, 0750, true);
    mkdir($outside, 0750, true);

    $name = str_repeat('a', 48) . '.png';
    $shard = $storage . DIRECTORY_SEPARATOR . 'aa';
    mkdir($shard, 0750, true);
    file_put_contents($shard . DIRECTORY_SEPARATOR . $name, $png);

    $resolved = gallery_resolve_file($storage, $name);
    check('a stored file inside the private folder is found', $resolved !== null && is_file($resolved));
    check('the real file type of that file is image/png', $resolved !== null && gallery_detect_mime($resolved) === 'image/png');
    check('a file name that does not exist gives null', gallery_resolve_file($storage, str_repeat('b', 48) . '.png') === null);
    foreach (['../x.png', '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'passwd', 'aa/' . $name, $name . '.php', str_repeat('a', 48) . '.php', 'x', '', str_repeat('a', 47) . '.png', "\0" . $name] as $evil) {
        check('a stored name of the wrong shape is refused: ' . json_encode($evil), gallery_resolve_file($storage, $evil) === null);
    }

    // A stored name with the right shape whose real location is outside the private folder
    // (a symbolic link), and a sibling folder whose name starts like the private folder.
    $secret = $outside . DIRECTORY_SEPARATOR . 'secret.png';
    file_put_contents($secret, $png);
    $linkName = str_repeat('d', 48) . '.png';
    $linkShard = $storage . DIRECTORY_SEPARATOR . 'dd';
    mkdir($linkShard, 0750, true);
    $linkMade = false;
    try {
        $linkMade = @symlink($secret, $linkShard . DIRECTORY_SEPARATOR . $linkName);
    } catch (Throwable) {
        $linkMade = false;
    }
    if ($linkMade) {
        check('a symbolic link that leads outside the private folder is refused', gallery_resolve_file($storage, $linkName) === null);
    } else {
        note('symbolic links cannot be created here (normal on Windows without special rights): the symbolic-link check was skipped.');
    }

    // A fake image: PHP code with a .png name must not be reported as an image.
    $fakeName = str_repeat('e', 48) . '.png';
    $fakeShard = $storage . DIRECTORY_SEPARATOR . 'ee';
    mkdir($fakeShard, 0750, true);
    file_put_contents($fakeShard . DIRECTORY_SEPARATOR . $fakeName, '<?php echo "x"; ?>');
    $fakeResolved = gallery_resolve_file($storage, $fakeName);
    check('a file with PHP code inside is found as a file but is NOT reported as an image type',
        $fakeResolved !== null && gallery_detect_mime($fakeResolved) === null);

    // A folder where the file should be (a directory is not a file).
    $dirName = str_repeat('f', 48) . '.png';
    mkdir($storage . DIRECTORY_SEPARATOR . 'ff' . DIRECTORY_SEPARATOR . $dirName, 0750, true);
    check('a directory with the name of a photograph is refused', gallery_resolve_file($storage, $dirName) === null);
    check('a private folder that does not exist gives null', gallery_resolve_file($base . DIRECTORY_SEPARATOR . 'missing', $name) === null);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    remove_tree($base);
}

check('after the rollback the visitor count is what it was before', count_rows($pdo, 'visitors') === $visitorsBefore);
check('after the rollback the photo count is what it was before', count_rows($pdo, 'photos') === $photosBefore);
check('after the rollback the consent count is what it was before', count_rows($pdo, 'consents') === $consentsBefore);
check('the temporary folder was removed', !is_dir($base));

echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . ' - ' . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
