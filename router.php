<?php
/**
 * LOCAL PREVIEW ONLY. Not needed on cPanel, where .htaccess does the same job.
 * Makes PHP's built-in server behave like the live Apache setup:
 *   PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8771 router.php
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$root = __DIR__;

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

function deny(): bool
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    return true;
}

// Private folders, hidden files, notes/backups/logs, this router, and scripts in uploads.
if (preg_match('~^/(data|includes)(/|$)~', $path)
    || preg_match('~/\.~', $path)
    || preg_match('~\.(md|log|zip|bak|tmp|sh)$~i', $path)
    || $path === '/router.php'
    || preg_match('~^/uploads/.*\.(php\d?|phtml|phar)$~i', $path)
    || strpos($path, '..') !== false) {
    return deny();
}
if ($path === '/uploads' || $path === '/uploads/') {
    return deny();
}

define('PRETTY_URLS', true);

// Clean product URLs, sitemap and home.
if (preg_match('~^/products/([A-Za-z0-9-]+)/?$~', $path, $m)) {
    $_GET['p'] = $m[1];
    header('X-Frame-Options: SAMEORIGIN');
    require $root . '/product.php';
    return true;
}
if ($path === '/products' || $path === '/products/' || $path === '/' || $path === '/index.php') {
    header('X-Frame-Options: SAMEORIGIN');
    require $root . '/index.php';
    return true;
}
if ($path === '/sitemap.xml') {
    require $root . '/sitemap.php';
    return true;
}
if ($path === '/admin') {
    header('Location: /admin/', true, 301);
    return true;
}
if ($path === '/admin/' || $path === '/admin/index.php') {
    require $root . '/admin/index.php';
    return true;
}

// Real files (images, CSS, JS, other PHP scripts) are served as they are.
$file = realpath($root . $path);
if ($file !== false && strpos($file, $root) === 0 && is_file($file)) {
    return false;
}

// Anything else: the site's branded "not found" page.
$_GET['p'] = '';
require $root . '/product.php';
return true;
