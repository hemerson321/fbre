{{-- Exibe anexos de documentos (imagem/PDF/outros) com ver + baixar.
     Espera: $docs (array). Opcional: $size (px da miniatura, default 48). --}}
@php $__docs = \App\Support\Docs::lista($docs ?? []); $__sz = $size ?? 48; @endphp
@if(count($__docs))
<div class="flex flex-wrap gap-2">
  @foreach($__docs as $d)
    @php $__img = \App\Support\Docs::ehImagem($d); @endphp
    <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white pl-1 pr-2 py-1 shadow-sm">
      @if($__img)
        <a href="/{{ $d['u'] }}" target="_blank" rel="noopener" title="Abrir {{ $d['n'] }}">
          <img src="/{{ $d['u'] }}" class="object-cover rounded-lg border border-slate-100" style="height:{{ $__sz }}px;width:{{ $__sz }}px">
        </a>
      @else
        <a href="/{{ $d['u'] }}" target="_blank" rel="noopener" title="Abrir {{ $d['n'] }}"
           class="rounded-lg bg-slate-100 flex items-center justify-center text-xl" style="height:{{ $__sz }}px;width:{{ $__sz }}px">{{ \App\Support\Docs::icone($d) }}</a>
      @endif
      <div class="min-w-0 leading-tight">
        <div class="text-[11px] font-semibold text-slate-700 truncate" style="max-width:110px" title="{{ $d['n'] }}">{{ $d['n'] }}</div>
        <div class="flex gap-2 text-[11px]">
          <a href="/{{ $d['u'] }}" target="_blank" rel="noopener" class="font-semibold text-sky-600 hover:underline">ver</a>
          <a href="/{{ $d['u'] }}" download="{{ $d['n'] }}" class="font-semibold text-slate-500 hover:underline">baixar</a>
        </div>
      </div>
    </div>
  @endforeach
</div>
@endif
