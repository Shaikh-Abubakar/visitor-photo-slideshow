<?php
declare(strict_types=1);

/**
 * Shared presentation helpers for authenticated administrator pages.
 * This file contains markup helpers only; authentication, CSRF and headers
 * remain the responsibility of each page and admin/auth.php.
 */
function admin_render_start(string $pageTitle, string $activePage, string $subtitle = ''): void
{
    $appName = admin_e((string) app_config('APP_NAME'));
    $title = admin_e($pageTitle);
    $subtitleHtml = $subtitle !== '' ? '<p class="admin-page-subtitle">' . admin_e($subtitle) . '</p>' : '';

    echo '<!doctype html>';
    echo '<html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="theme-color" content="#f2f0e9">';
    echo '<link rel="stylesheet" href="../assets/css/style.css?v=9">';
    echo '<title>' . $title . ' | ' . $appName . '</title></head>';
    echo '<body class="app-body admin-body">';
    echo '<div class="admin-shell">';
    echo '<header class="admin-topbar">';
    echo '<a class="admin-brand" href="index.php" aria-label="' . $appName . ' dashboard">';
    echo '<span class="admin-brand-mark" aria-hidden="true">V</span>';
    echo '<span class="admin-brand-copy"><strong>' . $appName . '</strong><small>Management console</small></span>';
    echo '</a>';
    echo '<div class="admin-top-actions"><span class="admin-role-label">Administrator</span>';
    echo '<a class="button admin-open-slideshow" href="../slideshow.php" target="_blank" rel="noopener noreferrer">Open public slideshow <span aria-hidden="true">↗</span></a>';
    echo '<form method="post" action="logout.php" class="admin-logout-form">';
    echo '<input type="hidden" name="csrf_token" value="' . admin_e(csrf_token()) . '">';
    echo '<button type="submit" class="button button-secondary admin-logout-button">Log out</button>';
    echo '</form></div></header>';

    echo '<div class="admin-workspace">';
    echo '<aside class="admin-sidebar"><p class="admin-sidebar-label">WORKSPACE</p>';
    echo '<nav class="admin-navigation" aria-label="Administrator navigation">';
    $links = [
        'dashboard' => ['index.php', 'Dashboard', '▦'],
        'visitors' => ['visitors.php', 'Visitors', '♙'],
        'photos' => ['photos.php', 'Photo review', '▧'],
    ];
    foreach ($links as $key => [$href, $label, $symbol]) {
        $current = $activePage === $key;
        echo '<a class="admin-nav-link' . ($current ? ' is-active' : '') . '" href="' . $href . '"' . ($current ? ' aria-current="page"' : '') . '>';
        echo '<span class="admin-nav-symbol" aria-hidden="true">' . $symbol . '</span><span>' . $label . '</span></a>';
    }
    echo '<form method="post" action="logout.php" class="admin-sidebar-logout">';
    echo '<input type="hidden" name="csrf_token" value="' . admin_e(csrf_token()) . '">';
    echo '<button type="submit" class="admin-nav-link admin-nav-logout"><span class="admin-nav-symbol" aria-hidden="true">↪</span><span>Logout</span></button>';
    echo '</form></nav>';
    echo '<div class="admin-sidebar-note"><span class="admin-sidebar-note-mark" aria-hidden="true">●</span><p>Review photos carefully. Public display requires both valid consent and administrator approval.</p></div>';
    echo '</aside>';

    echo '<main class="admin-content"><div class="admin-page-heading"><div><p class="admin-eyebrow">ADMINISTRATION</p><h1>' . $title . '</h1>' . $subtitleHtml . '</div></div>';
}

function admin_render_end(): void
{
    echo '</main></div><footer class="admin-footer">Visitor Photo Slideshow System</footer></div></body></html>';
}

function admin_status_class(string $status): string
{
    return match (strtolower($status)) {
        'approved', 'active', 'valid' => 'status-pill status-success',
        'pending' => 'status-pill status-warning',
        'rejected', 'hidden', 'withdrawn', 'invalid' => 'status-pill status-danger',
        default => 'status-pill status-neutral',
    };
}
