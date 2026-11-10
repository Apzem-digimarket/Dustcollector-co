<?php
/**
 * Product detail page. Every product created in the admin panel gets one automatically:
 *   /products/<slug>        (pretty URL, via .htaccess or router.php)
 *   /product.php?p=<slug>   (always works)
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$content = load_content();
$slug = strtolower(trim((string) ($_GET['p'] ?? ''), '/'));
$categoryNames = array_column($content['categories'], 'name', 'id');
$visible = array_values(array_filter($content['products'], function ($p) { return $p['visible']; }));

$product = null;
foreach ($content['products'] as $p) {
    if ($p['slug'] === $slug) {
        $product = $p;
        break;
    }
}

// Hidden products can be previewed by a logged-in admin only.
$isPreview = false;
if ($product && !$product['visible']) {
    if (isset($_COOKIE['dc_admin'])) {
        start_admin_session();
        $isPreview = is_logged_in();
    }
    if (!$isPreview) {
        $product = null;
    }
}

/* ---------- 404 ---------- */
if (!$product) {
    http_response_code(404);
    render_head(['title' => ($slug !== '' ? 'Product not found' : 'Page not found') . ' · Industrial Dust Collection', 'noindex' => true, 'css' => ['assets/product.css']]);
    render_header(false);
    ?>
  <main class="pd pd-404">
    <section class="wrap pd-404-inner">
      <div class="eyebrow">Error 404</div>
<?php if ($slug !== ''): ?>
      <h1>This product isn’t available.</h1>
      <p>It may have been renamed or removed from the range. Browse the current equipment or contact the team.</p>
<?php else: ?>
      <h1>This page doesn’t exist.</h1>
      <p>The link may be mistyped or out of date. Browse the equipment range or contact the team.</p>
<?php endif; ?>
      <div class="pd-actions"><a class="button" href="<?= e(url('')) ?>#applications">View equipment range <span class="arrow">→</span></a><a class="text-link" href="mailto:<?= e(SITE_CONTACT['email']) ?>">Email the sales team <span class="arrow">↗</span></a></div>
    </section>
  </main>
<?php
    render_footer();
    echo "</body>\n</html>\n";
    exit;
}

/* ---------- page data ---------- */
$panel = $content['panel'];
$catName = $categoryNames[$product['category']] ?? '';
$position = array_search($product['id'], array_column($visible, 'id'), true);
$number = $position === false ? '' : str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT);
$label = $number === '' ? 'Draft' : ($panel['cardLabel'] !== '' ? $panel['cardLabel'] . ' / ' . $number : $number);

$images = array_values(array_unique(array_filter(array_merge([$product['image']], $product['gallery']))));
$alt = $product['alt'] !== '' ? $product['alt'] : $product['name'];
$highlights = array_slice(array_values(array_filter($product['specs'], function ($s) { return $s['highlight'] && $s['value'] !== ''; })), 0, 4);
$overview = $product['overview'] !== '' ? $product['overview'] : '';

$prev = $next = null;
if ($position !== false && count($visible) > 1) {
    $prev = $visible[($position - 1 + count($visible)) % count($visible)];
    $next = $visible[($position + 1) % count($visible)];
}

// Related: same category first, then the rest of the range.
$related = [];
foreach ($visible as $p) {
    if ($p['id'] !== $product['id'] && $product['category'] !== '' && $p['category'] === $product['category']) {
        $related[] = $p;
    }
}
foreach ($visible as $p) {
    if (count($related) >= 8) {
        break;
    }
    if ($p['id'] !== $product['id'] && !in_array($p, $related, true)) {
        $related[] = $p;
    }
}
$related = array_slice($related, 0, 8);

$sections = ['overview' => 'Overview'];
if ($product['specs']) {
    $sections['specs'] = 'Specifications';
}
if ($product['features']) {
    $sections['features'] = 'Features';
}
if ($product['applications']) {
    $sections['applications'] = 'Applications';
}
$sections['process'] = 'Selection';
$sections['enquire'] = 'Request a quote';

/** Split "1,190 CMH" into prefix / number / suffix so the number can animate. */
function stat_parts(string $value): array
{
    if (preg_match('/^(.*?)(\d{1,3}(?:,\d{3})+|\d+)(\.\d+)?(.*)$/su', $value, $m)) {
        $decimals = $m[3] !== '' ? strlen($m[3]) - 1 : 0;
        $target = (float) str_replace(',', '', $m[2] . $m[3]);
        return [$m[1], $m[2] . $m[3], $m[4], $target, $decimals, strpos($m[2], ',') !== false];
    }
    return [$value, '', '', 0, 0, false];
}

$pagePath = product_path($product);
// Breadcrumb structured data (a Product schema without prices/reviews is flagged as invalid by Google).
$jsonld = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => absolute_url('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Equipment range', 'item' => absolute_url('') . '#applications'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $product['name'], 'item' => absolute_url($pagePath)],
    ],
];

render_head([
    'title' => $product['name'] . ' · Industrial Dust Collection · APZEM',
    'description' => $product['description'] !== '' ? $product['description'] : $product['name'] . ' from the APZEM dust collection range.',
    'canonical' => $pagePath,
    'image' => $images[0] ?? '',
    'css' => ['assets/product.css'],
    'jsonld' => $jsonld,
    'noindex' => !$product['visible'],
]);
?>
  <div class="pd-progress" aria-hidden="true"><span id="pdProgress"></span></div>
<?php render_header(false); ?>
  <main id="top" class="pd">
<?php if ($isPreview): ?>
    <div class="pd-draft"><div class="wrap">Draft preview — this product is hidden from visitors. Turn on “Show on website” in the admin panel to publish it.</div></div>
<?php endif; ?>

    <!-- Hero -->
    <section class="pd-hero">
      <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb">
          <a href="<?= e(url('')) ?>">Home</a><span aria-hidden="true">/</span>
          <a href="<?= e(url('')) ?>#applications">Equipment range</a><span aria-hidden="true">/</span>
<?php if ($catName !== ''): ?>
          <span><?= e($catName) ?></span><span aria-hidden="true">/</span>
<?php endif; ?>
          <span aria-current="page"><?= e($product['name']) ?></span>
        </nav>
        <div class="pd-hero-grid">
          <div class="pd-copy">
            <div class="pd-kicker reveal"><span class="pd-num"><?= e($label) ?></span><?php if ($catName !== ''): ?><span class="pd-cat"><?= e($catName) ?></span><?php endif; ?></div>
            <h1 class="reveal delay-1"><?= e($product['name']) ?></h1>
<?php if ($product['description'] !== ''): ?>
            <p class="pd-lead reveal delay-2"><?= nl2br(e($product['description'])) ?></p>
<?php endif; ?>
<?php if ($highlights): ?>
            <ul class="pd-chips reveal delay-2">
<?php foreach (array_slice($highlights, 0, 3) as $h): ?>
              <li><b><?= e($h['value']) ?></b><span><?= e($h['label']) ?></span></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
            <div class="pd-actions reveal delay-3">
              <a class="button" href="#enquire">Request a quote <span class="arrow">↗</span></a>
<?php if ($product['link'] !== ''): ?>
              <a class="button button-outline" href="<?= e($product['link']) ?>" target="_blank" rel="noopener">Download brochure <span class="arrow">↓</span></a>
<?php else: ?>
              <a class="text-link" href="#<?= $product['specs'] ? 'specs' : 'overview' ?>"><?= $product['specs'] ? 'View specifications' : 'Learn more' ?> <span class="arrow">↓</span></a>
<?php endif; ?>
            </div>
            <div class="pd-quick reveal delay-3">
              <a href="tel:<?= e(SITE_CONTACT['phoneHref']) ?>"><span aria-hidden="true">☎</span><?= e(SITE_CONTACT['phone']) ?></a>
              <a href="mailto:<?= e(SITE_CONTACT['email']) ?>?subject=<?= rawurlencode('Enquiry: ' . $product['name']) ?>"><span aria-hidden="true">✉</span><?= e(SITE_CONTACT['email']) ?></a>
            </div>
          </div>

          <div class="pd-media reveal delay-2">
            <div class="pd-stage" id="pdStage">
              <div class="pd-glow" aria-hidden="true"></div>
              <span class="airline a" aria-hidden="true"></span><span class="airline b" aria-hidden="true"></span><span class="airline c" aria-hidden="true"></span>
<?php if ($images): ?>
              <button type="button" class="pd-main" id="pdMain" aria-label="Enlarge image of <?= e($product['name']) ?>">
                <img id="pdMainImg" src="<?= e(url($images[0])) ?>" alt="<?= e($alt) ?>" fetchpriority="high">
              </button>
              <span class="pd-zoom-hint" aria-hidden="true">⤢ Click to enlarge</span>
<?php else: ?>
              <svg class="pd-schematic" viewBox="0 0 320 320" role="img" aria-label="Illustration of a dust collector">
                <defs><linearGradient id="g1" x1="0" x2="1"><stop offset="0" stop-color="#e7eeeb"/><stop offset=".5" stop-color="#fff"/><stop offset="1" stop-color="#dbe4e0"/></linearGradient></defs>
                <ellipse cx="160" cy="292" rx="96" ry="10" fill="#18304714"/>
                <rect x="98" y="48" width="124" height="136" rx="10" fill="url(#g1)" stroke="#9fb0ad" stroke-width="2"/>
                <rect x="114" y="66" width="92" height="34" rx="5" fill="#f4f8f6" stroke="#b5c3c0"/>
                <rect x="124" y="76" width="30" height="14" rx="2" fill="#28764f" opacity=".85"/>
                <circle cx="188" cy="83" r="8" fill="none" stroke="#71848a" stroke-width="2"/>
                <path d="M98 184h124l-34 62h-56z" fill="url(#g1)" stroke="#9fb0ad" stroke-width="2"/>
                <rect x="138" y="246" width="44" height="36" rx="5" fill="#e8eeec" stroke="#9fb0ad" stroke-width="2"/>
                <path d="M222 92h34a14 14 0 0 1 14 14v24" fill="none" stroke="#9fb0ad" stroke-width="12" stroke-linecap="round"/>
                <path class="flow" d="M40 120h50M30 150h60M44 180h46" stroke="#28764f" stroke-width="2" stroke-linecap="round" stroke-dasharray="6 10"/>
              </svg>
<?php endif; ?>
<?php if ($highlights): $badge = end($highlights); ?>
              <div class="pd-badge"><b><?= e($badge['value']) ?></b><span><?= e($badge['label']) ?></span></div>
<?php endif; ?>
            </div>
<?php if (count($images) > 1): ?>
            <div class="pd-thumbs" role="list" aria-label="Product images">
<?php foreach ($images as $i => $img): ?>
              <button type="button" role="listitem" class="pd-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-index="<?= $i ?>" aria-label="Show image <?= $i + 1 ?>"><img src="<?= e(url($img)) ?>" alt="" loading="lazy"></button>
<?php endforeach; ?>
            </div>
<?php endif; ?>
          </div>
        </div>
      </div>
    </section>

<?php if ($highlights): ?>
    <!-- Key figures -->
    <section class="pd-stats" aria-label="Key figures">
      <div class="wrap pd-stats-grid count-<?= count($highlights) ?>">
<?php foreach ($highlights as $i => $h):
    [$pre, $num, $post, $target, $decimals, $commas] = stat_parts($h['value']); ?>
        <div class="pd-stat reveal<?= $i ? ' delay-' . min($i, 3) : '' ?>">
          <div class="pd-stat-value"><?= e($pre) ?><?php if ($num !== ''): ?><span class="count" data-target="<?= e((string) $target) ?>" data-decimals="<?= $decimals ?>" data-commas="<?= $commas ? 1 : 0 ?>"><?= e($num) ?></span><?php endif; ?><?= e($post) ?></div>
          <div class="pd-stat-label"><?= e($h['label']) ?></div>
        </div>
<?php endforeach; ?>
      </div>
    </section>
<?php endif; ?>

    <!-- Section navigation -->
    <nav class="pd-subnav" id="pdSubnav" aria-label="On this page">
      <div class="wrap pd-subnav-inner">
        <span class="pd-subnav-title"><?= e($product['name']) ?></span>
        <div class="pd-subnav-links">
<?php foreach ($sections as $id => $title): ?>
          <a href="#<?= $id ?>" data-section="<?= $id ?>"<?= $id === 'enquire' ? ' class="is-cta"' : '' ?>><?= e($title) ?></a>
<?php endforeach; ?>
        </div>
      </div>
    </nav>

    <!-- Overview -->
    <section class="pd-section" id="overview">
      <div class="wrap pd-overview">
        <div class="reveal">
          <div class="eyebrow">Overview</div>
          <h2>Product overview</h2>
        </div>
        <div class="pd-overview-copy reveal delay-1">
<?php if ($overview !== ''): ?>
<?php foreach (preg_split('/\n\s*\n/', $overview) as $para): ?>
          <p><?= nl2br(e(trim($para))) ?></p>
<?php endforeach; ?>
<?php elseif ($product['description'] !== ''): ?>
          <p class="pd-big"><?= nl2br(e($product['description'])) ?></p>
<?php endif; ?>
          <p class="pd-note">Final equipment selection depends on an application-specific engineering review of your material, capture points, airflow requirements and site conditions.</p>
        </div>
      </div>
    </section>

<?php if ($product['specs']): ?>
    <!-- Specifications -->
    <section class="pd-section pd-alt" id="specs">
      <div class="wrap">
        <div class="pd-head reveal">
          <div><div class="eyebrow">Specifications</div><h2>Technical data</h2></div>
          <div class="pd-head-actions">
            <button type="button" class="pd-tool" id="copySpecs">⧉ Copy specs</button>
            <button type="button" class="pd-tool" onclick="window.print()">⎙ Print / save PDF</button>
          </div>
        </div>
        <div class="pd-specs-layout">
          <dl class="pd-specs reveal">
<?php foreach ($product['specs'] as $s): ?>
            <div class="pd-spec<?= $s['highlight'] ? ' is-key' : '' ?>"><dt><?= e($s['label']) ?></dt><dd><?= e($s['value']) ?></dd></div>
<?php endforeach; ?>
          </dl>
          <aside class="pd-spec-aside reveal delay-1">
            <div class="pd-aside-icon" aria-hidden="true">↯</div>
            <h3>Need a different configuration?</h3>
            <p>Airflow, filtration and construction can be reviewed against your process duty. Share your requirements and the team will advise on a suitable model.</p>
            <a class="button" href="#enquire">Discuss your application <span class="arrow">↗</span></a>
<?php if ($product['link'] !== ''): ?>
            <a class="text-link" href="<?= e($product['link']) ?>" target="_blank" rel="noopener">Download brochure <span class="arrow">↓</span></a>
<?php endif; ?>
          </aside>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($product['features']): ?>
    <!-- Features -->
    <section class="pd-section" id="features">
      <div class="wrap">
        <div class="pd-head reveal"><div><div class="eyebrow">Features</div><h2>Why it works</h2></div></div>
        <div class="pd-features">
<?php foreach ($product['features'] as $i => $f): ?>
          <article class="pd-feature reveal<?= $i % 3 ? ' delay-' . ($i % 3) : '' ?>">
            <span class="pd-feature-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
<?php if ($f['title'] !== ''): ?><h3><?= e($f['title']) ?></h3><?php endif; ?>
<?php if ($f['text'] !== ''): ?><p><?= nl2br(e($f['text'])) ?></p><?php endif; ?>
          </article>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($product['applications']): ?>
    <!-- Applications -->
    <section class="pd-section pd-alt" id="applications">
      <div class="wrap pd-apps">
        <div class="reveal"><div class="eyebrow">Applications</div><h2>Where it’s used</h2></div>
        <ul class="pd-tags reveal delay-1">
<?php foreach ($product['applications'] as $tag): ?>
          <li><?= e($tag) ?></li>
<?php endforeach; ?>
        </ul>
      </div>
    </section>
<?php endif; ?>

    <!-- Selection process -->
    <section class="pd-section pd-process" id="process">
      <div class="wrap">
        <div class="pd-head reveal"><div><div class="eyebrow">Selection</div><h2>Specified around your process.</h2></div></div>
        <ol class="pd-steps">
          <li class="reveal"><span>01</span><h3>Share your application</h3><p>Tell us about your material, process, dust generation points, operating hours and site conditions.</p></li>
          <li class="reveal delay-1"><span>02</span><h3>Engineering review</h3><p>Airflow, capture method, filtration, duct routing and operating conditions are assessed together.</p></li>
          <li class="reveal delay-2"><span>03</span><h3>Recommendation</h3><p>Receive a suitable collector, or a complete collection system, configured for your requirements.</p></li>
        </ol>
      </div>
    </section>

    <!-- Enquiry -->
    <section class="pd-section" id="enquire">
      <div class="wrap">
        <div class="pd-enquire reveal">
          <div class="pd-enquire-copy">
            <div class="eyebrow">Request a quote</div>
            <h2>Interested in the <?= e($product['name']) ?>?</h2>
            <p>Send your requirements and the sales team will get back to you with model-specific data and a recommendation.</p>
            <div class="contact-details">
              <a href="mailto:<?= e(SITE_CONTACT['email']) ?>"><span aria-hidden="true">✉</span><?= e(SITE_CONTACT['email']) ?></a>
              <a href="tel:<?= e(SITE_CONTACT['phoneHref']) ?>"><span aria-hidden="true">☎</span><?= e(SITE_CONTACT['phone']) ?></a>
              <a href="<?= e(SITE_CONTACT['web']) ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span><?= e(SITE_CONTACT['webLabel']) ?></a>
            </div>
          </div>
          <form class="pd-form" id="quoteForm" data-email="<?= e(SITE_CONTACT['email']) ?>" data-product="<?= e($product['name']) ?>" novalidate>
            <div class="pd-form-grid">
              <label><span>Name *</span><input name="name" required autocomplete="name"></label>
              <label><span>Company</span><input name="company" autocomplete="organization"></label>
              <label><span>Email *</span><input name="email" type="email" required autocomplete="email"></label>
              <label><span>Phone</span><input name="phone" type="tel" autocomplete="tel"></label>
              <label><span>Material / dust type</span><input name="material" placeholder="e.g. wood dust, metal grinding"></label>
              <label><span>Required airflow</span><input name="airflow" placeholder="e.g. 2,000 CMH"></label>
              <label class="full"><span>Message</span><textarea name="message" rows="4" placeholder="Process, number of capture points, operating hours, site conditions…"></textarea></label>
            </div>
            <p class="pd-form-error" id="quoteError" role="alert" hidden></p>
            <button class="button" type="submit">Send enquiry <span class="arrow">↗</span></button>
            <p class="pd-form-note">Opens your email app with these details filled in, addressed to <?= e(SITE_CONTACT['email']) ?>.</p>
          </form>
        </div>
      </div>
    </section>

<?php if ($related): ?>
    <!-- Related products -->
    <section class="pd-section pd-alt pd-related" aria-labelledby="relatedTitle">
      <div class="wrap">
        <div class="pd-head reveal">
          <div><div class="eyebrow">Explore the range</div><h2 id="relatedTitle"><?= $catName !== '' ? 'More ' . e(mb_strtolower($catName, 'UTF-8')) : 'Related equipment' ?></h2></div>
          <div class="pd-carousel-btns"><button type="button" class="pd-arrow" data-dir="-1" aria-label="Scroll left">←</button><button type="button" class="pd-arrow" data-dir="1" aria-label="Scroll right">→</button></div>
        </div>
        <div class="pd-track" id="pdTrack">
<?php foreach ($related as $r): $rCat = $categoryNames[$r['category']] ?? ''; ?>
          <a class="pd-card" data-tilt data-caption="View <?= e($r['name']) ?>" href="<?= e(url(product_path($r))) ?>">
            <div class="pd-card-img"><?php if ($r['image']): ?><img src="<?= e(url($r['image'])) ?>" alt="" loading="lazy"><?php else: ?><span>No photo</span><?php endif; ?></div>
<?php if ($rCat !== ''): ?><div class="product-cat"><?= e($rCat) ?></div><?php endif; ?>
            <h3><?= e($r['name']) ?></h3>
            <span class="card-more">View details <span class="arrow">→</span></span>
          </a>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($prev && $next): ?>
    <nav class="pd-pager wrap" aria-label="Other products">
      <a class="pd-pager-link" href="<?= e(url(product_path($prev))) ?>"><span>← Previous</span><b><?= e($prev['name']) ?></b></a>
      <a class="pd-pager-link next" href="<?= e(url(product_path($next))) ?>"><span>Next →</span><b><?= e($next['name']) ?></b></a>
    </nav>
<?php endif; ?>
  </main>

  <!-- Mobile quick actions -->
  <div class="pd-mobilebar" id="pdMobilebar">
    <a class="button" href="#enquire">Request a quote</a>
    <a class="pd-call" href="tel:<?= e(SITE_CONTACT['phoneHref']) ?>" aria-label="Call <?= e(SITE_CONTACT['phone']) ?>">☎</a>
  </div>

<?php if ($images): ?>
  <!-- Image viewer -->
  <div class="pd-lightbox" id="pdLightbox" role="dialog" aria-modal="true" aria-label="Product images" hidden>
    <button type="button" class="lb-close" aria-label="Close">✕</button>
<?php if (count($images) > 1): ?>
    <button type="button" class="lb-nav prev" aria-label="Previous image">←</button>
    <button type="button" class="lb-nav next" aria-label="Next image">→</button>
<?php endif; ?>
    <figure class="lb-figure"><img id="lbImg" src="" alt="<?= e($alt) ?>"><figcaption><span id="lbCount"></span> · Click image to zoom</figcaption></figure>
  </div>
  <script type="application/json" id="pdImages"><?= json_encode(array_map('url', $images), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php render_footer(); ?>
  <script src="<?= e(asset_url('assets/product.js')) ?>"></script>
</body>
</html>
