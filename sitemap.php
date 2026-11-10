<?php
/** XML sitemap of the home page and every visible product page (served at /sitemap.xml). */
require __DIR__ . '/includes/bootstrap.php';

$content = load_content();
header('Content-Type: application/xml; charset=utf-8');
$lastmod = is_file(CONTENT_FILE) ? date('Y-m-d', filemtime(CONTENT_FILE)) : date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
echo '  <url><loc>', e(absolute_url('')), '</loc><lastmod>', $lastmod, '</lastmod><priority>1.0</priority></url>', "\n";
foreach ($content['products'] as $p) {
    if ($p['visible']) {
        echo '  <url><loc>', e(absolute_url(product_path($p))), '</loc><lastmod>', $lastmod, '</lastmod><priority>0.8</priority></url>', "\n";
    }
}
echo '</urlset>', "\n";
