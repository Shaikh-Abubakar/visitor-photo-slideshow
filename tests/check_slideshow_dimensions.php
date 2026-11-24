<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php';

$cases = [
    ['name' => 'landscape', 'image' => [4000, 2000], 'canvas' => [1920, 1080]],
    ['name' => 'portrait', 'image' => [1200, 2400], 'canvas' => [1920, 1080]],
    ['name' => 'square', 'image' => [1000, 1000], 'canvas' => [1920, 1080]],
    ['name' => 'smaller source', 'image' => [300, 200], 'canvas' => [1366, 768]],
];

$passed = 0;
foreach ($cases as $case) {
    [$imageWidth, $imageHeight] = $case['image'];
    [$canvasWidth, $canvasHeight] = $case['canvas'];
    $result = slideshow_fit_dimensions($imageWidth, $imageHeight, $canvasWidth, $canvasHeight);

    if ($result['width'] < 1 || $result['height'] < 1
        || $result['width'] > $canvasWidth || $result['height'] > $canvasHeight) {
        fwrite(STDERR, 'FAIL: ' . $case['name'] . ' exceeded the display canvas.' . PHP_EOL);
        exit(1);
    }

    $sourceRatio = $imageWidth / $imageHeight;
    $resultRatio = $result['width'] / $result['height'];
    if (abs($sourceRatio - $resultRatio) / $sourceRatio > 0.01) {
        fwrite(STDERR, 'FAIL: ' . $case['name'] . ' aspect ratio changed.' . PHP_EOL);
        exit(1);
    }

    echo 'PASS: ' . $case['name'] . ' ' . $imageWidth . 'x' . $imageHeight
        . ' -> ' . $result['width'] . 'x' . $result['height'] . PHP_EOL;
    $passed++;
}

try {
    slideshow_fit_dimensions(0, 100, 1920, 1080);
    fwrite(STDERR, 'FAIL: invalid image dimensions were accepted.' . PHP_EOL);
    exit(1);
} catch (InvalidArgumentException) {
    echo 'PASS: invalid dimensions are rejected.' . PHP_EOL;
    $passed++;
}

echo 'RESULT: PASS - ' . $passed . ' slideshow dimension checks.' . PHP_EOL;
