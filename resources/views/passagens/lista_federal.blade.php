<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista de Passageiros (Passenger List)</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;color:#111;font-size:12px}
  .acoes{margin-bottom:12px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  .cab{text-align:center;margin-bottom:10px}
  .cab h1{font-size:15px;margin:0 0 4px}
  .cab .l{font-size:11px;color:#222}
  table{width:100%;border-collapse:collapse;margin-top:4px}
  th,td{text-align:left;padding:5px 6px;border:1px solid #bbb;vertical-align:top;font-size:11px}
  th{background:#f2f2f2;font-size:10px}
  th .en{display:block;font-weight:normal;font-style:italic;color:#555;font-size:9px}
  td.c,th.c{text-align:center}
  .addr{font-size:9px;color:#666;margin-top:2px}
  .tot{margin-top:12px;border:1px solid #bbb;padding:8px 10px;font-weight:bold;font-size:12px}
  .sec{margin-top:22px}
  .sec .tit{text-align:center;font-weight:bold;font-size:13px;margin-bottom:8px}
  .nota{border:1px solid #bbb;padding:7px 10px;font-weight:bold;font-size:11px;line-height:1.4}
  .nota .en2{display:block;font-weight:normal;font-style:italic;color:#555;font-size:10px}
  .decl{text-align:center;margin:34px auto 0;max-width:780px}
  .decl .pt{font-weight:bold;font-size:13px;margin:0 0 6px;line-height:1.45}
  .decl .en3{font-weight:bold;font-style:italic;color:#333;font-size:10.5px;margin:0}
  .assi{text-align:center;margin-top:46px}
  .assi .data{font-weight:bold;font-size:13px}
  .assi .box{margin-top:60px}
  .assi .box .n{font-weight:bold;font-size:13px}
  .assi .box .e{font-weight:bold;font-style:italic;color:#333;font-size:10px}
  @media print{ .acoes{display:none} body{padding:0 6px} .sec,.decl,.assi{page-break-inside:avoid} thead{display:table-header-group} }
</style>
</head>
@php $ord = $linhas->sortBy('nome')->values(); @endphp
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
  </div>

  <div class="cab">
    <h1>Lista de Passageiros <i style="font-weight:normal">(Passenger List)</i></h1>
    <div class="l">Nº de Passageiros <i>(Number of Passenger)</i>: <b>{{ $ord->count() }}</b> · <b>{{ strtoupper($cab['embarcacao']) }}</b> · Data viagem: <b>{{ $cab['data'] ? $cab['data']->format('d/m/Y') : ($cab['periodo'] ?: '—') }}</b> · Destino: <b>{{ $cab['destino'] }}</b></div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="c" style="width:34px">Nr.</th>
        <th>Nome completo <span class="en">(Full name)</span></th>
        <th style="width:120px">Data de nascimento <span class="en">(Date of birth)</span></th>
        <th class="c" style="width:90px">Sexo (M / F) <span class="en">(Gender)</span></th>
      </tr>
    </thead>
    <tbody>
      @forelse($ord as $i => $l)
      <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td>
          <b>{{ strtoupper($l->nome ?: '—') }}</b>
          <div class="addr">@if($l->telefone)Tel.: {{ $l->telefone }}@else , - - - CEP.: Tel.:@endif</div>
        </td>
        <td>{{ $l->nascimento ? $l->nascimento->format('d/m/Y') : '—' }}</td>
        <td class="c">{{ $l->sexo ?: '—' }}</td>
      </tr>
      @empty
      <tr><td colspan="4" class="c" style="padding:22px;color:#777">Nenhum passageiro no filtro selecionado.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="tot">Total Geral de Passageiros <i style="font-weight:normal">(Total number of passengers)</i>: {{ $ord->count() }}</div>

  <div class="sec">
    <div class="tit">Comboios <i style="font-weight:normal">(Convoys)</i></div>
    <div class="nota">
      No caso de navegação em comboio, preencher as informações abaixo sobre as embarcações não propulsadas componentes do comboio.
      <span class="en2">(If sailing in convoy, complete the information below about the vessels without propulsion integrating the convoy)</span>
    </div>
    <table style="margin-top:10px">
      <thead>
        <tr>
          <th class="c">Nome da Embarcação <span class="en">(Vessel's name):</span></th>
          <th class="c">Nº de Inscrição <span class="en">(Official number):</span></th>
          <th class="c">Arqueação Bruta <span class="en">(Gross Tonnage):</span></th>
          <th class="c">Carga <span class="en">(Cargo):</span></th>
          <th class="c">Quantidade e Unidade de Carga <span class="en">(Quantity and unity of cargo):</span></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="c">&nbsp;</td>
          <td class="c">&nbsp;</td>
          <td class="c">&nbsp;</td>
          <td class="c">&nbsp;</td>
          <td class="c">&nbsp;</td>
        </tr>
      </tbody>
    </table>
    <div class="nota" style="margin-top:10px">
      Somatório da Arqueação Bruta das embarcações que compõem o comboio, incluindo a embarcação propulsora:
      <span class="en2">(Sum total of Gross Tonnage of vessels that constitute the convoy, including the main vessel)</span>
    </div>
  </div>

  <div class="decl">
    <p class="pt">Estou ciente de que responderei administrativa, civil e penalmente pelos eventuais dados incorretos constantes da presente declaração.</p>
    <p class="en3">(I am aware that I will be held liable before Administrative Law, Civil Law, and Criminal Law for any eventual incorrect data stated herein.)</p>
  </div>

  <div class="assi">
    <div class="data">_______________________ em _____ de ________________ de ______</div>
    <div class="box">
      <div class="n">Assinatura do Representante da Embarcação</div>
      <div class="e">(Signature of Vessel's Representative)</div>
    </div>
  </div>

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
