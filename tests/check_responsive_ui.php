<?php
declare(strict_types=1);

/**
 * Prompt 6 responsive UI source check. No database connection is required.
 * Run from the project root with: php tests/check_responsive_ui.php
 */

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function ui_check(string $label, bool $condition): void
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

function ui_source(string $relative): string
{
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_file($path) ? (string) file_get_contents($path) : '';
}

$sharedCss = ui_source('assets/css/style.css');
$slideshowCss = ui_source('slideshow.css');

$commonPages = [
    'index.php',
    'register.php',
    'identify.php',
    'upload.php',
    'gallery.php',
    'my_slideshow.php',
    'privacy.php',
    'admin/login.php',
    'admin/layout.php',
];
$allUseCurrentCss = true;
foreach ($commonPages as $page) {
    $source = ui_source($page);
    $hasVersionedCss = $page === 'index.php'
        ? (str_contains($source, 'assets/css/style.css') && str_contains($source, "\$assetVersion = '9'"))
        : str_contains($source, 'style.css?v=9');
    if ($source === '' || !$hasVersionedCss) {
        $allUseCurrentCss = false;
        break;
    }
}
$slideshowSource = ui_source('slideshow.php');
ui_check('All visitor/admin page templates reference the same common CSS cache version', $allUseCurrentCss);
ui_check('Public slideshow references the current shared and dedicated CSS version',
    str_contains($slideshowSource, "\$assetVersion = '9';")
    && str_contains($slideshowSource, 'assets/css/style.css?v=')
    && str_contains($slideshowSource, 'slideshow.css?v='));

$hasViewport = true;
foreach (['index.php', 'register.php', 'identify.php', 'upload.php', 'gallery.php', 'my_slideshow.php', 'privacy.php', 'admin/login.php', 'admin/layout.php', 'slideshow.php'] as $page) {
    $source = ui_source($page);
    if (!str_contains($source, 'name="viewport"')) {
        $hasViewport = false;
        break;
    }
}
ui_check('All page templates declare a responsive viewport', $hasViewport);

ui_check('Common CSS has mobile and tablet breakpoints',
    str_contains($sharedCss, '@media (max-width: 720px)')
    && str_contains($sharedCss, '@media (max-width: 600px)')
    && str_contains($sharedCss, '@media (max-width: 420px)')
    && str_contains($sharedCss, '@media (max-width: 820px)')
    && str_contains($sharedCss, '@media (max-width: 1120px)'));
ui_check('Gallery and admin grids use shrink-safe minmax columns',
    str_contains($sharedCss, 'repeat(auto-fill, minmax(min(100%, 220px), 1fr))')
    && str_contains($sharedCss, 'grid-template-columns: repeat(2, minmax(0, 1fr))'));
ui_check('Admin data tables are contained in horizontal scroll wrappers',
    str_contains($sharedCss, '.admin-table-wrap')
    && str_contains($sharedCss, 'overflow-x: auto;')
    && str_contains($sharedCss, '.admin-table'));
ui_check('Gallery, admin previews, and slideshow images preserve image proportions',
    str_contains($sharedCss, 'object-fit: contain;')
    && str_contains($slideshowCss, 'object-fit: contain;'));
ui_check('Responsive overflow safeguards are present for flex/grid content',
    str_contains($sharedCss, 'min-width: 0;')
    && str_contains($sharedCss, 'overflow-wrap: anywhere;')
    && str_contains($sharedCss, 'overflow-x: clip;'));

$uiFiles = array_merge($commonPages, ['slideshow.php', 'my_slideshow.php']);
$noInlineScripts = true;
foreach ($uiFiles as $page) {
    if (preg_match('/<script\b|javascript:/i', ui_source($page)) === 1) {
        $noInlineScripts = false;
        break;
    }
}
ui_check('Reviewed page templates do not introduce JavaScript', $noInlineScripts);
ui_check('Common and slideshow stylesheets do not import external CSS libraries',
    !preg_match('/@import\s+url\s*\(/i', $sharedCss . "\n" . $slideshowCss));

ui_check('Image delivery and private visitor endpoints remain separate from the UI styles',
    is_file($root . DIRECTORY_SEPARATOR . 'photo.php')
    && is_file($root . DIRECTORY_SEPARATOR . 'slideshow_photo.php')
    && is_file($root . DIRECTORY_SEPARATOR . 'my_slideshow.php')
    && is_file($root . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'photo.php'));

echo PHP_EOL . 'RESULT: ' . ($failed === 0 ? 'PASS' : 'FAIL') . " - {$passed}/" . ($passed + $failed) . " responsive UI source checks." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
