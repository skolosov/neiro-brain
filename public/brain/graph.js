/**
 * Neiro Brain — force-directed knowledge graph.
 *
 * Plain ES module, no dependencies: the admin is served from OpenServer and must
 * work offline. Reads its data from <script id="nb-graph-data">, keeps the
 * physics in a requestAnimationFrame loop and talks to the side panel over DOM
 * events, so the graph can be embedded anywhere.
 */

const COLORS = {
  project: '#8b5cf6',
  entry: '#22c55e',
  match: '#eab308',
  projectPalette: ['#8b5cf6', '#06b6d4', '#f97316', '#ec4899', '#6366f1', '#14b8a6', '#eab308'],
};

const KIND_COLORS = {
  profile: '#3b82f6',
  preference: '#8b5cf6',
  project: '#22c55e',
  decision: '#f97316',
  person: '#ec4899',
  company: '#06b6d4',
  stack: '#eab308',
  style: '#6366f1',
  process: '#14b8a6',
  fact: '#94a3b8',
  other: '#94a3b8',
};

class ForceGraph {
  constructor(canvas, nodes, edges, options = {}) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.nodes = nodes;
    this.edges = edges;
    this.byId = new Map(nodes.map((node) => [node.id, node]));
    this.dpr = Math.min(2, window.devicePixelRatio || 1);
    this.scale = options.scale || 0.62;
    this.offsetX = 0;
    this.offsetY = 0;
    this.dragging = null;
    this.dragStart = null;
    this.hovered = null;
    this.selected = null;
    this.focus = new Set(options.focus || []);
    this.matches = new Set();
    this.labelsAlways = true;
    this.colorByProject = false;
    this.alpha = 1;
    this.animating = false;
    this.ticks = 0;
    this.projectColors = new Map();
    this.pointers = new Map();
    this.pinchDistance = 0;
    this.bind();
    this.resize();
    this.seed();
    this.start();
  }

  /* ------------------------------------------------------------------ */

  seed() {
    const count = Math.max(1, this.nodes.length);
    this.nodes.forEach((node, index) => {
      const angle = (index / count) * Math.PI * 2 + (index % 7) * 0.19;
      const radius = node.type === 'project' ? 40 : 90 + (index % 11) * 14;
      node.x = Math.cos(angle) * radius;
      node.y = Math.sin(angle) * radius;
      node.vx = 0;
      node.vy = 0;
      node.degree = 0;
      node.r = node.type === 'project' ? Math.max(9, Math.min(26, node.size * 0.55)) : Math.max(2.6, Math.min(14, node.size * 0.42));
    });

    this.edges.forEach((edge) => {
      const a = this.byId.get(edge.source);
      const b = this.byId.get(edge.target);
      if (!a || !b) return;
      a.degree = (a.degree || 0) + 1;
      b.degree = (b.degree || 0) + 1;
      const length = 70 + Math.random() * 40;
      edge.ax = (Math.random() - 0.5) * length;
      edge.ay = (Math.random() - 0.5) * length;
    });
  }

  step() {
    const nodes = this.nodes;
    const alpha = this.alpha;
    const repulsion = 2600;
    const spring = 0.006;
    const centerPull = 0.012;

    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const a = nodes[i];
        const b = nodes[j];
        let dx = b.x - a.x;
        let dy = b.y - a.y;
        let dist2 = dx * dx + dy * dy;

        if (dist2 < 0.01) {
          dx = (Math.random() - 0.5) * 0.4;
          dy = (Math.random() - 0.5) * 0.4;
          dist2 = dx * dx + dy * dy;
        }

        const dist = Math.sqrt(dist2);
        const force = (repulsion * alpha) / dist2;
        const fx = (dx / dist) * force;
        const fy = (dy / dist) * force;

        a.vx -= fx;
        a.vy -= fy;
        b.vx += fx;
        b.vy += fy;
      }
    }

    for (const edge of this.edges) {
      const a = this.byId.get(edge.source);
      const b = this.byId.get(edge.target);
      if (!a || !b) continue;

      const dx = b.x + edge.ax - a.x;
      const dy = b.y + edge.ay - a.y;
      const dist = Math.hypot(dx, dy) || 1;
      const target = 46 + Math.min(60, 120 / Math.sqrt(edge.weight || 1));
      const force = (dist - target) * spring * alpha * Math.min(3, edge.weight || 1);

      a.vx += (dx / dist) * force;
      a.vy += (dy / dist) * force;
      b.vx -= (dx / dist) * force;
      b.vy -= (dy / dist) * force;
    }

    for (const node of nodes) {
      node.vx -= node.x * centerPull;
      node.vy -= node.y * centerPull;
      node.vx *= 0.86;
      node.vy *= 0.86;
      node.x += node.vx;
      node.y += node.vy;
    }

    this.alpha = Math.max(0.02, this.alpha * 0.994);
  }

  start() {
    if (this.animating) return;
    this.animating = true;

    const loop = () => {
      this.ticks++;
      if (this.alpha > 0.02 || this.dragging) {
        this.step();
      }
      this.draw();

      if (this.alpha > 0.02) {
        this.frame = requestAnimationFrame(loop);
      } else {
        this.animating = false;
        this.draw();
      }
    };

    this.frame = requestAnimationFrame(loop);
  }

  warmup(steps = 260) {
    for (let i = 0; i < steps; i++) {
      this.step();
    }
  }

  /* ------------------------------------------------------------------ */

  resize() {
    const rect = this.canvas.getBoundingClientRect();
    const width = Math.max(320, rect.width);
    const height = Math.max(320, rect.height);

    this.canvas.width = Math.round(width * this.dpr);
    this.canvas.height = Math.round(height * this.dpr);
    this.width = width;
    this.height = height;
    this.originX = width / 2;
    this.originY = height / 2;
    this.draw();
  }

  worldToScreen(x, y) {
    return {
      x: this.originX + (x + this.offsetX) * this.scale,
      y: this.originY + (y + this.offsetY) * this.scale,
    };
  }

  screenToWorld(x, y) {
    return {
      x: (x - this.originX) / this.scale - this.offsetX,
      y: (y - this.originY) / this.scale - this.offsetY,
    };
  }

  color(node) {
    if (this.matches.has(node.id)) return COLORS.match;
    if (node.type === 'project') return COLORS.project;
    if (this.colorByProject && node.project) {
      return this.projectColor(node.project);
    }
    return KIND_COLORS[node.kind] || COLORS.entry;
  }

  projectColor(project) {
    if (!this.projectColors.has(project)) {
      this.projectColors.set(project, COLORS.projectPalette[this.projectColors.size % COLORS.projectPalette.length]);
    }
    return this.projectColors.get(project);
  }

  draw() {
    const ctx = this.ctx;
    const styles = getComputedStyle(this.canvas);
    const dark = this.canvas.closest('.dark') !== null;
    const background = dark ? '#0b1020' : '#ffffff';

    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = background;
    ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    ctx.scale(this.dpr, this.dpr);

    const hasFocus = this.focus.size > 0;
    const emphasise = (node) => !hasFocus || this.focus.has(node.id) || this.isNeighbour(node.id);
    const textColor = dark ? 'rgba(226,232,240,0.82)' : 'rgba(30,41,59,0.82)';

    ctx.lineWidth = 1;
    for (const edge of this.edges) {
      const a = this.byId.get(edge.source);
      const b = this.byId.get(edge.target);
      if (!a || !b) continue;

      const from = this.worldToScreen(a.x, a.y);
      const to = this.worldToScreen(b.x, b.y);
      const highlight = this.selected && (edge.source === this.selected || edge.target === this.selected);
      const dim = hasFocus && !(emphasise(a) && emphasise(b));

      ctx.strokeStyle = highlight
        ? 'rgba(139,92,246,0.75)'
        : dark
          ? `rgba(148,163,184,${dim ? 0.06 : 0.16 + Math.min(0.16, edge.weight * 0.03)})`
          : `rgba(100,116,139,${dim ? 0.05 : 0.14 + Math.min(0.16, edge.weight * 0.03)})`;
      ctx.lineWidth = highlight ? 1.6 : 1;
      ctx.beginPath();
      ctx.moveTo(from.x, from.y);
      ctx.lineTo(to.x, to.y);
      ctx.stroke();
    }

    for (const node of this.nodes) {
      const point = this.worldToScreen(node.x, node.y);
      const radius = node.r * Math.max(0.7, this.scale);
      const emphasis = emphasise(node);
      const isProject = node.type === 'project';
      const isSelected = this.selected === node.id;

      ctx.globalAlpha = emphasis ? 1 : 0.22;
      ctx.beginPath();
      ctx.arc(point.x, point.y, radius, 0, Math.PI * 2);
      ctx.fillStyle = this.color(node);

      if (isProject) {
        ctx.globalAlpha = emphasis ? 0.22 : 0.08;
        ctx.fill();
        ctx.globalAlpha = emphasis ? 1 : 0.22;
        ctx.lineWidth = 1.4;
        ctx.strokeStyle = this.color(node);
        ctx.stroke();
      } else {
        ctx.fill();
      }

      if (isSelected || this.hovered === node.id) {
        ctx.lineWidth = isSelected ? 2 : 1.2;
        ctx.strokeStyle = isSelected ? '#f8fafc' : this.color(node);
        ctx.beginPath();
        ctx.arc(point.x, point.y, radius + 3, 0, Math.PI * 2);
        ctx.stroke();
      }

      const labelAlpha = emphasis ? 1 : 0.25;
      const showLabel = this.labelsAlways && (isProject || node.r * this.scale > 3.2 || this.matches.has(node.id) || this.hovered === node.id || isSelected);

      if (showLabel) {
        ctx.globalAlpha = labelAlpha;
        ctx.font = isProject ? '600 12px system-ui, sans-serif' : '11px system-ui, sans-serif';
        ctx.fillStyle = isProject ? (dark ? '#e9d5ff' : '#5b21b6') : textColor;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        const label = node.label.length > 34 ? `${node.label.slice(0, 32)}…` : node.label;
        ctx.fillText(label, point.x, point.y + radius + 4);
      }

      ctx.globalAlpha = 1;
    }
  }

  isNeighbour(id) {
    if (!this.adjacency) {
      this.adjacency = new Map();
      for (const edge of this.edges) {
        if (!this.adjacency.has(edge.source)) this.adjacency.set(edge.source, new Set());
        if (!this.adjacency.has(edge.target)) this.adjacency.set(edge.target, new Set());
        this.adjacency.get(edge.source).add(edge.target);
        this.adjacency.get(edge.target).add(edge.source);
      }
    }

    for (const anchor of this.focus) {
      if (this.adjacency.get(anchor)?.has(id)) return true;
    }

    return false;
  }

  /* ------------------------------------------------------------------ */

  pick(event) {
    const rect = this.canvas.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    let best = null;
    let bestDistance = Infinity;

    for (const node of this.nodes) {
      const point = this.worldToScreen(node.x, node.y);
      const distance = Math.hypot(point.x - x, point.y - y);
      const radius = Math.max(6, node.r * Math.max(0.7, this.scale) + 4);

      if (distance <= radius && distance < bestDistance) {
        best = node;
        bestDistance = distance;
      }
    }

    return best;
  }

  select(id, announce = true) {
    this.selected = id;
    this.draw();

    if (announce && id) {
      const node = this.byId.get(id);
      if (node) this.canvas.dispatchEvent(new CustomEvent('nb-graph:select', { bubbles: true, detail: node }));
    }
  }

  setMatches(ids) {
    this.matches = new Set(ids);
    this.draw();
  }

  setFocus(ids) {
    this.focus = new Set(ids);
    this.alpha = Math.max(this.alpha, 0.12);
    this.start();
  }

  focusOn(id) {
    const node = this.byId.get(id);

    if (!node) return;

    this.select(id);
    this.setFocus([id]);
    this.offsetX = -node.x;
    this.offsetY = -node.y;
    this.scale = Math.max(this.scale, 1.1);
    this.alpha = Math.max(this.alpha, 0.05);
    this.start();
  }

  zoom(factor) {
    this.scale = Math.min(4, Math.max(0.15, this.scale * factor));
    this.draw();
  }

  fit() {
    if (this.nodes.length === 0) return;

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const node of this.nodes) {
      minX = Math.min(minX, node.x);
      minY = Math.min(minY, node.y);
      maxX = Math.max(maxX, node.x);
      maxY = Math.max(maxY, node.y);
    }

    const padding = 60;
    const spanX = Math.max(1, maxX - minX + padding * 2);
    const spanY = Math.max(1, maxY - minY + padding * 2);
    this.scale = Math.min(this.width / spanX, this.height / spanY, 2);
    this.offsetX = -((minX + maxX) / 2);
    this.offsetY = -((minY + maxY) / 2);
    this.draw();
  }

  bind() {
    this.canvas.addEventListener('pointerdown', (event) => {
      this.canvas.setPointerCapture(event.pointerId);
      this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

      if (this.pointers.size === 2) {
        const [a, b] = [...this.pointers.values()];
        this.pinchDistance = Math.hypot(a.x - b.x, a.y - b.y);
      }

      const node = this.pick(event);

      if (node) {
        this.dragging = node.id;
        this.select(node.id);
        return;
      }

      this.dragStart = { x: event.clientX, y: event.clientY, offsetX: this.offsetX, offsetY: this.offsetY };
    });

    this.canvas.addEventListener('pointermove', (event) => {
      this.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

      if (this.pointers.size === 2) {
        const [a, b] = [...this.pointers.values()];
        const distance = Math.hypot(a.x - b.x, a.y - b.y);

        if (this.pinchDistance > 0) {
          this.zoom(distance / this.pinchDistance);
        }

        this.pinchDistance = distance;
        return;
      }

      if (this.dragging) {
        const node = this.byId.get(this.dragging);
        if (node) {
          const world = this.screenToWorld(event.clientX - this.canvas.getBoundingClientRect().left, event.clientY - this.canvas.getBoundingClientRect().top);
          node.x = world.x;
          node.y = world.y;
          node.vx = 0;
          node.vy = 0;
          this.draw();
        }
        return;
      }

      if (this.dragStart) {
        this.offsetX = this.dragStart.offsetX + (event.clientX - this.dragStart.x) / this.scale;
        this.offsetY = this.dragStart.offsetY + (event.clientY - this.dragStart.y) / this.scale;
        this.draw();
        return;
      }

      const node = this.pick(event);
      const id = node ? node.id : null;

      if (id !== this.hovered) {
        this.hovered = id;
        this.canvas.style.cursor = node ? 'pointer' : 'grab';
        this.draw();
      }
    });

    const release = () => {
      this.pointers.clear();
      this.pinchDistance = 0;
      this.dragging = null;
      this.dragStart = null;
    };

    this.canvas.addEventListener('pointerup', release);
    this.canvas.addEventListener('pointercancel', release);
    this.canvas.addEventListener('pointerleave', () => {
      this.hovered = null;
      this.draw();
    });

    this.canvas.addEventListener('dblclick', (event) => {
      const node = this.pick(event);
      if (node?.url) window.location.href = node.url;
    });

    this.canvas.addEventListener('wheel', (event) => {
      event.preventDefault();
      this.zoom(event.deltaY < 0 ? 1.12 : 0.89);
    }, { passive: false });

    this.canvas.addEventListener('contextmenu', (event) => event.preventDefault());

    window.addEventListener('resize', () => this.resize());

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.select(null);
        this.setFocus([]);
      }
    });
  }
}

/* ---------------------------------------------------------------------- */

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]));
}

function renderDetail(container, node, data) {
  if (!node) {
    container.innerHTML = `
      <h3>Карточка узла</h3>
      <p class="nb-peek-text">Кликните узел на графе — здесь появится карточка знания:
      краткий ответ, факты, теги и связи.</p>`;
    return;
  }

  const neighbours = data.edges
    .filter((edge) => edge.source === node.id || edge.target === node.id)
    .map((edge) => data.nodes.find((item) => item.id === (edge.source === node.id ? edge.target : edge.source)))
    .filter(Boolean)
    .slice(0, 8);

  const tags = (node.tags || []).map((tag) => `<span class="nb-chip">#${escapeHtml(tag)}</span>`).join('');

  if (node.type === 'project') {
    container.innerHTML = `
      <div class="nb-card-w-meta" style="margin-bottom:.4rem"><span class="nb-pill">Проект</span></div>
      <h3><a href="${escapeHtml(node.url)}">${escapeHtml(node.label)}</a></h3>
      <div class="nb-peek-meta">в графе: ${node.chunks} записей · связей: ${neighbours.length}</div>
      <div class="nb-card-w-links" style="margin-top:.6rem">
        ${neighbours.map((item) => `
          <a class="nb-card-w-link" href="${escapeHtml(item.url || '#')}">
            <span class="nb-card-w-link-dot"></span>
            <span class="nb-card-w-link-label">${escapeHtml(item.label)}</span>
          </a>`).join('')}
      </div>`;
    return;
  }

  const atoms = (node.atoms || []);
  const keywords = (node.keywords || [])
    .map((word) => `<a class="nb-chip" href="${escapeHtml(window.location.pathname)}?q=${encodeURIComponent(word)}">${escapeHtml(word)}</a>`)
    .join('');

  container.innerHTML = `
    <div class="nb-card-w-meta" style="margin-bottom:.4rem">
      <span class="nb-pill nb-k-${escapeHtml(node.kind)}-pill">${escapeHtml(node.subtitle)}</span>
      ${node.project ? `<span class="nb-card-w-project">${escapeHtml(node.project)}</span>` : ''}
      <span class="nb-card-w-date">важность ${node.importance}/5</span>
    </div>
    <h3><a href="${escapeHtml(node.url)}">${escapeHtml(node.label)}</a></h3>
    <p class="nb-peek-text">${escapeHtml(node.summary)}</p>
    ${atoms.length ? `<div class="nb-atoms">${atoms.map((atom) => `
      <div class="nb-atom">
        <span class="nb-atom-type">${escapeHtml(atom.type)}</span>
        <span class="nb-atom-text">${escapeHtml(atom.text)}</span>
      </div>`).join('')}</div>` : ''}
    ${tags ? `<div class="nb-chip-row" style="margin:.6rem 0">${tags}</div>` : ''}
    ${keywords ? `<div style="font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--nb-muted);margin:.6rem 0 .3rem">Ключевые слова</div>
      <div class="nb-chip-row">${keywords}</div>` : ''}
    <div class="nb-actions" style="margin-top:.7rem">
      <a href="${escapeHtml(node.url)}" class="nb-btn nb-btn-primary">Открыть карточку</a>
    </div>
    ${neighbours.length ? `<div style="font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--nb-muted);margin:.8rem 0 .3rem">Связано с</div>
      <div class="nb-card-w-links">${neighbours.map((item) => `
        <a class="nb-card-w-link" href="${escapeHtml(item.url || '#')}">
          <span class="nb-card-w-link-dot"></span>
          <span class="nb-card-w-link-label">${escapeHtml(item.label)}</span>
          <span class="nb-card-w-link-meta">${item.type === 'project' ? 'проект' : 'запись'}</span>
        </a>`).join('')}</div>` : ''}`;
}

function boot() {
  const canvas = document.getElementById('nb-graph-canvas');
  const payload = document.getElementById('nb-graph-data');

  if (!canvas || !payload) return;

  let data;

  try {
    data = JSON.parse(payload.textContent);
  } catch (error) {
    console.error('nb-graph: bad payload', error);
    return;
  }

  const focus = (canvas.dataset.nbGraphFocus || '').split(',').filter(Boolean);
  const graph = new ForceGraph(canvas, data.nodes, data.edges, { focus });

  const detail = document.getElementById('nb-graph-detail');
  const selectedId = focus[0] || null;

  graph.warmup();
  graph.start();

  if (selectedId) {
    graph.select(selectedId, false);
    renderDetail(detail, data.nodes.find((node) => node.id === selectedId), data);
  }

  canvas.addEventListener('nb-graph:select', (event) => {
    const node = event.detail;
    if (node.type === 'entry') node.atoms = node.atoms || [];
    renderDetail(detail, node, data);
  });

  document.querySelectorAll('[data-nb-graph]').forEach((button) => {
    button.addEventListener('click', () => {
      const action = button.dataset.nbGraph;

      if (action === 'zoom-in') graph.zoom(1.25);
      if (action === 'zoom-out') graph.zoom(0.8);
      if (action === 'fit') graph.fit();
      if (action === 'cluster') {
        graph.colorByProject = !graph.colorByProject;
        graph.draw();
      }
      if (action === 'fullscreen') {
        const wrap = canvas.closest('.nb-graph-wrap');
        if (document.fullscreenElement) {
          document.exitFullscreen();
        } else if (wrap?.requestFullscreen) {
          wrap.requestFullscreen();
          setTimeout(() => graph.resize(), 120);
        }
      }
    });
  });

  const search = document.querySelector('[data-nb-graph-search]');

  if (search) {
    let timer = null;
    search.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const query = search.value.trim();

        if (query.length < 2) {
          graph.setMatches([]);
          return;
        }

        try {
          const response = await fetch(`${window.location.pathname}?${new URLSearchParams({ q: query })}`, {
            headers: { Accept: 'text/html' },
            credentials: 'same-origin',
          });

          if (!response.ok) throw new Error(String(response.status));
          const html = await response.text();
          const doc = new DOMParser().parseFromString(html, 'text/html');
          const fresh = JSON.parse(doc.getElementById('nb-graph-data')?.textContent || '{}');
          const focusIds = (doc.getElementById('nb-graph-canvas')?.dataset.nbGraphFocus || '').split(',').filter(Boolean);
          graph.setMatches(focusIds.length ? focusIds : (fresh.nodes || []).filter((node) => node.type === 'entry').map((node) => node.id));
        } catch (error) {
          graph.setMatches(data.nodes
            .filter((node) => node.type === 'entry' && node.label.toLowerCase().includes(query.toLowerCase()))
            .map((node) => node.id));
        }
      }, 260);
    });
  }
}

boot();
