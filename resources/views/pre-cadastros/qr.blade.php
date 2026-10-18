@extends('layouts.app')
@section('titulo','QR e cartaz')
@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  @media print {
    body { background:#fff !important; }
    .no-print, header, nav, aside { display:none !important; }
    .cartaz { box-shadow:none !important; border:none !important; margin:0 !important; width:100% !important; }
    @page { margin: 8mm; }
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
        <p class="text-xs text-slate-500">Imprima o cartaz e cole na parede. O cliente escaneia, preenche e aparece na sua fila.</p>
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

  {{-- cartaz (é o que imprime) --}}
  <div class="cartaz mx-auto bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" style="max-width:520px">
    <div class="p-6 text-white text-center" style="background:linear-gradient(140deg,{{ $cor1 }},color-mix(in srgb,{{ $cor1 }} 60%,{{ $cor2 }}))">
      @if($empresa->logo)<img src="/{{ $empresa->logo }}" class="h-16 w-16 rounded-xl object-cover bg-white/90 p-1 mx-auto mb-2">@endif
      <div class="text-xl font-extrabold leading-tight">{{ $empresa->nomeExibicao() }}</div>
      <div class="text-sm opacity-90 mt-1">Adiante seu atendimento</div>
    </div>
    <div class="p-6 text-center">
      <div class="text-2xl font-black" style="color:{{ $cor1 }}">PRÉ-CADASTRE SUA ENCOMENDA</div>
      <p class="text-slate-500 text-sm mt-1">Aponte a câmera do celular para o QR abaixo</p>
      <div id="qr" class="inline-block p-3 bg-white rounded-xl border border-slate-200 my-4"></div>
      <div class="text-left max-w-xs mx-auto space-y-2 text-sm">
        <div class="flex gap-2"><span class="font-black" style="color:{{ $cor2 }}">1</span> Escaneie e preencha origem, destino e o que vai enviar.</div>
        <div class="flex gap-2"><span class="font-black" style="color:{{ $cor2 }}">2</span> Receba um <b>código</b> na tela.</div>
        <div class="flex gap-2"><span class="font-black" style="color:{{ $cor2 }}">3</span> No balcão, mostre o <b>código</b> ou diga seu <b>nome</b>. Pronto!</div>
      </div>
      <div class="mt-4 text-xs text-slate-400 break-all">{{ $url }}</div>
    </div>
  </div>
</div>

<script>
  var qrcode = new QRCode(document.getElementById("qr"), {
    text: @json($url),
    width: 240, height: 240,
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
