// Shared site behaviour: mobile menu and scroll-in reveal animations.
(function () {
  const navLinks = document.getElementById('navLinks');
  const menu = document.getElementById('menu');
  if (navLinks && menu) {
    const close = () => { navLinks.classList.remove('open'); menu.setAttribute('aria-expanded', 'false'); menu.textContent = 'Menu'; };
    menu.addEventListener('click', () => {
      const open = navLinks.classList.toggle('open');
      menu.setAttribute('aria-expanded', String(open));
      menu.textContent = open ? 'Close' : 'Menu';
    });
    navLinks.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
  }

  const reveals = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window)) {
    reveals.forEach((el) => el.classList.add('visible'));
    return;
  }
  const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  }), { threshold: 0.14 });
  reveals.forEach((el) => observer.observe(el));
})();

// Tilted cards: elements with [data-tilt] tilt in 3D towards the pointer, scale up slightly,
// and show a caption ([data-caption]) that follows the cursor. Mouse/trackpad only.
(function () {
  const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const cards = document.querySelectorAll('[data-tilt]');
  if (!canHover || reduce || !cards.length) return;

  const AMPLITUDE = 12;      // max tilt in degrees
  const SCALE = 1.045;       // hover zoom (kept small so grid neighbours don't overlap much)

  // Simple damped spring; the same feel as a physics spring (stiffness, damping, mass).
  function spring(stiffness, damping, mass, value) {
    return {
      x: value, v: 0, target: value,
      step(dt) {
        const a = (-stiffness * (this.x - this.target) - damping * this.v) / mass;
        this.v += a * dt;
        this.x += this.v * dt;
      },
      done() { return Math.abs(this.x - this.target) < 0.002 && Math.abs(this.v) < 0.002; },
      snap() { this.x = this.target; this.v = 0; },
    };
  }

  const caption = document.createElement('div');
  caption.className = 'tilt-caption';
  caption.setAttribute('aria-hidden', 'true');
  document.body.appendChild(caption);
  const capRotate = spring(350, 30, 1, 0);
  let capX = 0, capY = 0, lastY = null;

  const running = new Set();
  let frame = null, lastTime = 0;

  function tick(now) {
    const dt = Math.min((now - lastTime) / 1000 || 0.016, 0.05);
    lastTime = now;
    const steps = Math.ceil(dt / (1 / 240));
    running.forEach((s) => {
      for (let i = 0; i < steps; i++) { s.rx.step(dt / steps); s.ry.step(dt / steps); s.sc.step(dt / steps); }
      if (!s.active && s.rx.done() && s.ry.done() && s.sc.done()) {
        s.rx.snap(); s.ry.snap(); s.sc.snap();
        s.el.style.transform = '';
        s.el.style.transition = '';
        s.el.classList.remove('is-tilting');
        running.delete(s);
        return;
      }
      s.el.style.transform = `perspective(900px) rotateX(${s.rx.x.toFixed(3)}deg) rotateY(${s.ry.x.toFixed(3)}deg) scale(${s.sc.x.toFixed(4)})`;
    });
    for (let i = 0; i < steps; i++) capRotate.step(dt / steps);
    capRotate.target *= 0.9;   // ease the swing back to level
    caption.style.transform = `translate3d(${capX}px, ${capY}px, 0) rotate(${capRotate.x.toFixed(2)}deg)`;
    frame = running.size || !capRotate.done() || caption.classList.contains('show') ? requestAnimationFrame(tick) : null;
  }
  function start() {
    if (!frame) { lastTime = performance.now(); frame = requestAnimationFrame(tick); }
  }

  cards.forEach((el) => {
    const s = { el, active: false, rect: null, scroll0: 0, rx: spring(100, 30, 2, 0), ry: spring(100, 30, 2, 0), sc: spring(100, 30, 2, 1) };

    el.addEventListener('pointerenter', (ev) => {
      if (ev.pointerType !== 'mouse') return;
      // Measure the card before it is transformed, so the tilt doesn't feed back on itself.
      if (!running.has(s)) {
        s.rect = el.getBoundingClientRect();
        s.scroll0 = window.scrollY;
      }
      s.active = true;
      el.style.transition = 'border-color .3s, box-shadow .3s, opacity .7s';
      el.classList.add('is-tilting');
      s.sc.target = SCALE;
      running.add(s);
      caption.textContent = el.dataset.caption || '';
      caption.classList.toggle('show', !!caption.textContent);
      lastY = null;
      start();
    });

    el.addEventListener('pointermove', (ev) => {
      if (!s.active || !s.rect) return;
      const r = s.rect;
      const top = r.top - (window.scrollY - s.scroll0);
      const offsetX = ev.clientX - (r.left + r.width / 2);
      const offsetY = ev.clientY - (top + r.height / 2);
      s.ry.target = Math.max(-1, Math.min(1, offsetX / (r.width / 2))) * AMPLITUDE;
      s.rx.target = Math.max(-1, Math.min(1, offsetY / (r.height / 2))) * -AMPLITUDE;
      capX = ev.clientX + 16;
      capY = ev.clientY + 18;
      if (lastY !== null) capRotate.target = Math.max(-14, Math.min(14, -(ev.clientY - lastY) * 0.6));
      lastY = ev.clientY;
      start();
    });

    el.addEventListener('pointerleave', () => {
      if (!s.active) return;
      s.active = false;
      s.rx.target = 0; s.ry.target = 0; s.sc.target = 1;
      caption.classList.remove('show');
      capRotate.target = 0;
      start();
    });
  });
})();
