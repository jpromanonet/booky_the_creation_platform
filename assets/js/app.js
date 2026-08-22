(() => {
  const themeKey = 'booky-theme';
  const root = document.documentElement;
  const themeToggles = [...document.querySelectorAll('.theme-toggle')];

  const syncThemeLabel = () => {
    const isDark = root.getAttribute('data-theme') === 'dark';
    themeToggles.forEach((btn) => {
      btn.setAttribute('aria-label', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    });
  };
  syncThemeLabel();

  const focusUpload = () => {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('focus') || (window.location.hash ? window.location.hash.slice(1) : '');
    if (!id) return;
    const el = document.getElementById(id);
    if (!el) return;
    window.setTimeout(() => {
      el.scrollIntoView({ block: 'center', behavior: 'auto' });
    }, 80);
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', focusUpload);
  } else {
    focusUpload();
  }

  themeToggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(themeKey, next); } catch (_) {}
      syncThemeLabel();
    });
  });

  document.addEventListener('click', (e) => {
    document.querySelectorAll('details.nav-drop[open]').forEach((d) => {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  const backToTop = document.getElementById('back-to-top');
  if (backToTop) {
    const syncBackToTop = () => {
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      backToTop.classList.toggle('is-visible', y > 280);
    };
    syncBackToTop();
    window.addEventListener('scroll', syncBackToTop, { passive: true });
    backToTop.addEventListener('click', () => {
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
  }

  const topbar = document.getElementById('topbar');
  const navToggle = document.getElementById('nav-toggle');
  if (topbar && navToggle) {
    const setOpen = (open) => {
      topbar.classList.toggle('is-open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      navToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    };
    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      setOpen(!topbar.classList.contains('is-open'));
    });
    topbar.querySelectorAll('.topbar-panel a').forEach((a) => {
      a.addEventListener('click', () => setOpen(false));
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setOpen(false);
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 960) setOpen(false);
    });
  }

  const roman = (n) => {
    const map = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']];
    let out = '';
    map.forEach(([v, g]) => {
      while (n >= v) { out += g; n -= v; }
    });
    return out;
  };

  const structureRoot = document.getElementById('structure-editor');
  const chapterTpl = document.getElementById('chapter-row-tpl');
  const partTpl = document.getElementById('part-row-tpl');
  const partsEditor = document.getElementById('parts-editor');
  const chapterEditor = document.getElementById('chapter-editor');
  const countInput = document.getElementById('chapter-count');

  const rowCount = () => chapterEditor ? chapterEditor.querySelectorAll('.chapter-row').length : 0;

  const partLabels = () => [...(partsEditor ? partsEditor.querySelectorAll('[data-name="part-title"]') : [])]
    .map((inp, i) => (inp.value || '').trim() || ('Parte ' + roman(i + 1)));

  const fillSelect = (sel, keep) => {
    if (!sel) return;
    const current = keep !== undefined ? String(keep) : String(sel.value || '');
    const labels = partLabels();
    sel.innerHTML = '';
    const none = document.createElement('option');
    none.value = '';
    none.textContent = 'Sin parte';
    sel.appendChild(none);
    labels.forEach((lab, i) => {
      const opt = document.createElement('option');
      opt.value = String(i + 1);
      opt.textContent = lab;
      sel.appendChild(opt);
    });
    sel.disabled = labels.length === 0;
    if (current && [...sel.options].some((o) => o.value === current)) sel.value = current;
    else sel.value = '';
  };

  const refreshPartSelects = (remap) => {
    if (!chapterEditor) return;
    chapterEditor.querySelectorAll('[data-part-select]').forEach((sel) => {
      const next = typeof remap === 'function' ? remap(sel.value) : sel.value;
      fillSelect(sel, next);
    });
  };

  const reindexParts = () => {
    if (!partsEditor) return;
    [...partsEditor.querySelectorAll('[data-part]')].forEach((part, i) => {
      const id = part.querySelector('[data-name="part-id"]');
      const title = part.querySelector('[data-name="part-title"]');
      if (id) id.name = `parts[${i}][id]`;
      if (title) {
        title.name = `parts[${i}][title]`;
        title.placeholder = 'Parte ' + roman(i + 1);
      }
    });
  };

  const nameChapters = () => {
    if (!chapterEditor) return;
    chapterEditor.querySelectorAll('.chapter-row').forEach((row, i) => {
      const hid = row.querySelector('[data-name="id"]');
      const inp = row.querySelector('[data-name="title"]');
      const sel = row.querySelector('[data-part-select]');
      if (hid) hid.name = 'chapter_id[]';
      if (inp) {
        inp.name = 'chapter_title[]';
        inp.placeholder = 'Capítulo ' + (i + 1);
      }
      if (sel) sel.name = 'chapter_part[]';
    });
    if (countInput) countInput.value = String(Math.max(1, rowCount()));
  };

  const addChapterRow = () => {
    if (!chapterEditor || !chapterTpl || rowCount() >= 80) return;
    const node = chapterTpl.content.cloneNode(true);
    const sel = node.querySelector('[data-part-select]');
    fillSelect(sel, '');
    chapterEditor.appendChild(node);
    nameChapters();
  };

  const syncChapterRows = (n) => {
    n = Math.max(1, Math.min(80, Number(n) || 1));
    while (rowCount() < n) addChapterRow();
    while (rowCount() > n) {
      const rows = chapterEditor.querySelectorAll('.chapter-row');
      rows[rows.length - 1].remove();
    }
    nameChapters();
  };

  const toggleSpecial = (box, fields) => {
    if (!box || !fields) return;
    const sync = () => { fields.hidden = !box.checked; };
    box.addEventListener('change', sync);
    sync();
  };
  toggleSpecial(document.getElementById('include-intro'), document.getElementById('intro-fields'));
  toggleSpecial(document.getElementById('include-epilogue'), document.getElementById('epilogue-fields'));

  document.getElementById('add-part')?.addEventListener('click', () => {
    if (!partsEditor || !partTpl) return;
    partsEditor.appendChild(partTpl.content.cloneNode(true));
    reindexParts();
    refreshPartSelects();
  });

  document.getElementById('add-chapter')?.addEventListener('click', () => addChapterRow());

  if (countInput && chapterEditor) {
    countInput.addEventListener('change', () => syncChapterRows(countInput.value));
    countInput.addEventListener('input', () => {
      const n = Number(countInput.value);
      if (n >= 1) syncChapterRows(n);
    });
  }

  partsEditor?.addEventListener('input', (e) => {
    if (e.target && e.target.matches('[data-name="part-title"]')) refreshPartSelects();
  });

  structureRoot?.addEventListener('click', (e) => {
    const removePart = e.target.closest('[data-remove-part]');
    if (removePart) {
      const card = removePart.closest('[data-part]');
      if (!card || !partsEditor) return;
      const idx = [...partsEditor.querySelectorAll('[data-part]')].indexOf(card);
      const removed = String(idx + 1);
      card.remove();
      reindexParts();
      refreshPartSelects((value) => {
        if (!value) return '';
        if (value === removed) return '';
        const n = Number(value);
        return n > idx + 1 ? String(n - 1) : value;
      });
      return;
    }
    const removeCh = e.target.closest('[data-remove-chapter]');
    if (!removeCh) return;
    const row = removeCh.closest('.chapter-row');
    if (!row || !chapterEditor) return;
    if (rowCount() <= 1) {
      const input = row.querySelector('[data-name="title"]');
      const hidden = row.querySelector('[data-name="id"]');
      const sel = row.querySelector('[data-part-select]');
      if (input) input.value = '';
      if (hidden) hidden.value = '';
      if (sel) sel.value = '';
      return;
    }
    row.remove();
    nameChapters();
  });

  document.getElementById('book-structure-form')?.addEventListener('submit', () => {
    reindexParts();
    nameChapters();
    chapterEditor?.querySelectorAll('[data-part-select]').forEach((sel) => { sel.disabled = false; });
  });

  const extOf = (name) => (String(name).split('.').pop() || '').toLowerCase();
  document.querySelectorAll('.drop-form').forEach((form) => {
    const zone = form.querySelector('.dropzone');
    const input = form.querySelector('.dropzone-input');
    const fileLabel = form.querySelector('.dropzone-file');
    if (!zone || !input) return;
    const allowedExt = String(form.dataset.ext || 'ods,odt,docx').split(',').map((s) => s.trim()).filter(Boolean);

    const useFile = (file) => {
      if (!file || form.dataset.busy === '1') return;
      if (!allowedExt.includes(extOf(file.name))) {
        alert(allowedExt.includes('pdf') && allowedExt.length === 1
          ? 'En este espacio usá un PDF.'
          : 'Usá un archivo ODS, ODT o DOCX.');
        return;
      }
      form.dataset.busy = '1';
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      if (fileLabel) {
        fileLabel.hidden = false;
        fileLabel.textContent = 'Subiendo ' + file.name + '…';
      }
      zone.classList.add('is-busy');
      form.submit();
    };

    ['dragenter', 'dragover'].forEach((ev) => {
      zone.addEventListener(ev, (e) => {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('is-drag');
      });
    });
    ['dragleave', 'drop'].forEach((ev) => {
      zone.addEventListener(ev, (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (ev === 'dragleave') zone.classList.remove('is-drag');
      });
    });
    zone.addEventListener('drop', (e) => {
      zone.classList.remove('is-drag');
      const file = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
      useFile(file);
    });
    input.addEventListener('change', () => {
      if (input.files && input.files[0]) useFile(input.files[0]);
    });
  });
  document.addEventListener('dragover', (e) => {
    if (e.target.closest && e.target.closest('.dropzone')) return;
    e.preventDefault();
  });
  document.addEventListener('drop', (e) => {
    if (e.target.closest && e.target.closest('.dropzone')) return;
    e.preventDefault();
  });

  const bootCharts = () => {
    const node = document.getElementById('booky-charts');
    if (!node || typeof Chart === 'undefined') return;
    let data;
    try { data = JSON.parse(node.textContent || '{}'); } catch (_) { return; }

    const muted = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#64748b';
    Chart.defaults.color = muted;
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

    const colors = ['#9a3412', '#d97706', '#3b82f6', '#7c3aed', '#0f766e', '#db2777', '#f97316', '#2563eb', '#10b981', '#64748b'];
    const book = data.book || null;
    const segs = book && Array.isArray(book.segments) ? book.segments : [];
    const overview = data.overview || {};
    const catalog = Array.isArray(data.catalog) ? data.catalog : [];

    const doughnutOrBar = (el, type, labels, values, bg) => {
      if (!el) return;
      new Chart(el, {
        type,
        data: { labels, datasets: [{ data: values, backgroundColor: bg, borderWidth: 0 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: type !== 'bar' } } },
      });
    };

    const make = (name, scope, factory) => {
      const sel = scope
        ? `[data-chart="${name}"][data-scope="${scope}"]`
        : `[data-chart="${name}"]:not([data-scope])`;
      const el = document.querySelector(sel);
      if (el) factory(el);
    };

    const coach = !!data.moods;
    make('catalogPct', null, (el) => {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: catalog.map((r) => r.title),
          datasets: [{
            label: '%',
            data: catalog.map((r) => r.pct),
            backgroundColor: catalog.map((r) => {
              const pct = Number(r.pct || 0);
              if (pct >= 99.9) return '#166534';
              if (pct >= 50) return '#4ade80';
              return coach ? '#d97706' : '#9a3412';
            }),
          }],
        },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { min: 0, max: 100 } } },
      });
    });
    make('catalogPages', null, (el) => {
      doughnutOrBar(el, 'bar', catalog.map((r) => r.title), catalog.map((r) => r.pages), coach ? '#22c55e' : '#3b82f6');
    });
    make('byAuthor', null, (el) => {
      const o = overview.by_author || {};
      doughnutOrBar(el, 'doughnut', Object.keys(o), Object.values(o), colors);
    });
    make('byStatus', null, (el) => {
      const o = overview.by_status || {};
      doughnutOrBar(el, 'pie', Object.keys(o), Object.values(o), colors);
    });
    make('byGenre', null, (el) => {
      const o = overview.by_genre || {};
      doughnutOrBar(el, 'doughnut', Object.keys(o), Object.values(o), colors);
    });
    make('pctBuckets', null, (el) => {
      const o = overview.pct_buckets || {};
      doughnutOrBar(el, 'bar', Object.keys(o), Object.values(o), '#7c3aed');
    });
    make('milestonesCatalog', null, (el) => {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: ['Outline', 'Sinopsis'],
          datasets: [
            { label: 'Listos', data: [overview.outline || 0, overview.synopsis || 0], backgroundColor: '#10b981' },
            { label: 'Libros', data: [overview.books || 0, overview.books || 0], backgroundColor: 'rgba(148,163,184,0.3)' },
          ],
        },
        options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
      });
    });
    make('chaptersCatalog', null, (el) => {
      new Chart(el, {
        type: 'bar',
        data: {
          labels: catalog.map((r) => r.title),
          datasets: [
            { label: 'Cargados', data: catalog.map((r) => r.chapters_done), backgroundColor: '#9a3412' },
            { label: 'Planeados', data: catalog.map((r) => r.chapters_total), backgroundColor: 'rgba(148,163,184,0.3)' },
          ],
        },
        options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
      });
    });
    make('completeSplit', null, (el) => {
      doughnutOrBar(el, 'doughnut', ['Al 100%', 'En curso'], [overview.complete || 0, overview.in_progress || 0], ['#166534', '#d97706']);
    });

    const greens = ['#4ade80', '#166534'];
    make('overallGauge', null, (el) => {
      const pct = Number(overview.overall_pct || 0);
      const fill = pct >= 99.9 ? '#166534' : (pct >= 50 ? '#4ade80' : '#d97706');
      doughnutOrBar(el, 'doughnut', ['Hecho', 'Falta'], [pct, Math.max(0, 100 - pct)], [fill, 'rgba(148,163,184,0.22)']);
    });
    make('halfSplit', null, (el) => {
      const over = Number(overview.over_half || 0);
      const rest = Math.max(0, Number(overview.books || 0) - over);
      doughnutOrBar(el, 'doughnut', ['Sobre 50%', 'Todavía debajo'], [over, rest], greens);
    });
    make('moodMix', null, (el) => {
      const moods = data.moods || {};
      const labels = { stalled: 'Empujón', starting: 'Arranque', moving: 'En marcha', almost: 'Casi', done: 'Cerrado' };
      const keys = Object.keys(moods);
      doughnutOrBar(
        el,
        'doughnut',
        keys.map((k) => labels[k] || k),
        keys.map((k) => moods[k]),
        ['#f59e0b', '#93c5fd', '#34d399', '#166534', '#0f766e']
      );
    });
    make('hitCoverage', null, (el) => {
      const books = Math.max(1, Number(overview.books || 0));
      const chDone = Number(overview.chapters_done || 0);
      const chTotal = Math.max(1, Number(overview.chapters_total || 0));
      new Chart(el, {
        type: 'bar',
        data: {
          labels: ['Outline', 'Sinopsis', 'Capítulos'],
          datasets: [{
            label: '%',
            data: [
              Math.round(((overview.outline || 0) / books) * 1000) / 10,
              Math.round(((overview.synopsis || 0) / books) * 1000) / 10,
              Math.round((chDone / chTotal) * 1000) / 10,
            ],
            backgroundColor: ['#4ade80', '#22c55e', '#166534'],
          }],
        },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100 } } },
      });
    });
    make('idleDays', null, (el) => {
      const cards = Array.isArray(data.cards) ? data.cards : [];
      new Chart(el, {
        type: 'bar',
        data: {
          labels: cards.map((c) => c.title),
          datasets: [{ label: 'Días', data: cards.map((c) => Math.min(60, Number(c.days_idle || 0))), backgroundColor: '#34d399' }],
        },
        options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } },
      });
    });
    make('authorPct', null, (el) => {
      const o = overview.pct_by_author || {};
      doughnutOrBar(el, 'bar', Object.keys(o), Object.values(o), '#166534');
    });

    const bookScope = (name, factory) => make(name, 'book', factory);
    if (segs.length) {
      const pageData = segs.every((s) => !s.pages) ? segs.map(() => 1) : segs.map((s) => s.pages);
      bookScope('composition', (el) => {
        new Chart(el, {
          type: 'doughnut',
          data: { labels: segs.map((s) => s.label), datasets: [{ data: pageData, backgroundColor: segs.map((s) => s.color) }] },
          options: {
            maintainAspectRatio: false,
            plugins: {
              tooltip: { callbacks: { label(ctx) { const s = segs[ctx.dataIndex]; return `${s.label}: ${s.pages} pág. (${s.pct}%)`; } } },
            },
          },
        });
      });
      bookScope('pages', (el) => doughnutOrBar(el, 'bar', segs.map((s) => s.label), segs.map((s) => s.pages), segs.map((s) => s.color)));
      bookScope('cumulative', (el) => {
        const cum = book.cumulative || [];
        new Chart(el, {
          type: 'line',
          data: { labels: cum.map((c) => c.label), datasets: [{ label: 'Páginas acumuladas', data: cum.map((c) => c.pages), borderColor: '#9a3412', tension: 0.25, fill: false }] },
          options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
      });
      bookScope('radar', (el) => {
        new Chart(el, {
          type: 'radar',
          data: { labels: segs.map((s) => s.label), datasets: [{ label: 'Páginas', data: segs.map((s) => s.pages), backgroundColor: 'rgba(154,52,18,0.25)', borderColor: '#9a3412' }] },
          options: { maintainAspectRatio: false },
        });
      });
      bookScope('polar', (el) => {
        new Chart(el, {
          type: 'polarArea',
          data: { labels: segs.map((s) => s.label), datasets: [{ data: pageData, backgroundColor: segs.map((s) => s.color) }] },
          options: { maintainAspectRatio: false },
        });
      });
      bookScope('avgLine', (el) => {
        const avg = Number(book.avg_pages || 0);
        new Chart(el, {
          type: 'bar',
          data: {
            labels: segs.map((s) => s.label),
            datasets: [
              { type: 'bar', label: 'Páginas', data: segs.map((s) => s.pages), backgroundColor: segs.map((s) => s.color) },
              { type: 'line', label: 'Promedio', data: segs.map(() => avg), borderColor: '#1a202c', borderWidth: 2 },
            ],
          },
          options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
        });
      });
    }
    if (book) {
      bookScope('milestones', (el) => {
        new Chart(el, {
          type: 'bar',
          data: {
            labels: ['Outline', 'Sinopsis', 'Manuscrito'],
            datasets: [
              { label: 'Cumplido', data: [book.has_outline ? 1 : 0, book.has_synopsis ? 1 : 0, book.chapters_done || 0], backgroundColor: '#9a3412' },
              { label: 'Total', data: [1, 1, Math.max(book.chapters_total || 1, 1)], backgroundColor: 'rgba(148,163,184,0.3)' },
            ],
          },
          options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
        });
      });
      bookScope('donePending', (el) => {
        doughnutOrBar(el, 'doughnut', ['Con documento', 'Pendientes'], [book.chapters_done || 0, book.chapters_pending || 0], ['#4ade80', '#d97706']);
      });
      bookScope('gauge', (el) => {
        const pct = Number(book.pct || 0);
        const fill = pct >= 99.9 ? '#166534' : (pct >= 50 ? '#4ade80' : '#d97706');
        doughnutOrBar(el, 'doughnut', ['Completo', 'Restante'], [pct, Math.max(0, 100 - pct)], [fill, 'rgba(148,163,184,0.25)']);
      });
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(bootCharts, 40));
  } else {
    window.setTimeout(bootCharts, 40);
  }

  const celebrate = document.getElementById('celebrate-modal');
  if (celebrate) {
    const close = () => {
      celebrate.classList.add('is-closing');
      window.setTimeout(() => celebrate.remove(), 220);
    };
    celebrate.querySelectorAll('[data-celebrate-close]').forEach((el) => {
      el.addEventListener('click', close);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && document.getElementById('celebrate-modal')) close();
    });
    window.setTimeout(() => celebrate.classList.add('is-open'), 40);
  }
})();
