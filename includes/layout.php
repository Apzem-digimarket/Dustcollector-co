<?php
/**
 * Shared page layout for the public site: <head>, header navigation and footer.
 * Contact details used across the site are set here.
 */

const SITE_CONTACT = [
    'email' => 'sales@apzem.com',
    'phone' => '+91 97890 66648',
    'phoneHref' => '+919789066648',
    'web' => 'https://www.apzem.com',
    'webLabel' => 'www.apzem.com',
];

function asset_url(string $path): string
{
    $file = ROOT_DIR . '/' . $path;
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * @param array $opts title, description, canonical (site path), image (site path),
 *                    css (list of site paths), jsonld (array), noindex (bool)
 */
function render_head(array $opts): void
{
    $title = $opts['title'] ?? 'Industrial Dust Collection Systems';
    $desc = $opts['description'] ?? '';
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#ffffff">
  <title><?= e($title) ?></title>
<?php if ($desc !== ''): ?>
  <meta name="description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if (!empty($opts['noindex'])): ?>
  <meta name="robots" content="noindex">
<?php endif; ?>
<?php if (isset($opts['canonical'])): ?>
  <link rel="canonical" href="<?= e(absolute_url($opts['canonical'])) ?>">
  <meta property="og:url" content="<?= e(absolute_url($opts['canonical'])) ?>">
<?php endif; ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="APZEM Industrial Dust Control">
  <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="apple-touch-icon" href="<?= e(url('apple-touch-icon.png')) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
<?php if ($desc !== ''): ?>
  <meta property="og:description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if (!empty($opts['image'])): ?>
  <meta property="og:image" content="<?= e(absolute_url($opts['image'])) ?>">
  <meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset_url('assets/site.css')) ?>">
<?php foreach ($opts['css'] ?? [] as $css): ?>
  <link rel="stylesheet" href="<?= e(asset_url($css)) ?>">
<?php endforeach; ?>
<?php if (!empty($opts['jsonld'])): ?>
  <script type="application/ld+json"><?= json_encode($opts['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
</head>
<body>
<?php
}

function render_header(bool $isHome): void
{
    $home = $isHome ? '' : url('');
    ?>
  <div class="topline"></div>
  <header><div class="wrap"><nav aria-label="Main navigation">
    <a class="brand" href="<?= $isHome ? '#top' : e(url('')) ?>" aria-label="APZEM home"><span class="brand-logo"><img src="<?= e(url('assets/aeris-logo.png')) ?>" alt="APZEM" fetchpriority="high"></span></a>
    <div class="links" id="navLinks"><a href="<?= e($home) ?>#approach">Engineering approach</a><a href="<?= e($home) ?>#applications">Equipment range</a><a class="nav-cta" href="<?= $isHome ? '#contact' : '#enquire' ?>">Request a consultation <span class="arrow">↗</span></a></div>
    <button class="menu" id="menu" aria-expanded="false" aria-controls="navLinks">Menu</button>
  </nav></div></header>
<?php
}

function render_footer(): void
{
    ?>
  <footer><div class="wrap foot-inner"><div><span class="foot-brand">INDUSTRIAL DUST CONTROL</span> <span>· Dust collection equipment &amp; systems</span></div><div>Engineered by APZEM</div></div></footer>
  <script src="<?= e(asset_url('assets/site.js')) ?>"></script>
<?php
}
