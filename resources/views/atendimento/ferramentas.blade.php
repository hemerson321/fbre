@extends('layouts.app')
@section('titulo','Ferramentas do atendimento')
@section('conteudo')
@php
  $stCor = ['pendente' => ['#fef3c7', '#92400e', 'Pendente'], 'enviando' => ['#dbeafe', '#1e40af', 'Enviando'], 'enviada' => ['#dcfce7', '#166534', 'Enviada'], 'erro' => ['#fee2e2', '#991b1b', 'Erro'], 'cancelada' => ['#f1f5f9', '#64748b', 'Cancelada']];
  $lotes = $agendadas->whereNotNull('lote')->groupBy('lote');
@endphp
<div x-data="ferramentasAtd()" class="max-w-5xl mx-auto space-y-4">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-extrabold">🧰 Ferramentas do atendimento</h1>
      <p class="text-sm text-slate-500">Respostas prontas, funil de mensagens, etiquetas, agendamentos{{ $adm ? ', envio em massa e status' : '' }}.</p>
    </div>
    <div class="flex gap-2"><a href="{{ route('atendimento.index') }}" class="btn btn-sm">💬 Conversas</a><a href="{{ route('atendimento.kanban') }}" class="btn btn-sm">📋 CRM Kanban</a></div>
  </div>

  <div class="flex gap-1.5 flex-wrap text-sm">
    <button type="button" @click="aba='respostas'" :class="aba==='respostas' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" class="rounded-full px-3.5 py-1.5 font-bold">⚡ Respostas e funis</button>
    <button type="button" @click="aba='etiquetas'" :class="aba==='etiquetas' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" class="rounded-full px-3.5 py-1.5 font-bold">🏷️ Etiquetas</button>
    <button type="button" @click="aba='agendadas'" :class="aba==='agendadas' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" class="rounded-full px-3.5 py-1.5 font-bold">🕒 Agendadas @if($pendentes)<span class="ml-1 rounded-full bg-amber-400 text-white text-[10px] px-1.5">{{ $pendentes }}</span>@endif</button>
    @if($adm)
    <button type="button" @click="aba='massa'" :class="aba==='massa' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" class="rounded-full px-3.5 py-1.5 font-bold">📣 Envio em massa</button>
    <button type="button" @click="aba='status'" :class="aba==='status' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" class="rounded-full px-3.5 py-1.5 font-bold">⭕ Status</button>
    @endif
  </div>

  {{-- ===== respostas rápidas / funil ===== --}}
  <div x-show="aba==='respostas'" class="space-y-3">
    <div class="card p-4">
      <div class="flex items-center justify-between gap-2 mb-3">
        <div class="font-bold" x-text="rz.id ? 'Editar' : 'Nova resposta rápida ou funil'"></div>
        <button type="button" class="btn btn-sm" x-show="rz.id" @click="novaResposta()">＋ Nova</button>
      </div>
      <form method="post" :action="rz.action" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <div><label class="label">Nome (aparece no botão ⚡ do atendimento)</label><input name="titulo" x-model="rz.titulo" required maxlength="120" class="input" placeholder="Ex.: Boas-vindas, Tabela de preços, Dados do PIX"></div>
        <template x-for="(p,i) in rz.passos" :key="p.k">
          <div class="rounded-xl border p-3 bg-slate-50 space-y-2">
            <div class="flex items-center justify-between">
              <div class="text-xs font-bold text-slate-500" x-text="rz.passos.length>1 ? ('Mensagem '+(i+1)) : 'Mensagem'"></div>
              <button type="button" class="text-xs text-rose-600 font-semibold" x-show="rz.passos.length>1" @click="rz.passos.splice(i,1)">remover</button>
            </div>
            <textarea :name="'passos['+i+'][texto]'" x-model="p.texto" rows="3" maxlength="4000" class="input !min-h-[80px] py-2" placeholder="Texto da mensagem (ou legenda do arquivo)"></textarea>
            <div class="grid sm:grid-cols-2 gap-2">
              <div>
                <label class="label">Arquivo (foto, vídeo, áudio, PDF) — opcional</label>
                <input type="file" :name="'passos['+i+'][arquivo]'" class="input py-2">
                <div class="text-[11px] text-slate-500 mt-1" x-show="p.arquivo">Atual: <b x-text="p.nome || 'arquivo'"></b> <button type="button" class="text-rose-600 underline" @click="p.arquivo='';p.nome='';p.mime=''">tirar</button></div>
                <input type="hidden" :name="'passos['+i+'][arquivo_atual]'" :value="p.arquivo||''">
                <input type="hidden" :name="'passos['+i+'][mime_atual]'" :value="p.mime||''">
                <input type="hidden" :name="'passos['+i+'][nome_atual]'" :value="p.nome||''">
              </div>
              <div x-show="i>0">
                <label class="label">Esperar antes de enviar (segundos)</label>
                <input type="number" min="0" max="3600" :name="'passos['+i+'][espera]'" x-model="p.espera" class="input">
              </div>
            </div>
          </div>
        </template>
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <button type="button" class="btn btn-sm" @click="rz.passos.push({k:Date.now(), texto:'', espera:5, arquivo:'', nome:'', mime:''})" x-show="rz.passos.length<12">＋ Adicionar mensagem (vira funil)</button>
          <button class="btn btn-primary">Salvar</button>
        </div>
        <p class="text-[11px] text-slate-400">Com 1 mensagem é uma resposta rápida. Com 2 ou mais vira um <b>funil</b>: um clique no atendimento e todas saem em sequência, respeitando a espera de cada uma.</p>
      </form>
    </div>
    <div class="card divide-y">
      @forelse($respostas as $r)
      @php $ps = (array) $r->passos; @endphp
      <div class="p-3 flex items-center gap-3">
        <div class="min-w-0 flex-1">
          <div class="font-semibold text-sm">{{ count($ps) > 1 ? '🔀' : '⚡' }} {{ $r->titulo }} @if(count($ps) > 1)<span class="chip text-[10px] bg-violet-100 text-violet-700 ml-1">funil · {{ count($ps) }} msgs</span>@endif</div>
          <div class="text-xs text-slate-500 truncate">{{ \Illuminate\Support\Str::limit((string) ($ps[0]['texto'] ?? ''), 110) ?: '📎 '.($ps[0]['nome'] ?? 'arquivo') }}</div>
        </div>
        <button type="button" class="btn btn-sm" @click="editarResposta(@js(['id' => $r->id, 'titulo' => $r->titulo, 'passos' => $ps]))">✏️</button>
        <form method="post" action="{{ route('atendimento.resposta.excluir', $r) }}" onsubmit="return confirm('Excluir?')">@csrf @method('DELETE')<button class="btn btn-sm" style="color:#b91c1c">🗑</button></form>
      </div>
      @empty
      <div class="p-6 text-center text-slate-400 text-sm">Nenhuma resposta cadastrada ainda.</div>
      @endforelse
    </div>
  </div>

  {{-- ===== etiquetas ===== --}}
  <div x-show="aba==='etiquetas'" x-cloak class="space-y-3">
    <form method="post" :action="et.action" class="card p-4 flex items-end gap-2 flex-wrap">
      @csrf
      <div class="flex-1 min-w-[200px]"><label class="label" x-text="et.id ? 'Editar etiqueta' : 'Nova etiqueta'"></label><input name="nome" x-model="et.nome" required maxlength="60" class="input" placeholder="Ex.: VIP, Voltar a ligar, Grupo"></div>
      <div><label class="label">Cor</label><input type="color" name="cor" x-model="et.cor" class="h-[46px] w-16 rounded-lg border border-slate-200 p-1 bg-white"></div>
      <button class="btn btn-primary">Salvar</button>
      <button type="button" class="btn" x-show="et.id" @click="et={id:null,nome:'',cor:'#6366f1',action:ET_BASE}">Cancelar</button>
    </form>
    <div class="card p-3 flex flex-wrap gap-2">
      @forelse($etiquetas as $e)
      <div class="flex items-center gap-1 rounded-full pl-3 pr-1 py-1 text-sm font-bold" style="background:color-mix(in srgb, {{ $e->cor }} 14%, white);color:{{ $e->cor }}">
        {{ $e->nome }}
        <button type="button" class="rounded-full w-7 h-7 hover:bg-white/70" title="Editar" @click="et={id:{{ $e->id }},nome:@js($e->nome),cor:@js($e->cor),action:ET_BASE+'/'+{{ $e->id }}}">✏️</button>
        <form method="post" action="{{ route('atendimento.etiqueta.excluir', $e) }}" onsubmit="return confirm('Excluir a etiqueta? Ela sai de todas as conversas.')">@csrf @method('DELETE')<button class="rounded-full w-7 h-7 hover:bg-white/70" title="Excluir">🗑</button></form>
      </div>
      @empty
      <div class="p-4 text-slate-400 text-sm">Nenhuma etiqueta. Crie as suas para marcar os clientes no atendimento e filtrar no Kanban.</div>
      @endforelse
    </div>
  </div>

  {{-- ===== agendadas ===== --}}
  <div x-show="aba==='agendadas'" x-cloak class="space-y-3">
    @foreach($lotes as $loteId => $itens)
    @php $pend = $itens->where('status', 'pendente')->count(); @endphp
    @if($pend > 0 && str_starts_with((string) $itens->first()->lote_nome, 'Massa'))
    <div class="card p-3 flex items-center justify-between gap-2 flex-wrap" style="border-left:4px solid #d97706">
      <div class="text-sm"><b>{{ $itens->first()->lote_nome }}</b> · {{ $itens->where('status', 'enviada')->count() }} enviada(s) · <b>{{ $pend }}</b> na fila · {{ $itens->where('status', 'erro')->count() }} erro(s)</div>
      <form method="post" action="{{ route('atendimento.lote.cancelar') }}" onsubmit="return confirm('Cancelar os envios que ainda não saíram?')">@csrf<input type="hidden" name="lote" value="{{ $loteId }}"><button class="btn btn-sm" style="color:#b91c1c">Cancelar o restante</button></form>
    </div>
    @endif
    @endforeach
    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="text-left text-[11px] uppercase tracking-wide text-slate-400 border-b"><th class="p-2.5">Quando</th><th class="p-2.5">Para</th><th class="p-2.5">Mensagem</th><th class="p-2.5">Situação</th><th class="p-2.5"></th></tr></thead>
        <tbody class="divide-y">
          @forelse($agendadas as $a)
          @php $sc = $stCor[$a->status] ?? $stCor['pendente']; @endphp
          <tr>
            <td class="p-2.5 whitespace-nowrap">{{ $a->enviar_em?->format('d/m/Y H:i') }}</td>
            <td class="p-2.5">{{ $a->tipo === 'status' ? '⭕ Status' : ($a->conversa ? $a->conversa->nomeExibicao() : $a->telefone) }}@if($a->lote_nome && $a->tipo !== 'status')<div class="text-[10px] text-slate-400">{{ $a->lote_nome }}</div>@endif</td>
            <td class="p-2.5 max-w-[320px]"><div class="truncate">{{ $a->arquivo ? '📎 ' : '' }}{{ $a->texto ?: $a->nome_arquivo }}</div>@if($a->erro)<div class="text-[11px] text-rose-600 truncate">{{ $a->erro }}</div>@endif</td>
            <td class="p-2.5"><span class="rounded-full px-2 py-0.5 text-[11px] font-bold" style="background:{{ $sc[0] }};color:{{ $sc[1] }}">{{ $sc[2] }}</span></td>
            <td class="p-2.5 text-right">
              @if($a->status === 'pendente')
              <form method="post" action="{{ route('atendimento.agendada.cancelar', $a) }}">@csrf<button class="btn btn-sm" style="color:#b91c1c">Cancelar</button></form>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="5" class="p-6 text-center text-slate-400">Nada agendado. Para agendar, abra uma conversa e use o botão 🕒.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($adm)
  {{-- ===== envio em massa ===== --}}
  <div x-show="aba==='massa'" x-cloak class="space-y-3">
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
      <b>⚠ Cuidado:</b> o WhatsApp pode <b>bloquear o número</b> de quem dispara muita mensagem para quem não pediu. Envie só para clientes seus, use o intervalo (mínimo 20 s) e evite passar de 200 por dia. Cada envio aceita no máximo 500 contatos.
    </div>
    <form method="post" action="{{ route('atendimento.massa') }}" enctype="multipart/form-data" class="card p-4 space-y-3">
      @csrf
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Nome do envio</label><input name="nome" maxlength="120" class="input" placeholder="Ex.: Promoção de dezembro"></div>
        <div><label class="label">Conexão do WhatsApp</label><select name="whatsapp_conexao_id" class="input"><option value="">Primeira ativa</option>@foreach($conexoes as $cx)<option value="{{ $cx->id }}">{{ $cx->nome }}</option>@endforeach</select></div>
      </div>
      <div>
        <label class="label">Para quem</label>
        <input type="hidden" name="publico" :value="pub">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <button type="button" @click="pub='etiqueta'" :class="pub==='etiqueta' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2 text-sm font-bold">🏷️ Etiqueta</button>
          <button type="button" @click="pub='funil'" :class="pub==='funil' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2 text-sm font-bold">📋 Etapa do funil</button>
          <button type="button" @click="pub='todos'" :class="pub==='todos' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2 text-sm font-bold">👥 Todos os contatos</button>
          <button type="button" @click="pub='numeros'" :class="pub==='numeros' ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2 text-sm font-bold">🔢 Lista de números</button>
        </div>
        <div class="mt-2" x-show="pub==='etiqueta'"><select name="etiqueta_id" class="input">@foreach($etiquetas as $e)<option value="{{ $e->id }}">{{ $e->nome }}</option>@endforeach</select></div>
        <div class="mt-2" x-show="pub==='funil'"><select name="funil" class="input">@foreach($funis as $fk => $fn)<option value="{{ $fk }}">{{ $fn }}</option>@endforeach</select></div>
        <div class="mt-2" x-show="pub==='numeros'"><textarea name="numeros" rows="3" class="input !min-h-[80px] py-2" placeholder="Um número por linha ou separados por vírgula, com DDD. Ex.: 92999990000"></textarea></div>
      </div>
      <div><label class="label">Mensagem — use {nome} para o primeiro nome do cliente</label><textarea name="texto" rows="4" maxlength="4000" class="input !min-h-[110px] py-2" placeholder="Olá {nome}! ..."></textarea></div>
      <div class="grid sm:grid-cols-3 gap-3">
        <div><label class="label">Arquivo (opcional)</label><input type="file" name="arquivo" class="input py-2"></div>
        <div><label class="label">Começar em (vazio = agora)</label><input type="datetime-local" name="enviar_em" class="input"></div>
        <div><label class="label">Intervalo entre envios (segundos)</label><input type="number" name="intervalo" min="20" max="600" value="40" class="input"></div>
      </div>
      <div class="flex justify-end"><button class="btn btn-primary" onclick="return confirm('Programar este envio em massa?')">📣 Programar envio</button></div>
    </form>
  </div>

  {{-- ===== status ===== --}}
  <div x-show="aba==='status'" x-cloak class="space-y-3">
    <form method="post" action="{{ route('atendimento.status') }}" enctype="multipart/form-data" class="card p-4 space-y-3">
      @csrf
      <div class="font-bold">⭕ Postar status do WhatsApp</div>
      <div><label class="label">Texto (ou legenda da imagem/vídeo)</label><textarea name="texto" rows="3" maxlength="700" class="input !min-h-[80px] py-2" placeholder="Ex.: Saída para Tefé toda sexta às 05:00. Garanta sua poltrona!"></textarea></div>
      <div class="grid sm:grid-cols-3 gap-3">
        <div><label class="label">Imagem ou vídeo (opcional)</label><input type="file" name="arquivo" accept="image/*,video/mp4" class="input py-2"></div>
        <div><label class="label">Postar em (vazio = agora)</label><input type="datetime-local" name="enviar_em" class="input"></div>
        <div><label class="label">Conexão do WhatsApp</label><select name="whatsapp_conexao_id" class="input"><option value="">Primeira ativa</option>@foreach($conexoes as $cx)<option value="{{ $cx->id }}">{{ $cx->nome }}</option>@endforeach</select></div>
      </div>
      <div class="flex justify-end"><button class="btn btn-primary">Programar status</button></div>
      <p class="text-[11px] text-slate-400">O status vai para todos os contatos da agenda do número. Só funciona em conexão Evolution. Os status programados aparecem na aba "Agendadas".</p>
    </form>
  </div>
  @endif
</div>
<script>
function ferramentasAtd(){
  var RZ_BASE = @json(url('/passagens/atendimento-ferramentas/resposta'));
  window.ET_BASE = @json(url('/passagens/atendimento-ferramentas/etiqueta'));
  return {
    aba: (location.hash || '#respostas').slice(1),
    pub: 'etiqueta',
    ET_BASE: window.ET_BASE,
    rz: { id:null, titulo:'', action: RZ_BASE, passos:[{k:1, texto:'', espera:0, arquivo:'', nome:'', mime:''}] },
    et: { id:null, nome:'', cor:'#6366f1', action: window.ET_BASE },
    novaResposta(){ this.rz = { id:null, titulo:'', action: RZ_BASE, passos:[{k:Date.now(), texto:'', espera:0, arquivo:'', nome:'', mime:''}] }; },
    editarResposta(r){
      this.rz = { id:r.id, titulo:r.titulo, action: RZ_BASE + '/' + r.id, passos:(r.passos||[]).map(function(p,i){ return {k:i+1, texto:p.texto||'', espera:p.espera||0, arquivo:p.arquivo||'', nome:p.nome||'', mime:p.mime||''}; }) };
      window.scrollTo({top:0, behavior:'smooth'});
    },
    init(){ this.$watch('aba', function(v){ try { history.replaceState(null, '', '#'+v); } catch(e){} }); if (['respostas','etiquetas','agendadas','massa','status'].indexOf(this.aba) < 0) this.aba = 'respostas'; }
  };
}
</script>
@endsection
