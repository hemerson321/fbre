@extends('layouts.app')
@section('titulo', ($home ?? false) ? 'Início' : 'Dashboard de viagens')
@section('conteudo')
@php
  $dvU = auth()->user();
  $dvEmpresa = \App\Support\Tenant::empresa();
  $dvH = now()->hour;
  $dvSaud = $dvH < 12 ? 'Bom dia' : ($dvH < 18 ? 'Boa tarde' : 'Boa noite');
  $dvPrimeiro = \Illuminate\Support\Str::of((string) $dvU->name)->explode(' ')->first();
  $dvNomeEmp = $dvEmpresa?->nomeExibicao() ?? 'FBRE';
  $dvCustom = ($periodo ?? '') === 'custom';
@endphp
<style>
  .dv{font-feature-settings:"tnum"}
  .dv-hero{display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;border-radius:18px;padding:12px 16px;margin-bottom:10px;color:#fff;background:linear-gradient(120deg,var(--cor1,#1e3a8a),color-mix(in srgb,var(--cor1,#1e3a8a) 62%,#7c3aed))}
  .dv-hero .logo{flex:none;width:44px;height:44px;border-radius:12px;background:#fff;padding:3px;object-fit:contain}
  .dv-hero .ic{flex:none;width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:22px}
  .dv-hero h1{font-size:19px;font-weight:800;margin:0;line-height:1.15}
  .dv-hero p{margin:2px 0 0;font-size:12px;color:rgba(255,255,255,.8)}
  .dv-fil{margin-left:auto;display:flex;flex-wrap:wrap;align-items:center;gap:6px}
  .dv-fil a,.dv-fil button.d{height:32px;display:inline-flex;align-items:center;padding:0 11px;border-radius:9px;font-size:12px;font-weight:700;color:#fff;background:rgba(255,255,255,.14);text-decoration:none;border:0;cursor:pointer}
  .dv-fil a.on,.dv-fil button.d.on{background:#fff;color:#1e293b}
  .dv-fil select,.dv-fil input[type=date]{height:32px;border:0;border-radius:9px;padding:0 8px;font-size:12px;font-weight:700;background:#fff;color:#1e293b;max-width:190px}
  .dv-fil .ok{height:32px;padding:0 12px;border-radius:9px;border:0;background:#fbbf24;color:#1e293b;font-weight:800;font-size:12px;cursor:pointer}
  .dv-datas{display:none;align-items:center;gap:6px}.dv-datas.on{display:flex}
  .dv-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:10px}
  .dv-kpi{position:relative;overflow:hidden;border-radius:16px;padding:11px 14px;color:#fff;min-width:0}
  .dv-kpi::after{content:"";position:absolute;right:-22px;top:-22px;width:86px;height:86px;border-radius:50%;background:rgba(255,255,255,.14)}
  .dv-kpi .l{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85}
  .dv-kpi .v{font-size:26px;font-weight:800;line-height:1.1;margin-top:2px;white-space:nowrap}
  .dv-kpi .s{font-size:11px;opacity:.92;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-right:30px}
  .dv-kpi .s b{font-weight:800}
  .dv-kpi .e{position:absolute;right:12px;bottom:8px;font-size:22px;z-index:1}
  .k1{background:linear-gradient(135deg,#4f46e5,#7c3aed)}.k2{background:linear-gradient(135deg,#0891b2,#06b6d4)}
  .k3{background:linear-gradient(135deg,#059669,#10b981)}.k4{background:linear-gradient(135deg,#d97706,#f59e0b)}
  .dv-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:10px}
  .dv-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:12px 14px;min-width:0;display:flex;flex-direction:column}
  .dv-card h2{font-size:13px;font-weight:800;color:#0f172a;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .dv-card .sub{font-size:10.5px;color:#94a3b8;margin:1px 0 8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .dv-canvas{position:relative;flex:1;min-height:0}
  .dv-a{height:clamp(200px,calc((100vh - 380px)*.55),330px)}.dv-b{height:clamp(170px,calc((100vh - 380px)*.45),270px)}
  .dv-rank{display:flex;flex-direction:column;gap:5px;flex:1;justify-content:space-between}
  .dv-rank .r{display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:8px;align-items:center}
  .dv-rank .p{width:22px;height:22px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;background:#f1f5f9;color:#64748b}
  .dv-rank .r:nth-child(1) .p{background:#fef3c7;color:#b45309}.dv-rank .r:nth-child(2) .p{background:#e2e8f0;color:#475569}.dv-rank .r:nth-child(3) .p{background:#ffedd5;color:#c2410c}
  .dv-rank .nm{font-size:12px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .dv-rank .bar{height:4px;border-radius:99px;background:#eef2ff;margin-top:2px;overflow:hidden}
  .dv-rank .bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#4f46e5,#06b6d4)}
  .dv-rank .q{font-size:12px;font-weight:800;color:#4f46e5;white-space:nowrap}
  .dv-tab{width:100%;border-collapse:collapse;font-size:12px}
  .dv-tab th{text-align:left;font-size:9.5px;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;padding:3px 6px;border-bottom:1px solid #e2e8f0}
  .dv-tab td{padding:6px;border-bottom:1px solid #f1f5f9;color:#0f172a}
  .dv-tab td.r,.dv-tab th.r{text-align:right}
  .dv-tab tbody tr:first-child td{font-weight:800}
  .dv-vazio{background:#fff;border:1px dashed #cbd5e1;border-radius:16px;padding:40px 16px;text-align:center;color:#64748b}
  @media (max-width:1100px){.dv-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:720px){.dv-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dv-grid{grid-template-columns:minmax(0,1fr)}.dv-fil{margin-left:0}.dv-kpi .v{font-size:22px}}
  @media print{.dv-fil{display:none}}
</style>
<div class="dv">
  <form method="get" action="{{ route($rotaForm) }}" class="dv-hero" id="dv-form">
    @if($home && $dvEmpresa?->logo)<img class="logo" onerror="this.style.display='none'" src="/{{ $dvEmpresa->logo }}" alt="">@else<span class="ic">📊</span>@endif
    <div style="min-width:0">
      @if($home)
        <h1>{{ $dvSaud }}, {{ $dvPrimeiro }} 👋</h1>
        <p>Bem-vindo(a) à {{ $dvNomeEmp }} · viagens de {{ $ini->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}{{ $emb ? ' · '.$emb->nome : '' }}</p>
      @else
        <h1>Dashboard de viagens{{ $emb ? ' · '.$emb->nome : '' }}</h1>
        <p>{{ $ini->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · pela data da viagem · sem canceladas</p>
      @endif
    </div>
    <div class="dv-fil">
      @foreach($periodos as $pk => $pl)
        <a href="{{ route($rotaForm, array_filter(['periodo' => $pk, 'transporte_id' => $embId])) }}" class="{{ $periodo === $pk ? 'on' : '' }}">{{ $pl }}</a>
      @endforeach
      <button type="button" class="d {{ $dvCustom ? 'on' : '' }}" onclick="var d=document.getElementById('dv-datas');d.classList.toggle('on');d.querySelectorAll('input').forEach(function(i){i.disabled=!d.classList.contains('on')})">📅 Datas</button>
      <span class="dv-datas {{ $dvCustom ? 'on' : '' }}" id="dv-datas">
        <input type="date" name="inicio" value="{{ $ini->toDateString() }}" @disabled(! $dvCustom)>
        <input type="date" name="fim" value="{{ $fim->toDateString() }}" @disabled(! $dvCustom)>
        <button class="ok">Ver</button>
      </span>
      @unless($dvCustom)<input type="hidden" name="periodo" value="{{ $periodo }}">@endunless
      <select name="transporte_id" onchange="this.form.submit()" title="Filtrar por transporte">
        <option value="">🚤 Todas as lanchas</option>
        @foreach($transportes as $t)<option value="{{ $t->id }}" @selected($embId === (int) $t->id)>{{ $t->nome }}</option>@endforeach
      </select>
    </div>
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
    root.innerHTML = '<div class="dv-vazio"><div style="font-size:34px">🧭</div><b>Nenhuma viagem com passagem neste período.</b><div style="font-size:12px;margin-top:4px">Troque o período ou a lancha no filtro.</div></div>';
    return;
  }

  const pessoas = DV.pessoas.slice(0, 6), maxP = Math.max(1, ...pessoas.map(p => p.qtd));
  const viagens = DV.viagens.slice(0, 5);
  const card = (cls, t, s, corpo) => `<div class="dv-card ${cls}"><h2>${t}</h2><div class="sub">${s}</div>${corpo}</div>`;
  const cv = id => `<div class="dv-canvas"><canvas id="${id}"></canvas></div>`;
  const rank = `<div class="dv-rank">${pessoas.map((p, i) => `<div class="r"><div class="p">${i + 1}</div><div><div class="nm" title="${esc(p.nome)} · última ${dataBr(p.ultima)}">${esc(p.nome)}</div><div class="bar"><i style="width:${Math.round(p.qtd / maxP * 100)}%"></i></div></div><div class="q">${num(p.qtd)}×</div></div>`).join('') || '<div class="sub">Sem passageiros identificados.</div>'}</div>`;
  const tabV = `<table class="dv-tab"><thead><tr><th>#</th><th>Data</th><th class="r">Passageiros</th><th class="r">Passagens</th></tr></thead><tbody>${viagens.map((v, i) => `<tr><td>${i === 0 ? '🥇' : i + 1}</td><td>${dataBr(v.data)} <span style="color:#94a3b8;font-weight:500">${semana(v.data)}</span></td><td class="r">${num(v.pax)}</td><td class="r">${num(v.pass)}</td></tr>`).join('')}</tbody></table>`;

  root.innerHTML = `
  <div class="dv-kpis">
    <div class="dv-kpi k1"><div class="l">Passageiros</div><div class="v">${num(DV.totPax)}</div><div class="s">melhor mês: <b>${DV.melhorMes ? esc(DV.melhorMes.label) + ' (' + num(DV.melhorMes.pax) + ')' : '—'}</b></div><div class="e">🧑‍🤝‍🧑</div></div>
    <div class="dv-kpi k2"><div class="l">Viagens</div><div class="v">${num(DV.nViagens)}</div><div class="s">${DV.embNome ? 'dia mais forte: <b>' + esc(DV.diaTop || '—') + '</b>' : 'quem mais viaja: <b>' + (DV.lanchaTop ? esc(DV.lanchaTop.nome) + ' (' + num(DV.lanchaTop.viagens) + ')' : '—') + '</b>'}</div><div class="e">🚤</div></div>
    <div class="dv-kpi k3"><div class="l">Média por viagem</div><div class="v">${Number(DV.mediaPax).toLocaleString('pt-BR')}</div><div class="s">rota campeã: <b>${DV.rotaTop ? esc(DV.rotaTop.nome) : '—'}</b></div><div class="e">📈</div></div>
    <div class="dv-kpi k4"><div class="l">Cidades atendidas</div><div class="v">${num(DV.nDestinos)}</div><div class="s">mais recebe: <b>${DV.destinoTop ? esc(DV.destinoTop.nome) + ' (' + num(DV.destinoTop.pax) + ')' : '—'}</b></div><div class="e">📍</div></div>
  </div>
  <div class="dv-grid">
    ${card('dv-a', '🍕 Rotas que mais levam passageiros', 'Participação de cada rota no total', cv('dv-rotas'))}
    ${DV.embNome
      ? card('dv-a', '🥇 Viagens que mais venderam — ' + esc(DV.embNome), 'Saídas com mais passageiros no período', tabV)
      : card('dv-a', '🚤 Lanchas que mais viajam', 'Viagens e passageiros por lancha', cv('dv-lanchas'))}
    ${card('dv-a', '🧑‍🤝‍🧑 Passageiros que mais viajam', 'Número de viagens no período', rank)}
  </div>
  <div class="dv-grid">
    ${card('dv-b', '📈 Mês a mês', 'Passageiros e viagens em cada mês', cv('dv-meses'))}
    ${card('dv-b', '📍 Cidades que mais recebem passageiros', 'Pelo destino da passagem', cv('dv-destinos'))}
    ${card('dv-b', '📅 Passageiros por dia da semana', DV.embNome ? 'Em que dia ' + esc(DV.embNome) + ' mais leva gente' : 'Em que dia mais se embarca gente', cv('dv-dias'))}
  </div>`;

  if (!window.Chart) return;
  const CORES = ['#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#94a3b8'];
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.font.size = 11;
  Chart.defaults.locale = 'pt-BR';
  Chart.defaults.color = '#64748b';
  const grade = {color: '#f1f5f9'}, semGrade = {display: false};
  const el = id => document.getElementById(id);
  const total = DV.graf.rotas.valores.reduce((a, b) => a + b, 0) || 1;
  const leg = {position: 'top', align: 'end', labels: {usePointStyle: true, pointStyle: 'circle', boxHeight: 6, padding: 8}};

  new Chart(el('dv-rotas'), {type: 'pie',
    data: {labels: DV.graf.rotas.labels, datasets: [{data: DV.graf.rotas.valores, backgroundColor: CORES, borderColor: '#fff', borderWidth: 2, hoverOffset: 8}]},
    options: {maintainAspectRatio: false, layout: {padding: 4},
      plugins: {legend: {position: 'right', labels: {usePointStyle: true, pointStyle: 'circle', boxHeight: 7, padding: 7, font: {weight: '600', size: 11},
        generateLabels: c => c.data.labels.map((l, i) => ({text: l + '  ' + Math.round(c.data.datasets[0].data[i] / total * 100) + '%', fillStyle: CORES[i % CORES.length], strokeStyle: '#fff', pointStyle: 'circle', index: i, hidden: !c.getDataVisibility(i)}))}},
        tooltip: {callbacks: {label: c => ' ' + num(c.parsed) + ' passageiros (' + Math.round(c.parsed / total * 100) + '%)'}}}}});

  if (el('dv-lanchas')) new Chart(el('dv-lanchas'), {type: 'bar',
    data: {labels: DV.graf.lanchas.labels, datasets: [
      {label: 'Viagens', data: DV.graf.lanchas.viagens, backgroundColor: '#4f46e5', borderRadius: 5, yAxisID: 'y', maxBarThickness: 20},
      {label: 'Passageiros', data: DV.graf.lanchas.pax, backgroundColor: '#06b6d4', borderRadius: 5, yAxisID: 'y1', maxBarThickness: 20}]},
    options: {maintainAspectRatio: false, plugins: {legend: leg},
      scales: {x: {grid: semGrade, ticks: {font: {weight: '600', size: 10}}}, y: {beginAtZero: true, grid: grade, ticks: {precision: 0}}, y1: {beginAtZero: true, position: 'right', grid: semGrade, ticks: {precision: 0}}}}});

  const ctxM = el('dv-meses').getContext('2d'); const g = ctxM.createLinearGradient(0, 0, 0, 200); g.addColorStop(0, 'rgba(79,70,229,.30)'); g.addColorStop(1, 'rgba(79,70,229,0)');
  new Chart(ctxM, {data: {labels: DV.graf.meses.labels, datasets: [
      {type: 'line', label: 'Passageiros', data: DV.graf.meses.pax, borderColor: '#4f46e5', backgroundColor: g, fill: true, tension: .35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderWidth: 2, yAxisID: 'y'},
      {type: 'bar', label: 'Viagens', data: DV.graf.meses.viagens, backgroundColor: 'rgba(6,182,212,.55)', borderRadius: 5, maxBarThickness: 22, yAxisID: 'y1'}]},
    options: {maintainAspectRatio: false, plugins: {legend: leg},
      scales: {x: {grid: semGrade}, y: {beginAtZero: true, grid: grade, ticks: {precision: 0}}, y1: {beginAtZero: true, position: 'right', grid: semGrade, ticks: {precision: 0}}}}});

  new Chart(el('dv-destinos'), {type: 'bar',
    data: {labels: DV.graf.destinos.labels.slice(0, 6), datasets: [{label: 'Passageiros', data: DV.graf.destinos.valores.slice(0, 6), backgroundColor: DV.graf.destinos.labels.slice(0, 6).map((_, i) => i === 0 ? '#10b981' : '#a7f3d0'), borderRadius: 5, maxBarThickness: 18}]},
    options: {indexAxis: 'y', maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true, grid: grade, ticks: {precision: 0}}, y: {grid: semGrade, ticks: {font: {weight: '600'}}}}}});

  const mxD = Math.max(...DV.graf.dias.valores);
  new Chart(el('dv-dias'), {type: 'bar',
    data: {labels: DV.graf.dias.labels, datasets: [{label: 'Passageiros', data: DV.graf.dias.valores, backgroundColor: DV.graf.dias.valores.map(v => v === mxD ? '#f59e0b' : '#fde68a'), borderRadius: 6, maxBarThickness: 30}]},
    options: {maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {grid: semGrade, ticks: {font: {weight: '600'}}}, y: {beginAtZero: true, grid: grade, ticks: {precision: 0}}}}});
})();
</script>
@endsection
