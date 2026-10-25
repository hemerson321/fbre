@extends('layouts.app')
@section('titulo','Agentes IA')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-5">
  <div>
    <h1 class="text-xl font-bold">Agentes IA</h1>
    <p class="text-sm text-slate-500">Crie quantos agentes quiser. Cada um usa um provedor de IA, uma conexão de WhatsApp e uma conta de pagamento, e atende um módulo. O agente responde no WhatsApp (próxima etapa liga o atendimento automático).</p>
  </div>

  @foreach($agentes as $a)
  <div class="card p-4 space-y-2" x-data="{ab:false}">
    <div class="flex items-center justify-between">
      <div class="font-bold cursor-pointer" @click="ab=!ab">🤖 {{ $a->nome }} <span class="text-xs font-normal text-slate-400">· {{ \App\Support\Modulos::rotulo($a->modulo) }} · {{ $a->llm?->nome ?? 'sem IA' }} @if(!$a->ativo)· inativo @endif</span></div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.agentes.demo', $a) }}" class="btn btn-sm btn-ghost">🧪 Demo</a>
        <span class="text-slate-400 cursor-pointer" @click="ab=!ab" x-text="ab?'▲':'▼'"></span>
      </div>
    </div>
    <div x-show="ab" x-cloak class="pt-2 border-t">
      @include('admin._agente_form', ['a'=>$a])
    </div>
  </div>
  @endforeach

  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">➕ Novo agente</div>
    @include('admin._agente_form', ['a'=>null])
  </div>

  @if($llms->isEmpty())<p class="text-xs text-amber-600">Dica: cadastre primeiro um <a href="{{ route('admin.llm') }}" class="underline">Provedor de IA</a> pro agente poder pensar.</p>@endif
</div>
@endsection
