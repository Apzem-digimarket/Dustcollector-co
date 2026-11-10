<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$content = load_content();
$panel = $content['panel'];
$spot = $panel['spotlight'];
$categoryNames = array_column($content['categories'], 'name', 'id');
$products = array_values(array_filter($content['products'], function ($p) { return $p['visible']; }));

// Categories that have at least one visible product, in admin order.
$filterCats = [];
foreach ($content['categories'] as $cat) {
    $count = count(array_filter($products, function ($p) use ($cat) { return $p['category'] === $cat['id']; }));
    if ($count) {
        $filterCats[] = ['id' => $cat['id'], 'name' => $cat['name'], 'count' => $count];
    }
}
?><?php render_head([
    'title' => 'Industrial Dust Collection Systems',
    'description' => 'Industrial dust collection equipment for manufacturing, process, woodworking, and material handling applications.',
    'canonical' => '',
]); ?>
<?php render_header(true); ?>
  <main id="top">
    <section class="hero"><div class="wrap hero-grid">
      <div class="hero-copy"><div class="eyebrow reveal">Industrial dust collection systems</div><h1 class="reveal delay-1">Engineered for<br><em>effective dust</em><br>control.</h1><p class="reveal delay-2">Dust collection equipment and engineered systems for manufacturing and process environments. Configured around your material, capture points, airflow requirements, and site conditions.</p><div class="actions reveal delay-3"><a class="button" href="#contact">Discuss your application <span class="arrow">↗</span></a><a class="text-link" href="#applications">View equipment range <span class="arrow">↓</span></a></div><div class="micro reveal delay-3"><span><i></i> Industrial equipment</span><span><i></i> Application-led system selection</span></div></div>
      <div class="visual reveal delay-2"><img class="hero-product-image" src="assets/products/centralised-dust-system.jpg" alt="Centralised industrial dust collection system with filter units, ducting, blower, and access platforms" fetchpriority="high"></div>
    </div></section>
    <div class="strip"><div class="wrap strip-inner"><div class="strip-label">Dust collection equipment &amp; engineered systems</div><div class="strip-points"><span><b>↗</b> Process-specific selection</span><span><b>↗</b> Multiple collector technologies</span><span><b>↗</b> System components &amp; integration</span></div></div></div>
    <section class="content" id="approach"><div class="wrap"><div class="intro"><div class="reveal"><div class="eyebrow">Engineering approach</div><h2>Specify the system around the process.</h2></div><div class="intro-copy reveal delay-1"><p>Effective dust collection starts with the material and the point of generation. Airflow, capture method, filtration, duct routing, and operating conditions all inform equipment selection.</p><p>Share your process and site requirements to discuss a suitable collector or complete collection system.</p></div></div><div class="benefits">
      <article class="benefit reveal"><div class="num">01 / MDC400</div><h3>Mini dust collector</h3><p>680 m³/h · 1 HP · bag filter · stated filtration efficiency of 99% at 5 microns.</p></article>
      <article class="benefit reveal delay-1"><div class="num">02 / TD840</div><h3>Two-stage dust collector</h3><p>2,000 m³/h · 3 HP · primary cyclone and secondary cartridge filtration with automatic pulse-jet cleaning.</p></article>
      <article class="benefit reveal delay-2"><div class="num">03 / WET TYPE</div><h3>Wet dust collector</h3><p>3,500 m³/h · 7.5 HP · SS304 construction · stated filtration efficiency up to 99% at 3 microns.</p></article>
    </div></div></section>
    <section class="content applications" id="applications"><div class="wrap">
      <div class="app-head"><div class="reveal"><?php if ($panel['eyebrow'] !== ''): ?><div class="eyebrow"><?= e($panel['eyebrow']) ?></div><?php endif; ?><h2><?= e($panel['title']) ?></h2></div><?php if ($panel['intro'] !== ''): ?><p class="reveal delay-1"><?= nl2br(e($panel['intro'])) ?></p><?php endif; ?></div>
<?php if ($spot['show']): ?>
      <div class="spotlight reveal<?= $spot['image'] ? '' : ' no-image' ?>"><div class="spotlight-copy"><?php if ($spot['kicker'] !== ''): ?><div class="spotlight-kicker"><?= e($spot['kicker']) ?></div><?php endif; ?><h3><?= e($spot['title']) ?></h3><?php if ($spot['text'] !== ''): ?><p><?= nl2br(e($spot['text'])) ?></p><?php endif; ?><?php if ($spot['linkText'] !== ''): ?><a class="text-link" href="#product-list"><?= e($spot['linkText']) ?> <span class="arrow">↓</span></a><?php endif; ?></div><?php if ($spot['image']): ?><img class="spotlight-image" src="<?= e($spot['image']) ?>" alt="<?= e($spot['alt']) ?>" loading="lazy"><?php endif; ?></div>
<?php endif; ?>
<?php if ($panel['showFilters'] && count($filterCats) > 1): ?>
      <div class="filters" id="product-filters" role="group" aria-label="Filter products by category">
        <button type="button" class="filter is-active" data-filter="all" aria-pressed="true">All <span><?= count($products) ?></span></button>
<?php foreach ($filterCats as $cat): ?>
        <button type="button" class="filter" data-filter="<?= e($cat['id']) ?>" aria-pressed="false"><?= e($cat['name']) ?> <span><?= $cat['count'] ?></span></button>
<?php endforeach; ?>
      </div>
<?php endif; ?>
      <div class="app-grid app-grid--products" id="product-list">
<?php foreach ($products as $i => $p):
    $classes = 'app app--product reveal' . ($p['image'] ? '' : ' no-photo') . ($i % 4 ? ' delay-' . ($i % 4) : '');
    $number = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
    $catName = $categoryNames[$p['category']] ?? '';
?>
        <a class="<?= $classes ?>" data-tilt data-caption="View <?= e($p['name']) ?>" data-category="<?= e($p['category']) ?>" href="<?= e(url(product_path($p))) ?>"><div class="app-index"><?= e($panel['cardLabel'] !== '' ? $panel['cardLabel'] . ' / ' . $number : $number) ?></div><div class="corner" aria-hidden="true">↗</div><?php if ($p['image']): ?><img class="product-photo" src="<?= e($p['image']) ?>" alt="<?= e($p['alt'] !== '' ? $p['alt'] : $p['name']) ?>" loading="lazy"><?php endif; ?><?php if ($panel['showCategoryOnCards'] && $catName !== ''): ?><div class="product-cat"><?= e($catName) ?></div><?php endif; ?><h3><?= e($p['name']) ?></h3><?php if ($p['description'] !== ''): ?><p class="range-spec"><?= nl2br(e($p['description'])) ?></p><?php endif; ?><span class="card-more">View details <span class="arrow">→</span></span></a>
<?php endforeach; ?>
<?php if (!$products): ?>
        <p class="empty-range">Products will be listed here soon. <a href="#contact">Contact the team</a> to discuss your requirements.</p>
<?php endif; ?>
      </div>
    </div></section>
    <section class="cta" id="contact"><div class="wrap"><div class="contact reveal"><div><div class="eyebrow">Project enquiry</div><h2>Discuss your dust collection requirements.</h2><p>Provide details about your material, process, dust generation points, operating hours, and site conditions to begin a technical discussion.</p></div><div class="contact-actions"><a class="button" href="mailto:sales@apzem.com?subject=Dust%20collection%20project%20enquiry">Email the sales team <span class="arrow">↗</span></a><div class="contact-details"><a href="mailto:sales@apzem.com"><span aria-hidden="true">✉</span>sales@apzem.com</a><a href="tel:+919789066648"><span aria-hidden="true">☎</span>+91 97890 66648</a><a href="https://www.apzem.com" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span>www.apzem.com</a></div></div></div></div></section>
  </main>
<?php render_footer(); ?>
  <script>
    const filters=document.getElementById('product-filters');
    if(filters){filters.addEventListener('click',ev=>{const btn=ev.target.closest('.filter');if(!btn)return;const f=btn.dataset.filter;filters.querySelectorAll('.filter').forEach(b=>{const on=b===btn;b.classList.toggle('is-active',on);b.setAttribute('aria-pressed',String(on))});document.querySelectorAll('#product-list .app--product').forEach(card=>{const show=f==='all'||card.dataset.category===f;card.hidden=!show;if(show)card.classList.add('visible')})})}
  </script>
</body>
</html>
