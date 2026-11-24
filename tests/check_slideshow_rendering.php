<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$css = file_get_contents($root . DIRECTORY_SEPARATOR . 'slideshow.css');
$page = file_get_contents($root . DIRECTORY_SEPARATOR . 'slideshow.php');
$helpers = file_get_contents($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'slideshow.php');
$security = file_get_contents($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php');

$checks = [
    'slideshow CSS uses the real viewport width' => str_contains($css, 'max-width: 100vw;'),
    'slideshow CSS uses the real viewport height' => str_contains($css, 'max-height: 100vh;'),
    'slideshow image keeps intrinsic sizing unless it must shrink' => str_contains($css, 'width: auto;') && str_contains($css, 'height: auto;'),
    'slideshow image uses contain instead of crop' => str_contains($css, 'object-fit: contain;'),
    'page loads the slideshow stylesheet' => str_contains($page, 'slideshow.css?v=3'),
    'page uses the dedicated slideshow image class' => str_contains($page, 'class="slideshow-photo"'),
    'page enables same-origin CSS and images only for the slideshow' => str_contains($page, 'send_security_headers(true, true);'),
    'photo selection requires a matching registered visitor' => substr_count($helpers, 'INNER JOIN visitors v ON v.id = p.visitor_id') >= 2,
    'photo selection requires the matching visitor consent' => substr_count($helpers, 'INNER JOIN consents c ON c.id = p.consent_id AND c.visitor_id = p.visitor_id') >= 2,
    'photo selection requires active status and consent version' => str_contains($helpers, 'p.status = :status AND c.consent_version = :version'),
    'JavaScript is not added to the slideshow page' => !preg_match('/<script\b|javascript:/i', $page),
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

echo 'RESULT: PASS - ' . $passed . ' slideshow rendering/eligibility checks.' . PHP_EOL;
