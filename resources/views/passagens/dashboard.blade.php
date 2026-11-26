@extends('layouts.app')
@section('titulo','Dashboard de viagens')
@section('conteudo')
<style>
  .dv{--i:#4f46e5;font-feature-settings:"tnum"}
  .dv-top{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:14px}
  .dv-top h1{font-size:20px;font-weight:800;color:#0f172a;margin:0;display:flex;align-items:center;gap:10px}
  .dv-top h1 span.ic{display:inline-flex;width:40px;height:40px;border-radius:12px;align-items:center;justify-content:center;font-size:20px;background:linear-gradient(135deg,#4f46e5,#7c3aed);box-shadow:0 8px 18px -8px #4f46e5}
  .dv-top p{margin:2px 0 0;font-size:12px;color:#64748b}
  .dv-filtro{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:12px;margin-bottom:14px}
  .dv-filtro label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:4px}
  .dv-filtro select,.dv-filtro input[type=date]{height:38px;border:1px solid #cbd5e1;border-radius:10px;padding:0 10px;font-size:13px;background:#fff;color:#0f172a}
  .dv-chips{display:flex;flex-wrap:wrap;gap:6px}
  .dv-chips a{height:38px;display:inline-flex;align-items:center;padding:0 12px;border-radius:10px;border:1px solid #e2e8f0;font-size:12px;font-weight:700;color:#475569;background:#fff;text-decoration:none}
  .dv-chips a.on{background:#4f46e5;border-color:#4f46e5;color:#fff}
  .dv-btn{height:38px;padding:0 16px;border-radius:10px;border:0;background:#4f46e5;color:#fff;font-weight:700;font-size:13px;cursor:pointer}
  .dv-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:12px}
  .dv-kpi{position:relative;overflow:hidden;border-radius:18px;padding:16px;color:#fff;min-width:0}
  .dv-kpi::after{content:"";position:absolute;right:-26px;top:-26px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.14)}
  .dv-kpi .l{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85}
  .dv-kpi .v{font-size:30px;font-weight:800;line-height:1.1;margin-top:6px;white-space:nowrap}
  .dv-kpi .s{font-size:11px;opacity:.85;margin-top:4px}
  .dv-kpi .e{position:absolute;right:14px;bottom:10px;font-size:26px;opacity:.9;z-index:1}
  .k1{background:linear-gradient(135deg,#4f46e5,#7c3aed)}.k2{background:linear-gradient(135deg,#0891b2,#06b6d4)}
  .k3{background:linear-gradient(135deg,#059669,#10b981)}.k4{background:linear-gradient(135deg,#d97706,#f59e0b)}
  .dv-dest{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:12px}
  .dv-d{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:12px 14px;display:flex;gap:12px;align-items:center;min-width:0}
  .dv-d .em{flex:none;width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px}
  .dv-d .t{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8}
  .dv-d .n{font-size:15px;font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .dv-d .q{font-size:11px;color:#64748b}
  .dv-d>div:last-child{min-width:0}
  .dv-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:12px}
  .dv-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:16px;min-width:0}
  .dv-card h2{font-size:14px;font-weight:800;color:#0f172a;margin:0}
  .dv-card .sub{font-size:11px;color:#94a3b8;margin:2px 0 12px}
  .dv-card.full{grid-column:1/-1}
  .dv-canvas{position:relative;height:280px}
  .dv-rank{display:flex;flex-direction:column;gap:8px}
  .dv-rank .r{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:10px;align-items:center}
  .dv-rank .p{width:28px;height:28px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;background:#f1f5f9;color:#64748b}
  .dv-rank .r:nth-child(1) .p{background:#fef3c7;color:#b45309}.dv-rank .r:nth-child(2) .p{background:#e2e8f0;color:#475569}.dv-rank .r:nth-child(3) .p{background:#ffedd5;color:#c2410c}
  .dv-rank .nm{font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .dv-rank .bar{height:6px;border-radius:99px;background:#eef2ff;margin-top:4px;overflow:hidden}
  .dv-rank .bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#4f46e5,#06b6d4)}
  .dv-rank .q{font-size:13px;font-weight:800;color:#4f46e5;white-space:nowrap}
  .dv-rank .u{font-size:10px;color:#94a3b8;font-weight:600}
  .dv-tab{width:100%;border-collapse:collapse;font-size:13px}
  .dv-tab th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;padding:6px 8px;border-bottom:1px solid #e2e8f0}
  .dv-tab td{padding:9px 8px;border-bottom:1px solid #f1f5f9;color:#0f172a}
  .dv-tab td.r,.dv-tab th.r{text-align:right}
  .dv-tab tr:first-child td{font-weight:800}
  .dv-vazio{background:#fff;border:1px dashed #cbd5e1;border-radius:18px;padding:40px 16px;text-align:center;color:#64748b}
  @media (max-width:900px){.dv-kpis,.dv-dest{grid-template-columns:repeat(2,minmax(0,1fr))}.dv-grid{grid-template-columns:minmax(0,1fr)}.dv-kpi .v{font-size:24px}}
  @media print{.dv-filtro{display:none}}
</style>
<div class="dv">
  <div class="dv-top">
    <div>
      <h1><span class="ic">📊</span>Dashboard de viagens{{ $emb ? ' · '.$emb->nome : '' }}</h1>
      <p>{{ $ini->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · pela data da viagem · passagens válidas (sem canceladas)</p>
    </div>
  </div>

  <form method="get" action="{{ route('passagens.dashboard') }}" class="dv-filtro">
    <div>
      <label>Período</label>
      <div class="dv-chips">
        @foreach($periodos as $pk => $pl)
          <a href="{{ route('passagens.dashboard', array_filter(['periodo' => $pk, 'transporte_id' => $embId])) }}" class="{{ $periodo === $pk ? 'on' : '' }}">{{ $pl }}</a>
        @endforeach
      </div>
    </div>
    <div><label>De</label><input type="date" name="inicio" value="{{ $ini->toDateString() }}"></div>
    <div><label>Até</label><input type="date" name="fim" value="{{ $fim->toDateString() }}"></div>
    <div>
      <label>🚤 Transporte</label>
      <select name="transporte_id" onchange="this.form.submit()">
        <option value="">Todos</option>
        @foreach($transportes as $t)<option value="{{ $t->id }}" @selected($embId === (int) $t->id)>{{ $t->nome }}</option>@endforeach
      </select>
    </div>
    <button class="dv-btn">Ver</button>
  </form>

  <div id="dv-root"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const DV = @json($dv);
  const root = document.getElementById('dv-root');
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const num = n => Number(n || 0).toLocaleString('pt-BR');
  const dataBr = d => { const p = String(d).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d; };
  const semana = d => ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][new Date(d + 'T12:00:00').getDay()] || '';

  if (!DV.totPax) {
    root.innerHTML = '<div class="dv-vazio"><div style="font-size:34px">🧭</div><b>Nenhuma viagem com passagem neste período.</b><div style="font-size:12px;margin-top:4px">Troque o período ou o transporte no filtro.</div></div>';
    return;
  }

  const d = (em, bg, t, n, q) => `<div class="dv-d"><div class="em" style="background:${bg}">${em}</div><div><div class="t">${t}</div><div class="n" title="${esc(n)}">${esc(n)}</div><div class="q">${q}</div></div></div>`;
  const maxP = Math.max(1, ...DV.pessoas.map(p => p.qtd));
  let h = `
  <div class="dv-kpis">
    <div class="dv-kpi k1"><div class="l">Passageiros</div><div class="v">${num(DV.totPax)}</div><div class="s">pessoas embarcadas no período</div><div class="e">🧑‍🤝‍🧑</div></div>
    <div class="dv-kpi k2"><div class="l">Viagens</div><div class="v">${num(DV.nViagens)}</div><div class="s">saídas com passagem vendida</div><div class="e">🚤</div></div>
    <div class="dv-kpi k3"><div class="l">Média por viagem</div><div class="v">${Number(DV.mediaPax).toLocaleString('pt-BR')}</div><div class="s">passageiros por saída</div><div class="e">📈</div></div>
    <div class="dv-kpi k4"><div class="l">Cidades atendidas</div><div class="v">${num(DV.nDestinos)}</div><div class="s">destinos com passageiro</div><div class="e">📍</div></div>
  </div>
  <div class="dv-grid">
    <div class="dv-card"><h2>🍕 Rotas que mais levam passageiros</h2><div class="sub">Participação de cada rota no total de passageiros</div><div class="dv-canvas"><canvas id="dv-rotas"></canvas></div></div>
    <div class="dv-card">${DV.embNome
      ? `<h2>📅 Passageiros por dia da semana</h2><div class="sub">Em que dia ${esc(DV.embNome)} mais leva gente</div><div class="dv-canvas"><canvas id="dv-dias"></canvas></div>`
      : `<h2>🚤 Lanchas que mais viajam</h2><div class="sub">Número de viagens e de passageiros por lancha</div><div class="dv-canvas"><canvas id="dv-lanchas"></canvas></div>`}</div>
  </div>
  <div class="dv-dest">
    ${DV.embNome
      ? d('🗓️', '#eef2ff', 'Mês que mais viajou', DV.melhorMes ? DV.melhorMes.label : '—', DV.melhorMes ? num(DV.melhorMes.pax) + ' passageiros · ' + num(DV.melhorMes.viagens) + ' viagens' : '')
      : d('🏆', '#eef2ff', 'Lancha que mais viaja', DV.lanchaTop ? DV.lanchaTop.nome : '—', DV.lanchaTop ? num(DV.lanchaTop.viagens) + ' viagens · ' + num(DV.lanchaTop.pax) + ' passageiros' : '')}
    ${d('🧭', '#ecfeff', 'Rota campeã', DV.rotaTop ? DV.rotaTop.nome : '—', DV.rotaTop ? num(DV.rotaTop.pax) + ' passageiros' : '')}
    ${d('📍', '#ecfdf5', 'Cidade que mais recebe', DV.destinoTop ? DV.destinoTop.nome : '—', DV.destinoTop ? num(DV.destinoTop.pax) + ' passageiros' : '')}
    ${DV.embNome
      ? d('📅', '#fffbeb', 'Dia mais forte', DV.diaTop || '—', 'dia da semana com mais passageiros')
      : d('🗓️', '#fffbeb', 'Melhor mês', DV.melhorMes ? DV.melhorMes.label : '—', DV.melhorMes ? num(DV.melhorMes.pax) + ' passageiros' : '')}
  </div>`;

  if (DV.embNome && DV.viagens.length) {
    h += `<div class="dv-grid"><div class="dv-card full"><h2>🚤 Viagens que mais venderam — ${esc(DV.embNome)}</h2><div class="sub">As 10 saídas com mais passageiros no período</div>
      <div style="overflow-x:auto"><table class="dv-tab"><thead><tr><th>#</th><th>Data da viagem</th><th class="r">Passageiros</th><th class="r">Passagens</th></tr></thead><tbody>
      ${DV.viagens.map((v, i) => `<tr><td>${i === 0 ? '🥇' : i + 1}</td><td>${dataBr(v.data)} <span style="color:#94a3b8;font-weight:500">· ${semana(v.data)}</span></td><td class="r">${num(v.pax)}</td><td class="r">${num(v.pass)}</td></tr>`).join('')}
      </tbody></table></div></div></div>`;
  }

  h += `
  <div class="dv-grid">
    <div class="dv-card"><h2>📈 Mês a mês</h2><div class="sub">Passageiros e viagens em cada mês</div><div class="dv-canvas"><canvas id="dv-meses"></canvas></div></div>
    <div class="dv-card"><h2>📍 Cidades que mais recebem passageiros</h2><div class="sub">Pelo destino da passagem</div><div class="dv-canvas"><canvas id="dv-destinos"></canvas></div></div>
    <div class="dv-card"><h2>🧑‍🤝‍🧑 Passageiros que mais viajam</h2><div class="sub">Top 10 por número de viagens no período</div>
      <div class="dv-rank">${DV.pessoas.map((p, i) => `<div class="r"><div class="p">${i + 1}</div><div><div class="nm" title="${esc(p.nome)}">${esc(p.nome)}</div><div class="bar"><i style="width:${Math.round(p.qtd / maxP * 100)}%"></i></div></div><div style="text-align:right"><div class="q">${num(p.qtd)}×</div><div class="u">última ${dataBr(p.ultima)}</div></div></div>`).join('') || '<div class="sub">Sem passageiros identificados.</div>'}</div></div>
    <div class="dv-card">${DV.embNome
      ? `<h2>🗓️ Resumo de ${esc(DV.embNome)}</h2><div class="sub">Números do período filtrado</div><table class="dv-tab"><tbody>
          <tr><td>Viagens realizadas</td><td class="r">${num(DV.nViagens)}</td></tr><tr><td>Passageiros levados</td><td class="r"><b>${num(DV.totPax)}</b></td></tr>
          <tr><td>Média de passageiros por viagem</td><td class="r"><b>${Number(DV.mediaPax).toLocaleString('pt-BR')}</b></td></tr>
          <tr><td>Mês que mais viajou</td><td class="r"><b>${DV.melhorMes ? esc(DV.melhorMes.label) : '—'}</b></td></tr>
          <tr><td>Rota mais vendida</td><td class="r"><b>${DV.rotaTop ? esc(DV.rotaTop.nome) : '—'}</b></td></tr>
          <tr><td>Cidades atendidas</td><td class="r"><b>${num(DV.nDestinos)}</b></td></tr></tbody></table>`
      : `<h2>📅 Passageiros por dia da semana</h2><div class="sub">Em que dia a agência mais embarca gente</div><div class="dv-canvas"><canvas id="dv-dias"></canvas></div>`}</div>
  </div>`;
  root.innerHTML = h;

  if (!window.Chart) return;
  const CORES = ['#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#94a3b8'];
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 12;
  Chart.defaults.locale = 'pt-BR';
  Chart.defaults.color = '#64748b';
  const grade = {color: '#f1f5f9'}, semGrade = {display: false};
  const el = id => document.getElementById(id);
  const total = DV.graf.rotas.valores.reduce((a, b) => a + b, 0) || 1;

  new Chart(el('dv-rotas'), {type: 'pie',
    data: {labels: DV.graf.rotas.labels, datasets: [{data: DV.graf.rotas.valores, backgroundColor: CORES, borderColor: '#fff', borderWidth: 3, hoverOffset: 10}]},
    options: {maintainAspectRatio: false, layout: {padding: 6},
      plugins: {legend: {position: 'right', labels: {usePointStyle: true, pointStyle: 'circle', padding: 12, font: {weight: '600'},
        generateLabels: c => c.data.labels.map((l, i) => ({text: l + '  ' + Math.round(c.data.datasets[0].data[i] / total * 100) + '%', fillStyle: CORES[i % CORES.length], strokeStyle: '#fff', pointStyle: 'circle', index: i, hidden: !c.getDataVisibility(i)}))}},
        tooltip: {callbacks: {label: c => ' ' + num(c.parsed) + ' passageiros (' + Math.round(c.parsed / total * 100) + '%)'}}}}});

  if (el('dv-lanchas')) new Chart(el('dv-lanchas'), {type: 'bar',
    data: {labels: DV.graf.lanchas.labels, datasets: [
      {label: 'Viagens', data: DV.graf.lanchas.viagens, backgroundColor: '#4f46e5', borderRadius: 6, yAxisID: 'y', maxBarThickness: 28},
      {label: 'Passageiros', data: DV.graf.lanchas.pax, backgroundColor: '#06b6d4', borderRadius: 6, yAxisID: 'y1', maxBarThickness: 28}]},
    options: {maintainAspectRatio: false, plugins: {legend: {position: 'top', align: 'end', labels: {usePointStyle: true, pointStyle: 'circle'}}},
      scales: {x: {grid: semGrade, ticks: {font: {weight: '600'}}}, y: {beginAtZero: true, grid: grade, ticks: {precision: 0}, title: {display: true, text: 'viagens'}}, y1: {beginAtZero: true, position: 'right', grid: semGrade, ticks: {precision: 0}, title: {display: true, text: 'passageiros'}}}}});

  const ctxM = el('dv-meses').getContext('2d'); const g = ctxM.createLinearGradient(0, 0, 0, 280); g.addColorStop(0, 'rgba(79,70,229,.30)'); g.addColorStop(1, 'rgba(79,70,229,0)');
  new Chart(ctxM, {data: {labels: DV.graf.meses.labels, datasets: [
      {type: 'line', label: 'Passageiros', data: DV.graf.meses.pax, borderColor: '#4f46e5', backgroundColor: g, fill: true, tension: .35, borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderWidth: 2, yAxisID: 'y'},
      {type: 'bar', label: 'Viagens', data: DV.graf.meses.viagens, backgroundColor: 'rgba(6,182,212,.55)', borderRadius: 6, maxBarThickness: 26, yAxisID: 'y1'}]},
    options: {maintainAspectRatio: false, plugins: {legend: {position: 'top', align: 'end', labels: {usePointStyle: true, pointStyle: 'circle'}}},
      scales: {x: {grid: semGrade}, y: {beginAtZero: true, grid: grade, ticks: {precision: 0}}, y1: {beginAtZero: true, position: 'right', grid: semGrade, ticks: {precision: 0}}}}});

  new Chart(el('dv-destinos'), {type: 'bar',
    data: {labels: DV.graf.destinos.labels, datasets: [{label: 'Passageiros', data: DV.graf.destinos.valores, backgroundColor: DV.graf.destinos.labels.map((_, i) => i === 0 ? '#10b981' : '#a7f3d0'), borderRadius: 6, maxBarThickness: 22}]},
    options: {indexAxis: 'y', maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true, grid: grade, ticks: {precision: 0}}, y: {grid: semGrade, ticks: {font: {weight: '600'}}}}}});

  const mxD = Math.max(...DV.graf.dias.valores);
  new Chart(el('dv-dias'), {type: 'bar',
    data: {labels: DV.graf.dias.labels, datasets: [{label: 'Passageiros', data: DV.graf.dias.valores, backgroundColor: DV.graf.dias.valores.map(v => v === mxD ? '#f59e0b' : '#fde68a'), borderRadius: 8, maxBarThickness: 38}]},
    options: {maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {grid: semGrade, ticks: {font: {weight: '600'}}}, y: {beginAtZero: true, grid: grade, ticks: {precision: 0}}}}});
})();
</script>
@endsection
