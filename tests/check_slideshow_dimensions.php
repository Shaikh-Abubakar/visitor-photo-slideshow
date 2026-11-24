<?php
declare(strict_types=1);

/*
 * Static regression checks for the current slideshow image-fit implementation.
 * The application does not define slideshow_fit_dimensions(); the browser fits
 * the original image with responsive CSS, while PHP validates image dimensions.
 */
$root = dirname(__DIR__);
$helperPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php';
$cssPath = $root . DIRECTORY_SEPARATOR . 'slideshow.css';
$helper = file_get_contents($helperPath);
$css = file_get_contents($cssPath);

$checks = [
    'PHP validates the real image dimensions with getimagesize()' =>
        str_contains($helper, 'getimagesize($path)'),
    'PHP rejects missing or invalid image dimensions' =>
        str_contains($helper, '(int) ($imageInfo[0] ?? 0) > 0')
        && str_contains($helper, '(int) ($imageInfo[1] ?? 0) > 0'),
    'image width remains automatic to preserve its aspect ratio' =>
        str_contains($css, 'width: auto;'),
    'image height remains automatic to preserve its aspect ratio' =>
        str_contains($css, 'height: auto;'),
    'image is constrained to the viewport width' =>
        str_contains($css, 'max-width: 100vw;'),
    'image is constrained to the viewport height' =>
        str_contains($css, 'max-height: 100vh;'),
    'image is contained without cropping' =>
        str_contains($css, 'object-fit: contain;'),
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
    fwrite(STDERR, 'RESULT: FAIL - ' . $passed . '/' . count($checks) . ' checks passed.' . PHP_EOL);
    exit(1);
}

echo 'RESULT: PASS - ' . $passed . ' slideshow image-dimension/display checks.' . PHP_EOL;
