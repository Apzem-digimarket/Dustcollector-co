(() => {
  'use strict';

  const app = document.getElementById('app');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const initial = JSON.parse(document.getElementById('initial-data').textContent);
  let content = initial.content;
  let email = initial.email;

  const ui = { search: '', category: 'all', panelDraft: null, panelDirty: false };

  /* ---------- helpers ---------- */

  // Build DOM nodes without innerHTML so product text can never inject markup.
  function h(tag, attrs, ...children) {
    const el = document.createElement(tag);
    for (const [key, val] of Object.entries(attrs || {})) {
      if (val == null || val === false) continue;
      if (key === 'class') el.className = val;
      else if (key.startsWith('on')) el.addEventListener(key.slice(2), val);
      else if (['value', 'checked', 'selected', 'disabled', 'hidden'].includes(key)) el[key] = val;
      else el.setAttribute(key, val === true ? '' : val);
    }
    for (const child of children.flat(Infinity)) {
      if (child == null || child === false) continue;
      el.append(child instanceof Node ? child : String(child));
    }
    return el;
  }
  const clone = (v) => JSON.parse(JSON.stringify(v));
  const uid = (prefix) => prefix + Array.from(crypto.getRandomValues(new Uint8Array(5)), (b) => b.toString(16).padStart(2, '0')).join('');
  const imgSrc = (path) => (path ? '../' + path : '');
  const categoryName = (id) => (content.categories.find((c) => c.id === id) || {}).name || '';
  const slugify = (text) => String(text || '').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
  // Site root = this page's path without the trailing "admin/…" part.
  const siteRoot = () => location.pathname.replace(/admin\/(index\.php)?$/, '');
  const productPath = (slug) => initial.productPattern + encodeURIComponent(slug);
  const productUrl = (p) => siteRoot() + productPath(p.slug);
  function moveItem(list, from, to) {
    if (to < 0 || to >= list.length) return;
    const [item] = list.splice(from, 1);
    list.splice(to, 0, item);
  }

  let toastTimer;
  function toast(message, type) {
    const el = document.getElementById('toast');
    el.textContent = message;
    el.className = 'toast show' + (type === 'error' ? ' error' : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (el.className = 'toast'), type === 'error' ? 5000 : 2200);
  }

  function toastLink(message, href) {
    const el = document.getElementById('toast');
    el.replaceChildren(message + ' · ', h('a', { href, target: '_blank', rel: 'noopener' }, 'Open it ↗'));
    el.className = 'toast show';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (el.className = 'toast'), 6000);
  }

  async function api(action, { json, form } = {}) {
    const opts = { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } };
    if (json) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(json);
    }
    if (form) opts.body = form;
    let res;
    try {
      res = await fetch('api.php?action=' + action, opts);
    } catch (err) {
      throw new Error('Network error. Check your connection and try again.');
    }
    let data = {};
    const text = await res.text().catch(() => '');
    try {
      data = JSON.parse(text);
    } catch (err) {
      // Some hosts print a PHP warning before the JSON; use the JSON part if there is one.
      const m = text.match(/\{"[\s\S]*\}\s*$/);
      try { data = m ? JSON.parse(m[0]) : {}; } catch (e) { data = {}; }
      if (res.ok && !m) throw new Error('The server sent an unexpected reply. Please try again.');
    }
    if (res.status === 401) {
      alert(data.error || 'Your session has ended. Please log in again.');
      location.reload();
      throw new Error('Session ended');
    }
    if (!res.ok) throw new Error(data.error || 'Request failed (' + res.status + ').');
    return data;
  }

  // Saves the whole content document. Saves run one after another.
  let queue = Promise.resolve();
  function persist(message = 'Saved') {
    const snapshot = clone(content);
    const run = queue.then(async () => {
      try {
        const data = await api('save', { json: { content: snapshot } });
        content = data.content;
        toast(message);
        return true;
      } catch (err) {
        if (err.message !== 'Session ended') toast(err.message, 'error');
        try { content = (await api('load')).content; } catch (e) { /* keep local copy */ }
        render();
        return false;
      }
    });
    queue = run.catch(() => {});
    return run;
  }

  function uploadImage(file) {
    if (!file) return Promise.reject(new Error('No file selected.'));
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) return Promise.reject(new Error('Please choose a JPG, PNG, WEBP or GIF image.'));
    if (file.size > initial.uploadLimit) return Promise.reject(new Error('Image is too large. Maximum size is ' + initial.uploadLimitText + '.'));
    const form = new FormData();
    form.append('image', file);
    return api('upload', { form }).then((d) => d.path);
  }

  /* ---------- image picker (used in product editor and panel form) ---------- */

  function imagePicker(getPath, setPath, label) {
    const fileInput = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: true });
    const drop = h('div', { class: 'image-drop', tabindex: '0', role: 'button', 'aria-label': 'Upload ' + label });
    const actions = h('div', { class: 'image-actions' });

    function paint() {
      drop.replaceChildren(getPath() ? h('img', { src: imgSrc(getPath()), alt: '' }) : 'Click or drop an image here');
      actions.replaceChildren(...[
        h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => fileInput.click() }, getPath() ? 'Replace image' : 'Upload image'),
        getPath() ? h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onclick: () => { setPath(''); paint(); } }, 'Remove') : null,
      ].filter(Boolean));
    }
    async function handle(file) {
      drop.classList.add('busy');
      try {
        setPath(await uploadImage(file));
        paint();
      } catch (err) {
        if (err.message !== 'Session ended') toast(err.message, 'error');
      } finally {
        drop.classList.remove('busy');
        fileInput.value = '';
      }
    }
    fileInput.addEventListener('change', () => fileInput.files[0] && handle(fileInput.files[0]));
    drop.addEventListener('click', () => fileInput.click());
    drop.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); fileInput.click(); } });
    drop.addEventListener('dragover', (ev) => { ev.preventDefault(); drop.classList.add('over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('over'));
    drop.addEventListener('drop', (ev) => {
      ev.preventDefault();
      drop.classList.remove('over');
      if (ev.dataTransfer.files[0]) handle(ev.dataTransfer.files[0]);
    });
    paint();

    const picker = h('div', { class: 'image-picker' },
      drop,
      h('div', {},
        h('div', { class: 'label' }, label),
        h('p', { class: 'hint' }, 'JPG, PNG, WEBP or GIF, up to ' + initial.uploadLimitText + '. Photos on a white background look best.'),
        actions, fileInput));
    picker.repaint = paint;
    return picker;
  }

  /* ---------- Products ---------- */

  function filteredProducts() {
    const q = ui.search.trim().toLowerCase();
    return content.products.filter((p) => {
      if (ui.category === '__none' && p.category && categoryName(p.category)) return false;
      if (ui.category !== 'all' && ui.category !== '__none' && p.category !== ui.category) return false;
      if (!q) return true;
      return (p.name + ' ' + p.description + ' ' + categoryName(p.category)).toLowerCase().includes(q);
    });
  }

  function moveProduct(from, to) {
    if (to < 0 || to >= content.products.length || from === to) return;
    const [item] = content.products.splice(from, 1);
    content.products.splice(to, 0, item);
    render();
    persist('Order saved');
  }

  function renderProducts() {
    const searchInput = h('input', {
      type: 'search', placeholder: 'Search products…', value: ui.search, 'aria-label': 'Search products',
      oninput: (ev) => { ui.search = ev.target.value; renderList(); },
    });
    const catSelect = h('select', {
      'aria-label': 'Filter by category',
      onchange: (ev) => { ui.category = ev.target.value; render(); },
    },
    h('option', { value: 'all', selected: ui.category === 'all' }, 'All categories'),
    content.categories.map((c) => h('option', { value: c.id, selected: ui.category === c.id }, c.name)),
    h('option', { value: '__none', selected: ui.category === '__none' }, 'No category'));

    const rows = h('ul', { class: 'rows' });
    const count = h('span', { class: 'count' });
    const notice = h('div', { class: 'notice', hidden: true }, 'Clear the search and category filter to reorder products.');

    function renderList() {
      const items = filteredProducts();
      const isFiltered = ui.search.trim() !== '' || ui.category !== 'all';
      notice.hidden = !isFiltered || items.length === 0;
      count.textContent = `${items.length} of ${content.products.length} products · ${content.products.filter((x) => x.visible).length} shown on site`;
      rows.replaceChildren(...(items.length ? items.map((p) => productRow(p, isFiltered)) : [
        h('li', { class: 'empty' },
          h('b', {}, content.products.length ? 'No products match' : 'No products yet'),
          content.products.length ? 'Try a different search or category.' : 'Click “Add product” to create your first one.'),
      ]));
    }
    renderList();

    return [
      h('div', { class: 'page-head' },
        h('div', {}, h('h1', {}, 'Products'), h('p', {}, 'Each product has its own page on the website, created automatically. Drag rows or use the arrows to change the order.')),
        h('button', { class: 'btn btn-primary', onclick: () => openProductEditor(null) }, '＋ Add product')),
      h('section', { class: 'card' },
        h('div', { class: 'toolbar' }, searchInput, catSelect, count),
        notice, rows),
    ];
  }

  let dragFrom = null;
  function productRow(p, filtering) {
    const index = content.products.indexOf(p);
    const cat = categoryName(p.category);
    const row = h('li', { class: 'row' + (p.visible ? '' : ' is-hidden'), draggable: filtering ? null : 'true' },
      h('span', { class: 'handle' + (filtering ? ' off' : ''), title: filtering ? '' : 'Drag to reorder', 'aria-hidden': 'true' }, '⋮⋮'),
      p.image ? h('img', { class: 'thumb', src: imgSrc(p.image), alt: '', loading: 'lazy' }) : h('div', { class: 'thumb thumb-empty' }, 'No photo'),
      h('div', { class: 'row-main' },
        h('div', { class: 'row-title' }, p.name),
        h('div', { class: 'row-meta' },
          h('span', { class: 'pill' + (cat ? '' : ' muted') }, cat || 'No category'),
          h('span', { class: 'desc' }, p.description))),
      h('label', { class: 'switch', title: 'Show on website' },
        h('input', {
          type: 'checkbox', checked: p.visible, 'aria-label': 'Show ' + p.name + ' on website',
          onchange: (ev) => { p.visible = ev.target.checked; row.classList.toggle('is-hidden', !p.visible); persist(p.visible ? 'Product shown on site' : 'Product hidden from site'); },
        }), h('i'), h('span', {}, 'Visible')),
      h('div', { class: 'row-actions' },
        h('button', { class: 'icon-btn', title: 'Move up', 'aria-label': 'Move ' + p.name + ' up', disabled: filtering || index === 0, onclick: () => moveProduct(index, index - 1) }, '↑'),
        h('button', { class: 'icon-btn', title: 'Move down', 'aria-label': 'Move ' + p.name + ' down', disabled: filtering || index === content.products.length - 1, onclick: () => moveProduct(index, index + 1) }, '↓'),
        h('button', { class: 'btn btn-secondary btn-sm', onclick: () => openProductEditor(p) }, 'Edit'),
        h('a', { class: 'btn btn-ghost btn-sm', href: productUrl(p), target: '_blank', rel: 'noopener', title: 'Open the product page' }, p.visible ? 'View page ↗' : 'Preview ↗'),
        h('button', { class: 'btn btn-ghost btn-sm', title: 'Duplicate', onclick: () => duplicateProduct(p) }, 'Duplicate'),
        h('button', { class: 'icon-btn danger', title: 'Delete', 'aria-label': 'Delete ' + p.name, onclick: () => deleteProduct(p) }, '✕')));

    if (!filtering) {
      row.addEventListener('dragstart', (ev) => { dragFrom = index; row.classList.add('dragging'); ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', String(index)); });
      row.addEventListener('dragend', () => { dragFrom = null; row.classList.remove('dragging'); });
      row.addEventListener('dragover', (ev) => {
        if (dragFrom === null) return;
        ev.preventDefault();
        const after = ev.clientY > row.getBoundingClientRect().top + row.offsetHeight / 2;
        row.classList.toggle('drop-after', after);
        row.classList.toggle('drop-before', !after);
      });
      row.addEventListener('dragleave', () => row.classList.remove('drop-before', 'drop-after'));
      row.addEventListener('drop', (ev) => {
        ev.preventDefault();
        const after = row.classList.contains('drop-after');
        row.classList.remove('drop-before', 'drop-after');
        if (dragFrom === null) return;
        let to = index + (after ? 1 : 0);
        if (dragFrom < to) to -= 1;
        moveProduct(dragFrom, to);
      });
    }
    return row;
  }

  function duplicateProduct(p) {
    const copy = { ...clone(p), id: uid('p'), slug: '', name: p.name + ' (copy)', visible: false };
    content.products.splice(content.products.indexOf(p) + 1, 0, copy);
    render();
    persist('Copy created (hidden until you publish it)');
  }

  function deleteProduct(p) {
    if (!confirm(`Delete “${p.name}”? This cannot be undone.`)) return;
    content.products = content.products.filter((x) => x !== p);
    render();
    persist('Product deleted');
  }

  function openProductEditor(existing) {
    const blank = { id: uid('p'), slug: '', name: '', category: ui.category !== 'all' && ui.category !== '__none' ? ui.category : '', description: '', overview: '', image: '', alt: '', gallery: [], specs: [], features: [], applications: [], link: '', visible: true };
    const draft = Object.assign(blank, existing ? clone(existing) : {});
    const errorBox = h('div', { class: 'alert alert-error form-error', role: 'alert', hidden: true });
    const showError = (msg, tab) => { errorBox.textContent = msg; errorBox.hidden = false; if (tab) selectTab(tab); };

    const field = (label, input, hint, required) =>
      h('label', { class: 'field' }, h('span', {}, label, required && h('span', { class: 'req' }, ' *')), input, hint && h('small', {}, hint));

    /* --- Details tab --- */
    const catSelect = h('select', { id: 'f-category' });
    function fillCategories() {
      catSelect.replaceChildren(
        h('option', { value: '' }, '— No category —'),
        ...content.categories.map((c) => h('option', { value: c.id }, c.name)),
        h('option', { value: '__new' }, '＋ New category…'));
      catSelect.value = draft.category;
    }
    fillCategories();
    catSelect.addEventListener('change', async () => {
      if (catSelect.value !== '__new') { draft.category = catSelect.value; return; }
      const name = (prompt('Name of the new category:') || '').trim();
      if (name) {
        const cat = { id: uid('c'), name: name.slice(0, 80) };
        content.categories.push(cat);
        draft.category = cat.id;
        await persist('Category added');
      }
      fillCategories();
    });

    const slugPreview = h('small', {});
    const slugInput = h('input', { type: 'text', value: draft.slug, maxlength: '80', placeholder: 'Created from the product name',
      oninput: (ev) => { draft.slug = ev.target.value; paintSlug(); } });
    function paintSlug() {
      const slug = slugify(draft.slug) || slugify(draft.name) || 'product-name';
      slugPreview.textContent = 'Page address: ' + location.origin + siteRoot() + productPath(slug);
    }
    const nameInput = h('input', { type: 'text', value: draft.name, maxlength: '150', required: true,
      oninput: (ev) => { draft.name = ev.target.value; paintSlug(); } });
    paintSlug();

    const details = h('div', { class: 'tab-pane' },
      field('Product name', nameInput, null, true),
      h('div', { class: 'grid-2' },
        field('Category', catSelect),
        h('label', { class: 'field' }, h('span', {}, 'Page address (URL)'), slugInput, slugPreview)),
      field('Short description', h('textarea', { maxlength: '1000', rows: '3', value: draft.description, oninput: (ev) => (draft.description = ev.target.value) }),
        'Shown on the product card and at the top of the product page, e.g. “2,000 CMH · 3 HP · bag filtration rated 99% at 5 μm.”'),
      field('Overview', h('textarea', { maxlength: '5000', rows: '6', value: draft.overview, oninput: (ev) => (draft.overview = ev.target.value) }),
        'Longer text for the product page. Leave a blank line between paragraphs.'),
      field('Brochure / datasheet link (optional)', h('input', { type: 'url', maxlength: '500', value: draft.link, placeholder: 'https://… PDF brochure', oninput: (ev) => (draft.link = ev.target.value) }),
        'Adds a “Download brochure” button to the product page.'),
      h('label', { class: 'check' },
        h('input', { type: 'checkbox', checked: draft.visible, onchange: (ev) => (draft.visible = ev.target.checked) }),
        h('span', {}, h('b', {}, 'Show on website'), h('small', {}, 'Untick to keep this product (and its page) as a hidden draft. You can still preview it while logged in.'))));

    /* --- Images tab --- */
    const galleryGrid = h('div', { class: 'gallery-grid' });
    const galleryInput = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, hidden: true });
    const galleryAdd = h('button', { type: 'button', class: 'gallery-add', onclick: () => galleryInput.click() }, '＋', h('span', {}, 'Add photos'));
    function paintGallery() {
      galleryGrid.replaceChildren(...draft.gallery.map((path, i) => h('div', { class: 'gallery-item' },
        h('img', { src: imgSrc(path), alt: '' }),
        h('div', { class: 'gallery-tools' },
          h('button', { type: 'button', class: 'icon-btn', title: 'Move left', 'aria-label': 'Move photo left', disabled: i === 0, onclick: () => { moveItem(draft.gallery, i, i - 1); paintGallery(); } }, '←'),
          h('button', { type: 'button', class: 'icon-btn', title: 'Use as main image', 'aria-label': 'Use as main image', onclick: () => {
            const old = draft.image; draft.image = path; draft.gallery.splice(i, 1); if (old) draft.gallery.unshift(old); mainPicker.repaint(); paintGallery();
          } }, '★'),
          h('button', { type: 'button', class: 'icon-btn', title: 'Move right', 'aria-label': 'Move photo right', disabled: i === draft.gallery.length - 1, onclick: () => { moveItem(draft.gallery, i, i + 1); paintGallery(); } }, '→'),
          h('button', { type: 'button', class: 'icon-btn danger', title: 'Remove', 'aria-label': 'Remove photo', onclick: () => { draft.gallery.splice(i, 1); paintGallery(); } }, '✕')))),
        draft.gallery.length < 12 ? galleryAdd : null);
    }
    async function addGalleryFiles(files) {
      const list = Array.from(files).slice(0, 12 - draft.gallery.length);
      galleryAdd.classList.add('busy');
      for (const file of list) {
        try { draft.gallery.push(await uploadImage(file)); paintGallery(); }
        catch (err) { if (err.message !== 'Session ended') toast(file.name + ': ' + err.message, 'error'); }
      }
      galleryAdd.classList.remove('busy');
      galleryInput.value = '';
    }
    galleryInput.addEventListener('change', () => addGalleryFiles(galleryInput.files));
    galleryGrid.addEventListener('dragover', (ev) => { ev.preventDefault(); galleryGrid.classList.add('over'); });
    galleryGrid.addEventListener('dragleave', () => galleryGrid.classList.remove('over'));
    galleryGrid.addEventListener('drop', (ev) => { ev.preventDefault(); galleryGrid.classList.remove('over'); addGalleryFiles(ev.dataTransfer.files); });
    paintGallery();

    const mainPicker = imagePicker(() => draft.image, (v) => (draft.image = v), 'Main image');
    const images = h('div', { class: 'tab-pane', hidden: true },
      mainPicker,
      field('Image description (alt text)', h('input', { type: 'text', maxlength: '200', value: draft.alt, placeholder: 'Defaults to the product name', oninput: (ev) => (draft.alt = ev.target.value) }),
        'Read by screen readers and search engines.'),
      h('div', { class: 'label' }, 'Gallery photos'),
      h('p', { class: 'hint', style: 'margin-bottom:10px' }, 'Extra views shown as thumbnails on the product page (up to 12). Drop several files at once. ★ makes a photo the main image.'),
      galleryGrid, galleryInput);

    /* --- Specifications tab --- */
    const specList = h('div', { class: 'rowlist' });
    function paintSpecs() {
      specList.replaceChildren(...draft.specs.map((s, i) => h('div', { class: 'spec-row' },
        h('input', { type: 'text', value: s.label, maxlength: '60', placeholder: 'e.g. Airflow', 'aria-label': 'Specification name', oninput: (ev) => (s.label = ev.target.value) }),
        h('input', { type: 'text', value: s.value, maxlength: '200', placeholder: 'e.g. 2,000 CMH', 'aria-label': 'Specification value', oninput: (ev) => (s.value = ev.target.value) }),
        h('label', { class: 'switch', title: 'Show as a big highlight figure on the product page' },
          h('input', { type: 'checkbox', checked: s.highlight, onchange: (ev) => { s.highlight = ev.target.checked; paintHint(); } }), h('i'), h('span', {}, 'Highlight')),
        h('div', { class: 'row-actions' },
          h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Move up', disabled: i === 0, onclick: () => { moveItem(draft.specs, i, i - 1); paintSpecs(); } }, '↑'),
          h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Move down', disabled: i === draft.specs.length - 1, onclick: () => { moveItem(draft.specs, i, i + 1); paintSpecs(); } }, '↓'),
          h('button', { type: 'button', class: 'icon-btn danger', 'aria-label': 'Remove row', onclick: () => { draft.specs.splice(i, 1); paintSpecs(); } }, '✕')))));
      if (!draft.specs.length) specList.append(h('p', { class: 'hint empty-hint' }, 'No specifications yet. Add rows such as Airflow, Motor, Filter type, Construction, Dimensions.'));
      paintHint();
    }
    const highlightHint = h('p', { class: 'hint' });
    function paintHint() {
      const n = draft.specs.filter((s) => s.highlight).length;
      highlightHint.textContent = `${n} highlighted. The first 4 highlighted rows appear as large animated figures at the top of the product page.`;
    }
    const addSpec = () => { draft.specs.push({ label: '', value: '', highlight: false }); paintSpecs(); specList.lastElementChild.querySelector('input').focus(); };
    paintSpecs();
    const specs = h('div', { class: 'tab-pane', hidden: true },
      h('div', { class: 'spec-head' }, h('span', {}, 'Name'), h('span', {}, 'Value'), h('span', {})),
      specList,
      h('div', { class: 'pane-actions' }, h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: addSpec }, '＋ Add specification')),
      highlightHint);

    /* --- Features & applications tab --- */
    const featureList = h('div', { class: 'rowlist' });
    function paintFeatures() {
      featureList.replaceChildren(...draft.features.map((f, i) => h('div', { class: 'feature-row' },
        h('div', { class: 'feature-fields' },
          h('input', { type: 'text', value: f.title, maxlength: '100', placeholder: 'Feature title, e.g. Automatic pulse cleaning', 'aria-label': 'Feature title', oninput: (ev) => (f.title = ev.target.value) }),
          h('textarea', { rows: '2', value: f.text, maxlength: '400', placeholder: 'One or two sentences explaining the benefit', 'aria-label': 'Feature description', oninput: (ev) => (f.text = ev.target.value) })),
        h('div', { class: 'row-actions' },
          h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Move up', disabled: i === 0, onclick: () => { moveItem(draft.features, i, i - 1); paintFeatures(); } }, '↑'),
          h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Move down', disabled: i === draft.features.length - 1, onclick: () => { moveItem(draft.features, i, i + 1); paintFeatures(); } }, '↓'),
          h('button', { type: 'button', class: 'icon-btn danger', 'aria-label': 'Remove feature', onclick: () => { draft.features.splice(i, 1); paintFeatures(); } }, '✕')))));
      if (!draft.features.length) featureList.append(h('p', { class: 'hint empty-hint' }, 'No features yet. The Features section is hidden on the product page until you add one.'));
    }
    paintFeatures();
    const appsInput = h('input', { type: 'text', value: draft.applications.join(', '), maxlength: '1200', placeholder: 'e.g. Woodworking, Metal grinding, Pharmaceutical',
      oninput: (ev) => (draft.applications = ev.target.value.split(',').map((t) => t.trim()).filter(Boolean)) });
    const features = h('div', { class: 'tab-pane', hidden: true },
      h('div', { class: 'label' }, 'Key features'),
      featureList,
      h('div', { class: 'pane-actions' }, h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onclick: () => {
        if (draft.features.length >= 12) return toast('Up to 12 features.', 'error');
        draft.features.push({ title: '', text: '' }); paintFeatures(); featureList.lastElementChild.querySelector('input').focus();
      } }, '＋ Add feature')),
      h('hr', { class: 'sep' }),
      field('Applications / industries', appsInput, 'Separate with commas. Shown as tags on the product page.'));

    /* --- Tabs --- */
    const panes = { details, images, specs, features };
    const tabNames = { details: 'Details', images: 'Images', specs: 'Specifications', features: 'Features & uses' };
    const tabButtons = Object.keys(panes).map((key) => h('button', { type: 'button', role: 'tab', class: 'dtab', 'data-key': key, onclick: () => selectTab(key) }, tabNames[key]));
    function selectTab(key) {
      Object.entries(panes).forEach(([k, pane]) => (pane.hidden = k !== key));
      tabButtons.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.key === key)));
    }
    selectTab('details');

    const form = h('form', { method: 'dialog', novalidate: true },
      h('div', { class: 'dialog-head' },
        h('h2', {}, existing ? 'Edit product' : 'Add product'),
        h('div', { class: 'dialog-head-actions' },
          existing && h('a', { class: 'btn btn-ghost btn-sm', href: productUrl(existing), target: '_blank', rel: 'noopener' }, 'View page ↗'),
          h('button', { type: 'button', class: 'icon-btn', 'aria-label': 'Close', onclick: () => dialog.close() }, '✕'))),
      h('div', { class: 'dtabs', role: 'tablist' }, tabButtons),
      h('div', { class: 'dialog-body' }, errorBox, details, images, specs, features),
      h('div', { class: 'dialog-foot' },
        existing && h('button', { type: 'button', class: 'btn btn-danger left', onclick: () => { dialog.close(); deleteProduct(existing); } }, 'Delete'),
        h('button', { type: 'button', class: 'btn btn-secondary', onclick: () => dialog.close() }, 'Cancel'),
        h('button', { type: 'submit', class: 'btn btn-primary' }, existing ? 'Save changes' : 'Create product & page')));

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      draft.name = draft.name.trim();
      draft.link = draft.link.trim();
      draft.slug = slugify(draft.slug);
      draft.specs = draft.specs.filter((s) => s.label.trim() || s.value.trim());
      draft.features = draft.features.filter((f) => f.title.trim() || f.text.trim());
      if (!draft.name) { showError('Please enter a product name.', 'details'); nameInput.focus(); return; }
      if (draft.link && !/^(https?:\/\/|mailto:|tel:)/i.test(draft.link) && /^[^/?#]*:/.test(draft.link)) {
        showError('The brochure link must start with https:// or http://', 'details');
        return;
      }
      const before = clone(content.products);
      const i = content.products.findIndex((x) => x.id === draft.id);
      if (i >= 0) content.products[i] = draft; else content.products.push(draft);
      const submit = form.querySelector('[type=submit]');
      submit.disabled = true;
      const ok = await persist(existing ? 'Product updated' : 'Product and page created');
      submit.disabled = false;
      if (ok) {
        dialog.close();
        render();
        if (!existing) {
          const saved = content.products.find((x) => x.id === draft.id);
          if (saved) toastLink('Product page created', productUrl(saved));
        }
      } else {
        content.products = before;
        showError('Could not save. See the message at the bottom of the screen.');
      }
    });

    const dialog = h('dialog', { class: 'dialog-wide', 'aria-label': existing ? 'Edit product' : 'Add product' }, form);
    dialog.addEventListener('close', () => dialog.remove());
    document.body.append(dialog);
    dialog.showModal();
    if (!existing) nameInput.focus();
  }

  /* ---------- Categories ---------- */

  function renderCategories() {
    const nameInput = h('input', { type: 'text', placeholder: 'New category name, e.g. Cartridge collectors', maxlength: '80', 'aria-label': 'New category name' });
    const addForm = h('form', { class: 'cat-add', onsubmit: (ev) => {
      ev.preventDefault();
      const name = nameInput.value.trim();
      if (!name) return nameInput.focus();
      if (content.categories.some((c) => c.name.toLowerCase() === name.toLowerCase())) return toast('That category already exists.', 'error');
      content.categories.push({ id: uid('c'), name });
      render();
      persist('Category added');
    } }, nameInput, h('button', { class: 'btn btn-primary', type: 'submit' }, 'Add category'));

    const move = (from, to) => {
      if (to < 0 || to >= content.categories.length) return;
      const [c] = content.categories.splice(from, 1);
      content.categories.splice(to, 0, c);
      render();
      persist('Order saved');
    };

    const rows = content.categories.map((c, i) => {
      const used = content.products.filter((p) => p.category === c.id).length;
      return h('li', { class: 'row cat-row' },
        h('div', { class: 'row-main' },
          h('input', { type: 'text', value: c.name, maxlength: '80', 'aria-label': 'Category name',
            onchange: (ev) => {
              const name = ev.target.value.trim();
              if (!name) { ev.target.value = c.name; return toast('Category name cannot be empty.', 'error'); }
              c.name = name;
              persist('Category renamed');
            },
            onkeydown: (ev) => { if (ev.key === 'Enter') ev.target.blur(); } }),
          h('div', { class: 'row-meta' }, h('a', { href: '#products', onclick: () => { ui.category = c.id; ui.search = ''; } }, `${used} product${used === 1 ? '' : 's'}`))),
        h('div', { class: 'row-actions' },
          h('button', { class: 'icon-btn', title: 'Move up', 'aria-label': 'Move ' + c.name + ' up', disabled: i === 0, onclick: () => move(i, i - 1) }, '↑'),
          h('button', { class: 'icon-btn', title: 'Move down', 'aria-label': 'Move ' + c.name + ' down', disabled: i === content.categories.length - 1, onclick: () => move(i, i + 1) }, '↓')),
        h('button', { class: 'icon-btn danger', title: 'Delete', 'aria-label': 'Delete ' + c.name, onclick: () => {
          const msg = used ? `Delete “${c.name}”? Its ${used} product(s) will be kept but moved to “No category”.` : `Delete “${c.name}”?`;
          if (!confirm(msg)) return;
          content.categories = content.categories.filter((x) => x !== c);
          content.products.forEach((p) => { if (p.category === c.id) p.category = ''; });
          if (ui.category === c.id) ui.category = 'all';
          render();
          persist('Category deleted');
        } }, '✕'));
    });

    return [
      h('div', { class: 'page-head' },
        h('div', {}, h('h1', {}, 'Categories'), h('p', {}, 'Categories group products and appear as filter buttons above the product grid, in this order.'))),
      h('section', { class: 'card' }, addForm,
        h('ul', { class: 'rows' }, rows.length ? rows : h('li', { class: 'empty' }, h('b', {}, 'No categories yet'), 'Add one above.'))),
    ];
  }

  /* ---------- Products panel settings ---------- */

  function renderPanel() {
    if (!ui.panelDraft) ui.panelDraft = clone(content.panel);
    const d = ui.panelDraft;
    const s = d.spotlight;
    const state = h('div', { class: 'save-state' + (ui.panelDirty ? ' dirty' : '') }, ui.panelDirty ? 'You have unsaved changes.' : 'All changes saved.');
    const saveBtn = h('button', { class: 'btn btn-primary btn-block', disabled: !ui.panelDirty, onclick: savePanel }, 'Save panel');
    const discardBtn = h('button', { class: 'btn btn-secondary btn-block', disabled: !ui.panelDirty, onclick: () => { ui.panelDraft = null; ui.panelDirty = false; render(); } }, 'Discard changes');
    const dirty = () => {
      ui.panelDirty = true;
      state.textContent = 'You have unsaved changes.';
      state.classList.add('dirty');
      saveBtn.disabled = discardBtn.disabled = false;
    };
    const text = (obj, key, label, opts = {}) =>
      h('label', { class: 'field' }, h('span', {}, label),
        h(opts.multiline ? 'textarea' : 'input', { type: 'text', value: obj[key], maxlength: String(opts.max || 160), rows: opts.multiline ? '3' : null, placeholder: opts.placeholder || null,
          oninput: (ev) => { obj[key] = ev.target.value; dirty(); } }),
        opts.hint && h('small', {}, opts.hint));
    const check = (obj, key, label, hint) =>
      h('label', { class: 'check' }, h('input', { type: 'checkbox', checked: obj[key], onchange: (ev) => { obj[key] = ev.target.checked; dirty(); } }),
        h('span', {}, h('b', {}, label), hint && h('small', {}, hint)));

    async function savePanel() {
      saveBtn.disabled = true;
      content.panel = clone(d);
      const ok = await persist('Products panel saved');
      if (ok) { ui.panelDraft = null; ui.panelDirty = false; render(); } else saveBtn.disabled = false;
    }

    return [
      h('div', { class: 'page-head' },
        h('div', {}, h('h1', {}, 'Products panel'), h('p', {}, 'Customise the “Equipment range” section of the website that holds the product grid.'))),
      h('div', { class: 'panel-layout' },
        h('div', {},
          h('section', { class: 'card card-pad' },
            h('h2', { class: 'section-title' }, 'Section heading'),
            h('p', { class: 'section-sub' }, 'The text at the top of the products section.'),
            text(d, 'eyebrow', 'Small label', { max: 80, placeholder: 'Equipment range' }),
            text(d, 'title', 'Heading', { max: 160 }),
            text(d, 'intro', 'Introduction', { max: 600, multiline: true })),
          h('section', { class: 'card card-pad' },
            h('h2', { class: 'section-title' }, 'Product cards'),
            h('p', { class: 'section-sub' }, 'How the product grid looks.'),
            text(d, 'cardLabel', 'Card number label', { max: 20, placeholder: 'RANGE', hint: 'Shown as “RANGE / 01” on each card. Leave empty to show only the number.' }),
            check(d, 'showFilters', 'Show category filter buttons', 'Lets visitors filter the grid by category.'),
            check(d, 'showCategoryOnCards', 'Show category name on each card')),
          h('section', { class: 'card card-pad' },
            h('h2', { class: 'section-title' }, 'Spotlight banner'),
            h('p', { class: 'section-sub' }, 'The large image banner above the product grid.'),
            check(s, 'show', 'Show spotlight banner'),
            text(s, 'kicker', 'Small label', { max: 80 }),
            text(s, 'title', 'Title', { max: 160 }),
            text(s, 'text', 'Text', { max: 600, multiline: true }),
            text(s, 'linkText', 'Link text', { max: 80, hint: 'Scrolls visitors down to the product grid.' }),
            imagePicker(() => s.image, (v) => { s.image = v; dirty(); }, 'Banner image'),
            text(s, 'alt', 'Image description (alt text)', { max: 200 }))),
        h('aside', { class: 'save-bar' },
          h('div', { class: 'card card-pad' }, state, h('div', { style: 'display:grid;gap:8px;margin-top:12px' }, saveBtn, discardBtn,
            h('a', { class: 'btn btn-ghost btn-block', href: '../#applications', target: '_blank', rel: 'noopener' }, 'Preview on site ↗'))))),
    ];
  }

  /* ---------- Account ---------- */

  function renderAccount() {
    const msg = h('div', { class: 'alert', hidden: true });
    const emailInput = h('input', { type: 'email', value: email, required: true, autocomplete: 'username' });
    const current = h('input', { type: 'password', required: true, autocomplete: 'current-password' });
    const next = h('input', { type: 'password', minlength: '10', autocomplete: 'new-password' });
    const confirmInput = h('input', { type: 'password', autocomplete: 'new-password' });
    const show = (text, ok) => { msg.textContent = text; msg.className = 'alert ' + (ok ? 'alert-ok' : 'alert-error'); msg.hidden = false; };

    const form = h('form', { class: 'card card-pad', style: 'max-width:520px', onsubmit: async (ev) => {
      ev.preventDefault();
      if (next.value && next.value.length < 10) return show('New password must be at least 10 characters.');
      if (next.value !== confirmInput.value) return show('The new passwords do not match.');
      const btn = form.querySelector('button');
      btn.disabled = true;
      try {
        const data = await api('account', { json: { email: emailInput.value.trim(), currentPassword: current.value, newPassword: next.value } });
        email = data.email;
        current.value = next.value = confirmInput.value = '';
        show('Account updated.', true);
      } catch (err) {
        if (err.message !== 'Session ended') show(err.message);
      } finally {
        btn.disabled = false;
      }
    } },
    h('h2', { class: 'section-title' }, 'Login details'),
    h('p', { class: 'section-sub' }, 'Change the email or password used to log in to this panel.'),
    msg,
    h('label', { class: 'field' }, h('span', {}, 'Email'), emailInput),
    h('label', { class: 'field' }, h('span', {}, 'New password'), next, h('small', {}, 'Leave empty to keep your current password. At least 10 characters.')),
    h('label', { class: 'field' }, h('span', {}, 'Confirm new password'), confirmInput),
    h('label', { class: 'field' }, h('span', {}, 'Current password', h('span', { class: 'req' }, ' *')), current, h('small', {}, 'Required to confirm any change.')),
    h('button', { class: 'btn btn-primary', type: 'submit' }, 'Update account'));

    return [
      h('div', { class: 'page-head' }, h('div', {}, h('h1', {}, 'Account'), h('p', {}, 'Logged in as ' + email))),
      form,
    ];
  }

  /* ---------- router ---------- */

  const views = { products: renderProducts, categories: renderCategories, panel: renderPanel, account: renderAccount };
  const currentTab = () => (views[location.hash.slice(1)] ? location.hash.slice(1) : 'products');

  function render() {
    const tab = currentTab();
    document.querySelectorAll('#tabs a').forEach((a) => (a.dataset.tab === tab ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current')));
    const scrollY = window.scrollY;
    app.replaceChildren(...views[tab]());
    window.scrollTo(0, scrollY);
  }

  let lastTab = currentTab();
  window.addEventListener('hashchange', () => {
    if (lastTab === 'panel' && ui.panelDirty && currentTab() !== 'panel') {
      if (!confirm('You have unsaved changes to the products panel. Leave without saving?')) {
        history.replaceState(null, '', '#panel');
        return;
      }
      ui.panelDraft = null;
      ui.panelDirty = false;
    }
    lastTab = currentTab();
    render();
    window.scrollTo(0, 0);
  });
  window.addEventListener('beforeunload', (ev) => {
    if (ui.panelDirty) { ev.preventDefault(); ev.returnValue = ''; }
  });

  render();
})();
