@extends('layouts.app')
@section('titulo','Check-in')
@push('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
@endpush
@section('conteudo')
<div class="max-w-md mx-auto space-y-4">

  <div class="flex items-center justify-between">
    <h1 class="text-xl font-bold">Check-in / embarque</h1>
    <a href="{{ route('passagens.index') }}" class="btn btn-ghost btn-sm">Passagens</a>
  </div>

  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
  @endif

  {{-- Scanner --}}
  <div class="card p-4 space-y-3">
    <div class="text-sm font-semibold text-slate-600">1. Escaneie o QR do bilhete</div>
    <div id="reader" class="rounded-xl overflow-hidden bg-slate-900/5 min-h-[220px] flex items-center justify-center text-slate-400 text-sm">
      Toque em “Ativar câmera” para começar
    </div>
    <div id="status" class="text-xs text-slate-500 text-center"></div>
    <div class="flex gap-2">
      <button id="btnCam" type="button" class="btn btn-primary flex-1" style="background:var(--cor1)" onclick="iniciarCam()">📷 Ativar câmera</button>
      <button id="btnStop" type="button" class="btn btn-ghost flex-1 hidden" onclick="pararCam()">Parar</button>
    </div>
    <p class="text-[11px] text-slate-400 text-center">O QR está no comprovante da passagem. Ao ler, abre o check-in pra confirmar o embarque.</p>
  </div>

  {{-- Busca manual --}}
  <div class="card p-4 space-y-2">
    <div class="text-sm font-semibold text-slate-600">2. Ou abra pelo número</div>
    <form method="get" action="{{ route('passagens.scanner.buscar') }}" class="flex gap-2">
      <input name="numero" class="input flex-1" placeholder="Nº da passagem (ex.: {{ auth()->user()->prefixo ?: 'PS' }}-0001)" autocomplete="off" autofocus>
      <button class="btn btn-accent">Abrir</button>
    </form>
  </div>
</div>

<script>
  var BUSCA = @json(route('passagens.scanner.buscar'));
  var html5 = null, lido = false;
  function status(t){ document.getElementById('status').textContent = t || ''; }
  function iniciarCam(){
    if(typeof Html5Qrcode === 'undefined'){ status('Leitor não carregou. Use a busca pelo número abaixo.'); return; }
    var btn = document.getElementById('btnCam'); btn.disabled = true; status('Abrindo câmera…');
    document.getElementById('reader').textContent = '';
    html5 = new Html5Qrcode("reader");
    html5.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, onScan, function(){})
      .then(function(){ status('Aponte para o QR do bilhete'); btn.classList.add('hidden'); document.getElementById('btnStop').classList.remove('hidden'); })
      .catch(function(e){ status('Não consegui abrir a câmera. Use a busca pelo número.'); btn.disabled = false; });
  }
  function pararCam(){ if(html5){ html5.stop().then(function(){ html5.clear(); }).catch(function(){}); } document.getElementById('btnStop').classList.add('hidden'); document.getElementById('btnCam').classList.remove('hidden'); document.getElementById('btnCam').disabled=false; status(''); }
  function onScan(text){
    if(lido) return; lido = true;
    pararCam();
    try{
      var u = new URL(text, location.origin);
      if(u.pathname.indexOf('/checkin/') > -1){ status('Abrindo check-in…'); location.href = u.href; return; }
    }catch(e){}
    status('Abrindo…'); location.href = BUSCA + '?numero=' + encodeURIComponent(text);
  }
</script>
@endsection
