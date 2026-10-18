/* FBRE — service worker: deixa o sistema abrir sem internet.
 * Estratégia: páginas = rede primeiro, cache como reserva; libs externas (Tailwind, Alpine, fontes) = cache primeiro.
 */
const VERSAO = 'fbre-v3';
const SHELL = ['/encomendas/nova', '/encomendas', '/offline', '/manifest.json', '/img/icon.png', '/js/fbre-offline.js'];
const LIBS = [
  'https://cdn.tailwindcss.com',
  'https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
];

self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    const c = await caches.open(VERSAO);
    await Promise.allSettled([...SHELL, ...LIBS].map((u) => c.add(new Request(u, { mode: u.startsWith('http') ? 'no-cors' : 'same-origin' }))));
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k !== VERSAO) await caches.delete(k);
    self.clients.claim();
  })());
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET') return;                     // POST/sync nunca passa pelo cache
  if (url.pathname.startsWith('/sync/') || url.pathname.startsWith('/api/')) return;
  if (url.pathname === '/login' || url.pathname === '/logout') return;

  // libs externas e fontes: cache primeiro
  if (url.origin !== location.origin) {
    e.respondWith((async () => {
      const c = await caches.open(VERSAO);
      const hit = await c.match(req, { ignoreVary: true });
      if (hit) return hit;
      try { const r = await fetch(req); if (r && (r.ok || r.type === 'opaque')) c.put(req, r.clone()); return r; }
      catch { return hit || Response.error(); }
    })());
    return;
  }

  // páginas e assets locais: rede primeiro, cache como reserva
  e.respondWith((async () => {
    const c = await caches.open(VERSAO);
    try {
      const r = await fetch(req);
      if (r.ok && (req.mode === 'navigate' || url.pathname.startsWith('/img/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/storage/'))) {
        c.put(req, r.clone());
      }
      return r;
    } catch {
      const hit = await c.match(req, { ignoreSearch: req.mode === 'navigate' });
      if (hit) return hit;
      if (req.mode === 'navigate') return (await c.match('/encomendas/nova')) || (await c.match('/offline')) || Response.error();
      return Response.error();
    }
  })());
});

self.addEventListener('message', (e) => { if (e.data === 'skipWaiting') self.skipWaiting(); });
