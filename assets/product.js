// Product page interactions.
(function () {
  'use strict';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  /* Reading progress bar */
  const progress = $('#pdProgress');
  const subnav = $('#pdSubnav');
  const mobilebar = $('#pdMobilebar');
  const enquire = $('#enquire');
  let ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const max = document.documentElement.scrollHeight - innerHeight;
      if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(scrollY / max, 1) : 0})`;
      if (subnav) subnav.classList.toggle('is-stuck', subnav.getBoundingClientRect().top <= 80);
      if (mobilebar && enquire) {
        const r = enquire.getBoundingClientRect();
        mobilebar.classList.toggle('is-hidden', scrollY < 300 || (r.top < innerHeight && r.bottom > 0));
      }
      ticking = false;
    });
  }
  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', onScroll);
  onScroll();

  /* Section navigation: highlight the section in view */
  const links = $$('.pd-subnav-links a[data-section]');
  // The active section is the last one whose top has passed under the sticky bars.
  const targets = links.map((a) => document.getElementById(a.dataset.section)).filter(Boolean);
  let activeId = null;
  function spy() {
    let id = null;
    targets.forEach((s) => { if (s.getBoundingClientRect().top <= 170) id = s.id; });
    if (targets.length && innerHeight + scrollY >= document.documentElement.scrollHeight - 4) id = targets[targets.length - 1].id;
    if (id === activeId) return;
    activeId = id;
    links.forEach((a) => {
      const on = a.dataset.section === id;
      a.classList.toggle('is-active', on && !a.classList.contains('is-cta'));
      const bar = a.parentElement;
      if (on && bar.scrollWidth > bar.clientWidth) bar.scrollTo({ left: a.offsetLeft - 16, behavior: 'smooth' });
    });
  }
  if (targets.length) {
    addEventListener('scroll', () => requestAnimationFrame(spy), { passive: true });
    spy();
  }

  /* Count-up figures */
  const counters = $$('.count[data-target]');
  function format(n, decimals, commas) {
    const fixed = n.toFixed(decimals);
    if (!commas) return fixed;
    const [i, d] = fixed.split('.');
    return i.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (d ? '.' + d : '');
  }
  if (counters.length && !reduceMotion && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (!e.isIntersecting) return;
      io.unobserve(e.target);
      const el = e.target;
      const target = parseFloat(el.dataset.target);
      const decimals = +el.dataset.decimals;
      const commas = el.dataset.commas === '1';
      const final = el.textContent;
      const start = performance.now();
      const dur = 1400;
      (function step(now) {
        const t = Math.min((now - start) / dur, 1);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = t < 1 ? format(target * eased, decimals, commas) : final;
        if (t < 1) requestAnimationFrame(step);
      })(start);
    }), { threshold: 0.6 });
    counters.forEach((c) => io.observe(c));
  }

  /* Image stage: tilt + gallery */
  const stage = $('#pdStage');
  const mainImg = $('#pdMainImg');
  const thumbs = $$('.pd-thumb');
  const imagesEl = $('#pdImages');
  const images = imagesEl ? JSON.parse(imagesEl.textContent) : [];
  let current = 0;

  if (stage && !reduceMotion && matchMedia('(hover: hover)').matches) {
    stage.addEventListener('pointermove', (ev) => {
      const r = stage.getBoundingClientRect();
      const x = (ev.clientX - r.left) / r.width - 0.5;
      const y = (ev.clientY - r.top) / r.height - 0.5;
      stage.classList.add('is-tilting');
      stage.style.setProperty('--ry', (x * 8).toFixed(2) + 'deg');
      stage.style.setProperty('--rx', (-y * 8).toFixed(2) + 'deg');
    });
    stage.addEventListener('pointerleave', () => {
      stage.classList.remove('is-tilting');
      stage.style.setProperty('--rx', '0deg');
      stage.style.setProperty('--ry', '0deg');
    });
  }

  function show(i) {
    if (!mainImg || !images.length) return;
    current = (i + images.length) % images.length;
    thumbs.forEach((t, n) => t.classList.toggle('is-active', n === current));
    if (mainImg.getAttribute('src') === images[current]) return;
    mainImg.classList.add('is-swapping');
    setTimeout(() => {
      mainImg.src = images[current];
      mainImg.onload = () => mainImg.classList.remove('is-swapping');
      setTimeout(() => mainImg.classList.remove('is-swapping'), 400);
    }, 180);
  }
  thumbs.forEach((t) => {
    t.addEventListener('click', () => show(+t.dataset.index));
    t.addEventListener('mouseenter', () => show(+t.dataset.index));
  });

  /* Lightbox */
  const lb = $('#pdLightbox');
  const lbImg = $('#lbImg');
  const lbCount = $('#lbCount');
  let lastFocus = null;
  function lbShow(i) {
    current = (i + images.length) % images.length;
    lbImg.classList.remove('zoomed');
    lbImg.src = images[current];
    lbCount.textContent = `${current + 1} / ${images.length}`;
  }
  function lbOpen() {
    if (!lb) return;
    lastFocus = document.activeElement;
    lb.hidden = false;
    document.body.classList.add('lb-open');
    lbShow(current);
    $('.lb-close', lb).focus();
  }
  function lbClose() {
    lb.hidden = true;
    document.body.classList.remove('lb-open');
    show(current);
    if (lastFocus) lastFocus.focus();
  }
  if (lb) {
    $('#pdMain').addEventListener('click', lbOpen);
    $('.lb-close', lb).addEventListener('click', lbClose);
    const prev = $('.lb-nav.prev', lb);
    const next = $('.lb-nav.next', lb);
    if (prev) prev.addEventListener('click', () => lbShow(current - 1));
    if (next) next.addEventListener('click', () => lbShow(current + 1));
    lb.addEventListener('click', (ev) => { if (ev.target === lb) lbClose(); });
    lbImg.addEventListener('click', (ev) => {
      const r = lbImg.getBoundingClientRect();
      lbImg.style.transformOrigin = `${((ev.clientX - r.left) / r.width) * 100}% ${((ev.clientY - r.top) / r.height) * 100}%`;
      lbImg.classList.toggle('zoomed');
    });
    document.addEventListener('keydown', (ev) => {
      if (lb.hidden) return;
      if (ev.key === 'Escape') lbClose();
      else if (ev.key === 'ArrowLeft' && images.length > 1) lbShow(current - 1);
      else if (ev.key === 'ArrowRight' && images.length > 1) lbShow(current + 1);
      else if (ev.key === 'Tab') {   // keep focus inside the viewer
        const f = $$('button', lb);
        const i = f.indexOf(document.activeElement);
        ev.preventDefault();
        f[(i + (ev.shiftKey ? -1 : 1) + f.length) % f.length].focus();
      }
    });
    let x0 = null;
    lb.addEventListener('touchstart', (ev) => { x0 = ev.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', (ev) => {
      if (x0 === null || images.length < 2) return;
      const dx = ev.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 50) lbShow(current + (dx < 0 ? 1 : -1));
      x0 = null;
    });
  }

  /* Copy specifications */
  const copyBtn = $('#copySpecs');
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const title = $('.pd h1').textContent.trim();
      const rows = $$('.pd-spec').map((r) => `${$('dt', r).textContent.trim()}: ${$('dd', r).textContent.trim()}`);
      const text = `${title}\n${rows.join('\n')}\n${location.href}`;
      const label = copyBtn.textContent;
      try {
        await navigator.clipboard.writeText(text);
        copyBtn.textContent = '✓ Copied';
      } catch (err) {
        copyBtn.textContent = 'Copy not available';
      }
      setTimeout(() => (copyBtn.textContent = label), 1800);
    });
  }

  /* Quote form → pre-filled email */
  const form = $('#quoteForm');
  if (form) {
    const error = $('#quoteError');
    form.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      const bad = [];
      if (!String(data.name || '').trim()) bad.push(form.elements.name);
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(data.email || '').trim())) bad.push(form.elements.email);
      $$('input', form).forEach((i) => i.classList.toggle('invalid', bad.includes(i)));
      if (bad.length) {
        error.textContent = 'Please enter your name and a valid email address.';
        error.hidden = false;
        bad[0].focus();
        return;
      }
      error.hidden = true;
      const product = form.dataset.product;
      const opt = (label, value) => (String(value || '').trim() ? `${label}: ${String(value).trim()}` : null);
      const lines = [
        `Product: ${product}`,
        `Page: ${location.href.split('#')[0]}`,
        '',
        opt('Name', data.name),
        opt('Company', data.company),
        opt('Email', data.email),
        opt('Phone', data.phone),
        opt('Material / dust type', data.material),
        opt('Required airflow', data.airflow),
        '',
        String(data.message || '').trim(),
      ].filter((l) => l !== null);
      const href = `mailto:${form.dataset.email}?subject=${encodeURIComponent('Quote request: ' + product)}&body=${encodeURIComponent(lines.join('\n'))}`;
      location.href = href;
    });
    $$('input', form).forEach((i) => i.addEventListener('input', () => i.classList.remove('invalid')));
  }

  /* Related products carousel */
  const track = $('#pdTrack');
  if (track) {
    const btns = $$('.pd-arrow');
    const update = () => {
      const max = track.scrollWidth - track.clientWidth - 2;
      btns.forEach((b) => (b.disabled = b.dataset.dir === '-1' ? track.scrollLeft <= 2 : track.scrollLeft >= max));
    };
    btns.forEach((b) => b.addEventListener('click', () => track.scrollBy({ left: +b.dataset.dir * track.clientWidth * 0.8 })));
    track.addEventListener('scroll', update, { passive: true });
    addEventListener('resize', update);
    update();
  }
})();
