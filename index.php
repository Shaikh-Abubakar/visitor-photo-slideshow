<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'security.php';

send_security_headers(false, true);
header('Content-Type: text/html; charset=utf-8');

$appName = html_escape((string) app_config('APP_NAME'));
$assetVersion = '9';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f2f0e9">
    <title><?= $appName ?> | Visitor photographs</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $assetVersion ?>">
</head>
<body class="app-body home-body">
<div class="app-site-shell">
    <header class="app-header">
        <p class="app-brand"><?= $appName ?></p>
        <span class="app-header-tag">A shared photo experience</span>
    </header>

    <main class="home-page">
        <section class="home-hero" aria-labelledby="home-title">
            <div>
                <p class="home-kicker">VISITOR PHOTO SLIDESHOW</p>
                <h1 id="home-title">Every visit leaves a story.</h1>
                <p class="home-hero-copy">Register to receive your visitor code, return whenever you like, and share photographs for the public display when you choose.</p>
            </div>
            <div class="home-hero-art" aria-hidden="true">
                <span class="home-art-caption">Moments worth sharing</span>
            </div>
        </section>

        <section class="home-actions" aria-label="Choose what you would like to do">
            <a class="home-action" href="register.php">
                <span class="home-action-mark" aria-hidden="true">01 / NEW VISITOR</span>
                <strong>Register</strong>
                <span>Create your visitor pass and receive the code you will use on future visits.</span>
            </a>
            <a class="home-action" href="identify.php">
                <span class="home-action-mark" aria-hidden="true">02 / RETURNING VISITOR</span>
                <strong>Continue with your code</strong>
                <span>Identify yourself to open your private photo gallery or upload more photographs.</span>
            </a>
            <a class="home-action" href="slideshow.php" target="_blank" rel="noopener noreferrer">
                <span class="home-action-mark" aria-hidden="true">03 / PUBLIC DISPLAY</span>
                <strong>View the slideshow</strong>
                <span>Open the public screen. Only active, approved photographs with valid consent appear.</span>
            </a>
        </section>

        <p class="home-note">Public viewing does not require visitor sign-in. Photograph contributions require registration, consent and administrator review.</p>
    </main>

    <footer class="home-footer">
        <a href="admin/login.php">Administrator access</a>
        <span> · </span>
        Visitor Photo Slideshow System
    </footer>
</div>
</body>
</html>
