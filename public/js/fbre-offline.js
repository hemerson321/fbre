/* FBRE Offline — banco local (IndexedDB), fila de sincronização e impressão sem internet.
 * Regra de ouro: NADA é apagado do aparelho antes do servidor confirmar.
 */
(function () {
  const DB_NOME = 'fbre', DB_VER = 1;
  let db;

  // ---------------------------------------------------------------- IndexedDB
  function abrir() {
    if (db) return Promise.resolve(db);
    return new Promise((res, rej) => {
      const r = indexedDB.open(DB_NOME, DB_VER);
      r.onupgradeneeded = () => {
        const d = r.result;
        if (!d.objectStoreNames.contains('fila')) d.createObjectStore('fila', { keyPath: 'uuid' });      // encomendas/baixas pendentes
        if (!d.objectStoreNames.contains('enviadas')) d.createObjectStore('enviadas', { keyPath: 'uuid' }); // já confirmadas (histórico local)
        if (!d.objectStoreNames.contains('kv')) d.createObjectStore('kv');                                 // bootstrap, config
      };
      r.onsuccess = () => { db = r.result; res(db); };
      r.onerror = () => rej(r.error);
    });
  }
  function tx(store, modo, fn) {
    return abrir().then((d) => new Promise((res, rej) => {
      const t = d.transaction(store, modo); const s = t.objectStore(store); const out = fn(s);
      t.oncomplete = () => res(out && out.result !== undefined ? out.result : out);
      t.onerror = () => rej(t.error);
    }));
  }
  const kvGet = (k) => tx('kv', 'readonly', (s) => s.get(k));
  const kvSet = (k, v) => tx('kv', 'readwrite', (s) => s.put(v, k));
  const filaTodos = () => tx('fila', 'readonly', (s) => s.getAll());
  const filaPut = (o) => tx('fila', 'readwrite', (s) => s.put(o));
  const filaDel = (uuid) => tx('fila', 'readwrite', (s) => s.delete(uuid));
  const enviadasPut = (o) => tx('enviadas', 'readwrite', (s) => s.put(o));

  // ---------------------------------------------------------------- util
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); }));
  const moeda = (v) => (Number(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const csrf = () => (document.querySelector('meta[name=csrf-token]') || {}).content;
  const online = () => navigator.onLine;

  // ---------------------------------------------------------------- bootstrap (catálogo + empresa)
  async function atualizarBootstrap() {
    try {
      const r = await fetch('/sync/bootstrap', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!r.ok) throw new Error(r.status);
      const j = await r.json();
      await kvSet('bootstrap', j);
      return j;
    } catch { return kvGet('bootstrap'); }
  }

  // ---------------------------------------------------------------- envio
  async function enviarUm(item) {
    const url = item.tipo === 'encomenda' ? '/sync/encomenda' : '/sync/baixa';
    const r = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ ...item.dados, uuid: item.uuid, offline: true }),
    });
    if (r.status === 419) { // sessão/CSRF expirou: renova e tenta de novo depois
      await atualizarBootstrap(); throw new Error('csrf');
    }
    if (r.status === 401) throw new Error('login');
    if (r.status === 422) { // dado inválido: não fica preso na fila pra sempre
      const j = await r.json().catch(() => ({}));
      item.erro = 'Rejeitada: ' + JSON.stringify(j.errors || j); item.falhas = (item.falhas || 0) + 1; await filaPut(item);
      throw new Error('422');
    }
    if (r.status === 409) { const j = await r.json(); if (j.reter) { item.falhas = (item.falhas || 0) + 1; await filaPut(item); } throw new Error('409'); }
    if (!r.ok) throw new Error(r.status);
    const j = await r.json();
    await enviadasPut({ uuid: item.uuid, tipo: item.tipo, resposta: j, em: Date.now(), dados: item.dados });
    await filaDel(item.uuid);
    return j;
  }

  let sincronizando = false;
  async function sincronizar() {
    if (sincronizando || !online()) return;
    sincronizando = true;
    try {
      const fila = (await filaTodos()).sort((a, b) => a.em - b.em);
      // encomendas primeiro, depois baixas (a baixa pode depender da encomenda)
      for (const item of [...fila.filter((i) => i.tipo === 'encomenda'), ...fila.filter((i) => i.tipo !== 'encomenda')]) {
        if ((item.falhas || 0) >= 20) continue;
        try { await enviarUm(item); } catch (e) { if (['login', 'csrf'].includes(e.message)) break; }
      }
    } finally { sincronizando = false; atualizarIndicador(); }
  }

  // ---------------------------------------------------------------- API pública
  const FBREOffline = {
    uuid, moeda, online,
    pendentes: async () => (await filaTodos()).length,
    fila: filaTodos,
    bootstrap: () => kvGet('bootstrap'),
    sincronizar,

    /** Salva a encomenda localmente e tenta enviar. Retorna {local, servidor?} */
    async salvarEncomenda(dados) {
      const id = dados.uuid || uuid();
      const item = { uuid: id, tipo: 'encomenda', em: Date.now(), dados: { ...dados, uuid: id, criada_em: new Date().toISOString(), numero_provisorio: 'P-' + id.slice(0, 6).toUpperCase() } };
      await filaPut(item);                       // 1) gravado no aparelho
      let servidor = null;
      if (online()) { try { servidor = await enviarUm(item); } catch (e) { servidor = null; } }
      atualizarIndicador();
      return { local: item, servidor };
    },

    async salvarBaixa(dados) {
      const id = uuid();
      const item = { uuid: id, tipo: 'baixa', em: Date.now(), dados: { ...dados } };
      await filaPut(item);
      let servidor = null;
      if (online()) { try { servidor = await enviarUm(item); } catch (e) { servidor = null; } }
      atualizarIndicador();
      return { local: item, servidor };
    },

    /** Comprovante 80mm gerado no aparelho (quando não há internet). */
    async imprimirComprovanteLocal(item) {
      const b = (await kvGet('bootstrap')) || { empresa: {} };
      const e = b.empresa || {}; const d = item.dados;
      const sub = d.itens.reduce((s, i) => s + i.quantidade * i.valor_unitario, 0);
      const desc = Number(d.desconto) || 0; const total = Math.max(sub - desc, 0);
      const pago = (d.pagamentos || []).reduce((s, p) => s + (Number(p.valor) || 0), 0); const devido = Math.max(total - pago, 0);
      const formas = { avista: 'À vista', areceber: 'A receber', faturado: 'Faturado' };
      const esc = (s) => String(s ?? '').replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
      const linhasItens = d.itens.map((i) => `<tr><td>${esc(i.descricao)}</td><td class="r">${i.quantidade}x ${moeda(i.valor_unitario).replace('R$', '').trim()}</td><td class="r b">${moeda(i.quantidade * i.valor_unitario).replace('R$', '').trim()}</td></tr>`).join('');
      const etiquetas = Array.from({ length: Number(d.volumes) || 1 }, (_, v) => `
        <div class="quebra"><div class="c b lg">${esc((e.nome || '').toUpperCase())}</div>
        <div class="box c"><div class="b" style="font-size:26px">Nº ${esc(d.numero_provisorio)}</div><div class="b lg">VOLUME ${v + 1}/${d.volumes}</div></div>
        <div class="b lg">DESTINO: ${esc(d.destino.toUpperCase())}</div><div class="sm">Origem: ${esc(d.origem)}</div><hr>
        <div class="b">PARA: ${esc(d.destinatario_nome.toUpperCase())}</div><div>${esc(d.destinatario_telefone || '')}</div>
        <div class="sm">DE: ${esc(d.remetente_nome)} ${esc(d.remetente_telefone || '')}</div><hr>
        <table><tr><td class="sm">${new Date().toLocaleString('pt-BR')}</td><td class="r b">${devido > 0 ? 'A RECEBER ' + moeda(devido) : 'PAGO'}</td></tr></table><br></div>`).join('');
      const html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Comprovante ${esc(d.numero_provisorio)}</title>
      <style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px;color:#000}
      .c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px}hr{border:0;border-top:1px dashed #000;margin:4px 0}
      table{width:100%;border-collapse:collapse}td{padding:1px 0;vertical-align:top}.box{border:1px solid #000;padding:2mm;margin:2mm 0}.quebra{page-break-after:always}
      .aviso{border:1px solid #000;padding:2px;text-align:center;font-size:10px;margin:3px 0}</style></head><body onload="window.print()">
      <div class="c"><div class="b lg">${esc((e.nome || 'FBRE').toUpperCase())}</div>${e.cnpj ? `<div class="sm">CNPJ ${esc(e.cnpj)}</div>` : ''}${e.telefone || e.whatsapp ? `<div class="sm">${esc(e.telefone || '')} ${e.whatsapp ? '· WhatsApp ' + esc(e.whatsapp) : ''}</div>` : ''}</div><hr>
      <div class="c b xl">ENCOMENDA Nº ${esc(d.numero_provisorio)}</div>
      <div class="aviso">Nº PROVISÓRIO — emitido sem internet. O número definitivo será atribuído na sincronização.</div>
      <div class="c sm">${new Date().toLocaleString('pt-BR')} · Operador: ${esc((b.usuario || {}).nome || '')}</div><hr>
      <table><tr><td class="b">ORIGEM</td><td class="r">${esc(d.origem)}</td></tr><tr><td class="b">DESTINO</td><td class="r">${esc(d.destino)}</td></tr><tr><td class="b">VOLUMES</td><td class="r">${d.volumes}</td></tr></table><hr>
      <div class="b">REMETENTE</div><div>${esc(d.remetente_nome)}</div><div class="sm">${esc(d.remetente_telefone || '')} ${esc(d.remetente_documento || '')}</div>
      <div class="b" style="margin-top:3px">DESTINATÁRIO</div><div>${esc(d.destinatario_nome)}</div><div class="sm">${esc(d.destinatario_telefone || '')} ${esc(d.destinatario_documento || '')}</div><hr>
      <table>${linhasItens}</table><hr>
      <table>${desc > 0 ? `<tr><td>Subtotal</td><td class="r">${moeda(sub)}</td></tr><tr><td>Desconto</td><td class="r">-${moeda(desc)}</td></tr>` : ''}
      <tr class="lg"><td class="b">TOTAL</td><td class="r b">${moeda(total)}</td></tr><tr><td>Pago</td><td class="r">${moeda(pago)}</td></tr><tr><td class="b">DEVIDO</td><td class="r b">${moeda(devido)}</td></tr><tr><td>Forma</td><td class="r">${formas[d.forma] || d.forma}</td></tr></table>
      ${d.observacao ? `<hr><div class="sm">Obs: ${esc(d.observacao)}</div>` : ''}${e.pix ? `<hr><div class="c sm">PIX: ${esc(e.pix)}</div>` : ''}${e.rodape ? `<hr><div class="c sm">${esc(e.rodape)}</div>` : ''}
      <hr><div class="c sm">Apresente este comprovante para retirar a encomenda.</div><br><br>
      <div class="quebra"></div>${etiquetas}</body></html>`;
      const w = window.open('', 'fbre_print', 'width=420,height=640'); if (!w) return alert('Permita pop-ups para imprimir.');
      w.document.open(); w.document.write(html); w.document.close();
    },
  };
  window.FBREOffline = FBREOffline;

  // ---------------------------------------------------------------- indicador no topo
  async function atualizarIndicador() {
    const el = document.getElementById('fbre-net'); if (!el) return;
    const n = await FBREOffline.pendentes();
    const on = online();
    el.className = 'flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-semibold ' + (on ? (n ? 'bg-amber-400 text-slate-900' : 'bg-emerald-500/90 text-white') : 'bg-red-500 text-white');
    el.innerHTML = `<span class="inline-block w-2 h-2 rounded-full ${on ? 'bg-white' : 'bg-white animate-pulse'}"></span>` +
      (on ? (n ? `online · ${n} pendente${n > 1 ? 's' : ''}` : 'online') : (n ? `offline · ${n} na fila` : 'offline'));
    el.title = on ? (n ? 'Enviando dados pendentes…' : 'Tudo sincronizado') : 'Sem internet: os cadastros ficam guardados neste aparelho e sobem quando a conexão voltar.';
  }

  // ---------------------------------------------------------------- init
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
  window.addEventListener('online', () => { atualizarIndicador(); sincronizar(); });
  window.addEventListener('offline', atualizarIndicador);
  window.addEventListener('beforeunload', (ev) => { /* aviso só se houver pendências */ });
  document.addEventListener('DOMContentLoaded', async () => {
    atualizarIndicador();
    if (online()) { await atualizarBootstrap(); sincronizar(); }
    setInterval(() => { if (online()) sincronizar(); }, 15000);
    document.getElementById('fbre-net')?.addEventListener('click', () => { atualizarBootstrap(); sincronizar(); });
  });
})();
