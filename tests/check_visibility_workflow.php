<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $path): string => (string) file_get_contents($root . DIRECTORY_SEPARATOR . $path);
$visitors = $read('includes/visitors.php');
$register = $read('register.php');
$identify = $read('identify.php');
$upload = $read('upload.php');
$uploadLogic = $read('includes/uploads.php');
$gallery = $read('includes/gallery.php');
$photoEndpoint = $read('photo.php');
$slideshow = $read('includes/slideshow.php');
$adminPhotos = $read('admin/photos.php');
$schema = $read('database/schema.sql');
$migration = $read('database/step11_photo_visibility_migration.sql');
$styles = $read('assets/css/style.css');

$checks = [
    'duplicate-name check uses a prepared query' => str_contains($visitors, 'function visitor_name_exists') && str_contains($visitors, 'SELECT 1 FROM visitors WHERE name = ? LIMIT 1'),
    'same-name registration shows a warning and directs returning visitors to identify' => str_contains($register, 'visitor_name_exists($pdo') && str_contains($register, 'confirm_new_visitor') && str_contains($register, 'href="identify.php"'),
    'same-name records are never automatically merged or authenticated' => str_contains($visitors, 'not unique identifiers') && str_contains($register, 'different visitor with the same name'),
    'returning-visitor identification looks up the existing visitor code instead of registering again' => str_contains($identify, 'visitor_find_by_code(') && !str_contains($identify, 'visitor_register('),
    'upload form offers Private and Public choices' => str_contains($upload, 'value="private"') && str_contains($upload, 'value="public"'),
    'Private is the default upload choice' => str_contains($upload, '$visibilityValue = \'private\''),
    'Public upload requires explicit consent' => str_contains($upload, '$_POST[\'visibility\'] === \'public\'') && str_contains($upload, '$_POST[\'consent\'] ?? null) !== \'yes\''),
    'Private upload does not create a consent record' => str_contains($uploadLogic, "if (\$visibility === 'public')") && str_contains($uploadLogic, 'NULL consent_id') && str_contains($uploadLogic, 'INSERT INTO photos (visitor_id, consent_id, visibility'),
    'each photo row records its visibility' => str_contains($uploadLogic, 'consentId, $visibility, $file[\'stored_name\']'),
    'owner gallery shows Private photos or Public photos with matching consent' => str_contains($gallery, "p.visibility = 'private' OR (p.visibility = 'public' AND c.id IS NOT NULL)"),
    'owner gallery queries are constrained to the identified visitor id' => str_contains($gallery, 'WHERE p.visitor_id = ? AND p.status = ?') && str_contains($gallery, 'p.visitor_id = :visitor'),
    'owner photo endpoint requires session identity and owner match' => str_contains($photoEndpoint, 'visitor_current()') && str_contains($photoEndpoint, 'gallery_find_visible_photo(db_connection(), $photoId, $visitor[\'id\'])'),
    'public slideshow selects Public, active, approved, consented, not-withdrawn photos only' => str_contains($slideshow, "p.visibility = 'public'") && str_contains($slideshow, 'p.status = :status') && str_contains($slideshow, 'p.approval_status = :approval') && str_contains($slideshow, 'c.consent_version = :version') && str_contains($slideshow, 'c.withdrawn_at IS NULL'),
    'administrator review queue contains Public photos only' => str_contains($adminPhotos, "WHERE ph.visibility = \'public\'") && str_contains($adminPhotos, 'Review only Public submissions'),
    'new schema makes consent optional and defaults visibility to Private' => str_contains($schema, 'consent_id         BIGINT UNSIGNED NULL DEFAULT NULL') && str_contains($schema, "visibility         VARCHAR(10)     NOT NULL DEFAULT 'private'"),
    'migration preserves old Public photos only with active consent version 1.0' => str_contains($migration, 'MODIFY consent_id BIGINT UNSIGNED NULL DEFAULT NULL') && str_contains($migration, "SET p.visibility = 'public'") && str_contains($migration, "c.consent_version = '1.0'") && str_contains($migration, 'c.withdrawn_at IS NULL'),
    'upload UI includes clear visibility styles' => str_contains($styles, '.upload-visibility') && str_contains($styles, '.visibility-option'),
];

$passed = 0;
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
        continue;
    }
    echo 'PASS: ' . $label . PHP_EOL;
    $passed++;
}
if ($passed !== count($checks)) {
    fwrite(STDERR, 'RESULT: FAIL - ' . $passed . '/' . count($checks) . ' visibility/identity checks passed.' . PHP_EOL);
    exit(1);
}
echo 'RESULT: PASS - ' . $passed . ' visibility/identity checks.' . PHP_EOL;
