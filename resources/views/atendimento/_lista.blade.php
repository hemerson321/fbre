@php $__etq = \App\Models\AtendEtiqueta::get()->keyBy('id'); @endphp
@forelse($conversas as $c)

        <a href="{{ route('atendimento.show',$c) }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 {{ $sel && $sel->id===$c->id ? 'bg-slate-100' : '' }}">
          <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500 shrink-0">{{ mb_strtoupper(mb_substr($c->nomeExibicao(),0,1)) }}</div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold truncate">{{ $c->nomeExibicao() }}</span>
              <span class="text-[10px] text-slate-400 shrink-0">{{ optional($c->ultima_msg_em)->format('d/m H:i') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="text-xs text-slate-500 truncate flex-1">{{ $c->ultima_msg_texto }}</span>
              @if($c->nao_lidas)<span class="min-w-[18px] text-center rounded-full bg-emerald-500 text-white text-[10px] font-bold px-1">{{ $c->nao_lidas }}</span>@endif
            </div>
            <span class="chip text-[10px] bg-{{ $c->cor() }}-100 text-{{ $c->cor() }}-700 mt-0.5">{{ $c->statusLabel() }}</span>
            @if($c->funilLabel())<span class="chip text-[10px] bg-indigo-100 text-indigo-700 mt-0.5 ml-1">{{ $c->funilLabel() }}</span>@endif
            @if($c->precisa_humano)<span class="chip text-[10px] bg-rose-100 text-rose-700 mt-0.5 ml-1 font-bold">🔔 precisa atendente{{ $c->transferida_motivo ? ' · '.$c->transferida_motivo : '' }}</span>@endif
            @if($c->atendente)<span class="chip text-[10px] bg-emerald-100 text-emerald-700 mt-0.5 ml-1">👤 {{ $c->atendente->name }}</span>@endif
            @foreach((array) $c->etiquetas as $eid)
              @if(isset($__etq[$eid]))
                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold mt-0.5 ml-1" style="background:color-mix(in srgb, {{ $__etq[$eid]->cor }} 15%, white);color:{{ $__etq[$eid]->cor }}">{{ $__etq[$eid]->nome }}</span>
              @endif
            @endforeach
            @if(!empty($c->contexto['pref_poltrona']))<span class="chip text-[10px] bg-sky-100 text-sky-700 mt-0.5 ml-1">🪑 {{ ucfirst($c->contexto['pref_poltrona']) }}{{ !empty($c->contexto['poltronas_agente']) ? ' · '.implode(', ', (array) $c->contexto['poltronas_agente']) : '' }}</span>@endif
          </div>
        </a>
      @empty
        <div class="p-8 text-center text-slate-400 text-sm">Nenhuma conversa.</div>
      @endforelse