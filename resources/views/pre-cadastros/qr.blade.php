@extends('layouts.app')
@section('titulo','QR e cartaz')
@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  .cartaz{ --c1:{{ $empresa->cor_primaria ?? '#0b1a33' }}; --c2:{{ $empresa->cor_secundaria ?? '#00a8e8' }}; }
  .qrwrap{ position:relative; display:inline-block; padding:14px; background:#fff; border-radius:18px; }
  .qrwrap::before,.qrwrap::after,.qrwrap>span.c1,.qrwrap>span.c2{ content:""; position:absolute; width:26px; height:26px; border:4px solid var(--c2); }
  .qrwrap::before{ top:0; left:0; border-right:0; border-bottom:0; border-top-left-radius:10px; }
  .qrwrap::after{ top:0; right:0; border-left:0; border-bottom:0; border-top-right-radius:10px; }
  .qrwrap>span.c1{ bottom:0; left:0; border-right:0; border-top:0; border-bottom-left-radius:10px; }
  .qrwrap>span.c2{ bottom:0; right:0; border-left:0; border-top:0; border-bottom-right-radius:10px; }
  .passo-n{ flex:0 0 auto; width:30px; height:30px; border-radius:9999px; display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; font-size:15px; }
  @media print {
    body{ background:#fff !important; }
    .no-print, header, nav, aside{ display:none !important; }
    .cartaz{ box-shadow:none !important; border:none !important; margin:0 auto !important; }
    .cartaz, .cartaz *{ -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
    @page{ margin:10mm; }
  }
</style>
@endpush
@section('conteudo')
@php
  $cor1 = $empresa->cor_primaria ?? '#0b1a33';
  $cor2 = $empresa->cor_secundaria ?? '#00a8e8';
@endphp
<div class="max-w-4xl mx-auto space-y-4">

  {{-- controles --}}
  <div class="card p-4 no-print space-y-3">
    <div class="flex items-start justify-between gap-2 flex-wrap">
      <div>
        <h1 class="text-xl font-bold">QR e cartaz do pré-cadastro</h1>
        <p class="text-xs text-slate-500">Imprima o cartaz e cole na parede. O cliente escaneia, preenche e já cai na sua fila.</p>
      </div>
      <a href="{{ route('pre.index') }}" class="btn btn-ghost btn-sm">← Fila</a>
    </div>
    <div>
      <label class="label">Link do pré-cadastro (o QR aponta pra cá)</label>
      <div class="flex gap-2">
        <input id="pre-url" class="input font-mono text-sm" value="{{ $url }}" readonly onclick="this.select()">
        <button class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('pre-url').value)">copiar</button>
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      <button class="btn btn-accent btn-sm" onclick="baixarQR()">⬇ Baixar QR (PNG)</button>
      <button class="btn btn-primary btn-sm" onclick="window.print()">🖨 Imprimir / salvar cartaz (PDF)</button>
      <a href="{{ route('pre.form', $empresa->slug) }}" target="_blank" class="btn btn-ghost btn-sm">👁 Ver formulário do cliente</a>
    </div>
    <p class="text-[11px] text-slate-400">Dica: pra mandar pra gráfica, clique em “Imprimir” e escolha <b>Salvar como PDF</b>, ou baixe o PNG do QR e use na arte de vocês.</p>
  </div>

  {{-- ===== CARTAZ (é o que imprime) ===== --}}
  <div class="cartaz mx-auto bg-white rounded-[28px] shadow-lg border border-slate-100 overflow-hidden" style="max-width:540px">

    {{-- topo --}}
    <div class="relative px-8 pt-8 pb-7 text-white text-center" style="background:linear-gradient(145deg,{{ $cor1 }},color-mix(in srgb,{{ $cor1 }} 55%,{{ $cor2 }}))">
      @if($empresa->logo)
        <img src="/{{ $empresa->logo }}" class="h-20 w-20 rounded-2xl object-contain bg-white p-1.5 mx-auto mb-3 shadow-md">
      @endif
      <div class="text-2xl font-extrabold leading-tight">{{ $empresa->nomeExibicao() }}</div>
      <div class="inline-flex items-center gap-1.5 mt-2 text-[12px] font-semibold uppercase tracking-wider px-3 py-1 rounded-full bg-white/15">
        📦 Encomendas · adiante seu atendimento
      </div>
    </div>

    {{-- faixa de chamada --}}
    <div class="px-8 pt-7 text-center">
      <div class="text-[26px] font-black leading-tight" style="color:{{ $cor1 }}">PRÉ-CADASTRE<br>SUA ENCOMENDA</div>
      <p class="inline-flex items-center gap-1.5 text-slate-500 text-sm mt-2 font-medium">
        <span style="color:{{ $cor2 }}">📷</span> Aponte a câmera do celular para o QR
      </p>
    </div>

    {{-- QR com moldura --}}
    <div class="flex justify-center my-6">
      <div class="qrwrap shadow-sm">
        <span class="c1"></span><span class="c2"></span>
        <div id="qr"></div>
      </div>
    </div>

    {{-- passos --}}
    <div class="px-8 pb-2 space-y-3 max-w-sm mx-auto">
      <div class="flex items-center gap-3">
        <span class="passo-n" style="background:{{ $cor2 }}">1</span>
        <span class="text-[15px] text-slate-700 leading-snug">Escaneie e preencha <b>origem</b>, <b>destino</b> e o que vai enviar.</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="passo-n" style="background:{{ $cor2 }}">2</span>
        <span class="text-[15px] text-slate-700 leading-snug">Receba um <b>código</b> direto na tela do celular.</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="passo-n" style="background:{{ $cor2 }}">3</span>
        <span class="text-[15px] text-slate-700 leading-snug">No balcão, mostre o <b>código</b> ou diga seu <b>nome</b>. Pronto!</span>
      </div>
    </div>

    {{-- benefícios --}}
    <div class="flex justify-center gap-5 mt-5 px-8 text-center text-[12px] font-semibold" style="color:{{ $cor1 }}">
      <div>⚡<br><span class="opacity-80">Sem fila</span></div>
      <div>🕒<br><span class="opacity-80">Mais rápido</span></div>
      <div>✅<br><span class="opacity-80">Código na hora</span></div>
    </div>

    {{-- rodapé com link --}}
    <div class="mt-6 px-8 py-4 text-center" style="background:color-mix(in srgb,{{ $cor1 }} 6%,#fff)">
      <div class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold">ou acesse pelo link</div>
      <div class="text-sm font-bold break-all" style="color:{{ $cor1 }}">{{ preg_replace('#^https?://#','',$url) }}</div>
    </div>
  </div>
</div>

<script>
  var qrcode = new QRCode(document.getElementById("qr"), {
    text: @json($url),
    width: 230, height: 230,
    colorDark: @json($cor1), colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.M
  });
  function baixarQR(){
    var cv = document.querySelector('#qr canvas');
    var url = cv ? cv.toDataURL('image/png') : document.querySelector('#qr img').src;
    var a = document.createElement('a');
    a.href = url; a.download = 'qr-precadastro-{{ $empresa->slug }}.png'; a.click();
  }
</script>
@endsection
