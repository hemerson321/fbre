@php $a = $a ?? null; $r = $a->regras ?? []; $h = $a->horario ?? []; $dias = $h['dias'] ?? [1,2,3,4,5,6,7]; @endphp
<form method="post" action="{{ $a ? route('admin.agentes.salvar',$a) : route('admin.agentes.salvar') }}" class="space-y-3"
      x-data="{ modo:'{{ $a->assumir_modo ?? 'apos_min' }}', hr:'{{ $h['modo'] ?? '24h' }}' }">@csrf
  <div class="grid sm:grid-cols-2 gap-3">
    <div><label class="label">Nome do agente</label><input name="nome" class="input" value="{{ $a->nome ?? '' }}" required maxlength="80" placeholder="Ex.: Vendedora Rainha Esther"></div>
    <div><label class="label">Atende o módulo</label><select name="modulo" class="input">@foreach($modulos as $mk=>$md)<option value="{{ $mk }}" @selected(($a->modulo ?? 'passagem')==$mk)>{{ $md[1] }} {{ $md[0] }}</option>@endforeach</select></div>
    <div><label class="label">Provedor de IA</label><select name="llm_provedor_id" class="input"><option value="">—</option>@foreach($llms as $l)<option value="{{ $l->id }}" @selected(($a->llm_provedor_id ?? null)==$l->id)>{{ $l->nome }}</option>@endforeach</select></div>
    <div><label class="label">Modelo de escalonamento (opcional)</label><select name="llm_escalonar_id" class="input"><option value="">— nenhum —</option>@foreach($llms as $l)<option value="{{ $l->id }}" @selected(($a->llm_escalonar_id ?? null)==$l->id)>{{ $l->nome }}</option>@endforeach</select><p class="text-[11px] text-slate-400 mt-0.5">Principal barato (ex.: Gemini) atende; se complicar ([[ESCALAR]]) ou falhar, cai neste (ex.: Claude Sonnet). Imagem/foto sempre usa este.</p></div>
    <div><label class="label">Conexão WhatsApp</label><select name="whatsapp_conexao_id" class="input"><option value="">—</option>@foreach($conexoes as $c)<option value="{{ $c->id }}" @selected(($a->whatsapp_conexao_id ?? null)==$c->id)>{{ $c->nome }}</option>@endforeach</select></div>
    <div><label class="label">Conta de pagamento (PIX)</label><select name="pagamento_api_id" class="input"><option value="">—</option>@foreach($apis as $p)<option value="{{ $p->id }}" @selected(($a->pagamento_api_id ?? null)==$p->id)>{{ $p->nome }}</option>@endforeach</select></div>
    <div><label class="label">Vender com antecedência (meses)</label><input type="number" name="meses_venda" min="1" max="12" class="input" value="{{ $r['meses_venda'] ?? 2 }}" title="Padrão 2 meses. O preço é o mesmo em qualquer data futura."></div>
    <div><label class="label">⏱️ Sem resposta: avisar após (min)</label><input type="number" name="ocioso_aviso_min" min="0" max="120" class="input" value="{{ $r['ocioso_aviso_min'] ?? 2 }}" title="Minutos sem resposta do cliente até o agente avisar que vai encerrar. 0 = nunca encerra sozinho."><p class="text-[11px] text-slate-400 mt-0.5">0 = não encerra sozinho</p></div>
    <div><label class="label">⏱️ Encerrar após o aviso (min)</label><input type="number" name="ocioso_encerrar_min" min="1" max="60" class="input" value="{{ $r['ocioso_encerrar_min'] ?? 1 }}" title="Minutos a mais, depois do aviso, até encerrar a conversa e soltar a poltrona reservada."><p class="text-[11px] text-slate-400 mt-0.5">solta a poltrona e agradece</p></div>
    <div><label class="label">💳 Aguardar pagamento PIX (min)</label><input type="number" name="ocioso_pix_min" min="1" max="240" class="input" value="{{ $r['ocioso_pix_min'] ?? 30 }}" title="Com PIX gerado e ainda não pago, quantos minutos esperar antes de avisar/encerrar."><p class="text-[11px] text-slate-400 mt-0.5">com PIX gerado e não pago</p></div>
    <label class="flex items-end gap-2 text-sm pb-2"><input type="checkbox" name="ativo" value="1" @checked($a->ativo ?? true) class="w-5 h-5 rounded"> Agente ativo</label>
  </div>

  <div><label class="label">Persona / instruções</label>
    <textarea name="persona" class="input" rows="3" placeholder="Como o agente deve atender: tom, o que oferecer, como fechar a venda...">{{ $a->persona ?? '' }}</textarea></div>

  <div class="rounded-xl bg-slate-50 border p-3 space-y-2">
    <div class="text-xs font-bold text-slate-500 uppercase">Regras</div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="regras[desligado]" value="1" @checked($r['desligado'] ?? false) class="w-5 h-5 rounded"> Desligado (só humano atende)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="regras[nao_revela_robo]" value="1" @checked($r['nao_revela_robo'] ?? true) class="w-5 h-5 rounded"> Não revela que é robô</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="regras[sensiveis_humano]" value="1" @checked($r['sensiveis_humano'] ?? true) class="w-5 h-5 rounded"> Assunto sensível (reclamação/reembolso) → chama humano</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="regras[nunca_inventa]" value="1" @checked($r['nunca_inventa'] ?? true) class="w-5 h-5 rounded"> Nunca inventa preço/rota (só o que está no sistema)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="regras[checkin_auto]" value="1" @checked($r['checkin_auto'] ?? false) class="w-5 h-5 rounded"> Check-in automático nas vendas do agente (desmarcado = cliente faz check-in no embarque)</label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="regras[inclui_comida]" value="1" @checked($r['inclui_comida'] ?? true) class="w-5 h-5 rounded"> Viagem inclui alimentação (comida a bordo) — se desmarcar, o agente informa que NÃO dá comida</label>
    @php $am = $r['audio_modo'] ?? (($r['responde_audio'] ?? false) ? 'audio' : 'texto'); @endphp
    <div class="pt-1">
      <label class="label text-xs">Quando o cliente mandar ÁUDIO, o agente responde com</label>
      <select name="regras[audio_modo]" class="input sm:max-w-xs">
        <option value="texto" @selected($am==='texto')>Só texto</option>
        <option value="audio" @selected($am==='audio')>Só áudio</option>
        <option value="audio_texto" @selected($am==='audio_texto')>Áudio + texto (manda os dois)</option>
      </select>
    </div>
    <div class="grid sm:grid-cols-2 gap-2 pl-7">
      <div>
        <label class="label text-xs">Token OpenAI p/ áudio</label>
        <input name="regras[audio_token]" type="password" class="input font-mono text-sm" autocomplete="off" placeholder="{{ !empty($r['audio_token']) ? '•••• salvo — deixe em branco p/ manter' : 'sk-...' }}">
        <p class="text-[11px] text-slate-400 mt-0.5">Cole o token da OpenAI. Ele ouve o áudio do cliente e gera a voz da resposta. @if(!empty($r['audio_token']))<span class="text-emerald-600 font-semibold">Token salvo ✓</span>@endif</p>
        @if($a)
        <div class="flex items-center gap-2 mt-1 flex-wrap">
          <button type="button" class="btn btn-ghost btn-sm !py-1 !text-xs" onclick="testarAudioAgente(this,'{{ route('admin.agentes.testar-audio',$a) }}')">🔌 Testar API de áudio</button>
          <span class="text-xs" data-audio-result></span>
        </div>
        @endif
      </div>
      <div>
        <label class="label text-xs">Voz da resposta</label>
        <select name="regras[audio_voz]" class="input">
          @foreach(['alloy'=>'Alloy (neutra)','nova'=>'Nova (feminina)','shimmer'=>'Shimmer (feminina suave)','coral'=>'Coral (feminina)','echo'=>'Echo (masculina)','onyx'=>'Onyx (masculina grave)'] as $vk=>$vl)
            <option value="{{ $vk }}" @selected(($r['audio_voz'] ?? 'alloy')==$vk)>{{ $vl }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <div class="grid sm:grid-cols-2 gap-3">
    <div><label class="label">Quando assume</label>
      <select name="assumir_modo" x-model="modo" class="input">@foreach(\App\Models\Agente::ASSUMIR as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
    <div x-show="modo==='apos_min'"><label class="label">Minutos sem resposta do humano</label>
      <input type="number" name="assumir_min" min="0" max="120" class="input" value="{{ $a->assumir_min ?? 2 }}"></div>
  </div>

  <div class="rounded-xl bg-slate-50 border p-3 space-y-2">
    <div class="flex items-center gap-4">
      <span class="text-xs font-bold text-slate-500 uppercase">Horário</span>
      <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="hr_modo" value="24h" x-model="hr"> 24 horas</label>
      <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="hr_modo" value="faixa" x-model="hr"> Faixa</label>
    </div>
    <div x-show="hr==='faixa'" x-cloak class="space-y-2">
      <div class="flex gap-2 items-end">
        <div><label class="label !text-xs">Das</label><input type="time" name="hr_inicio" class="input !min-h-[38px]" value="{{ $h['inicio'] ?? '08:00' }}"></div>
        <div><label class="label !text-xs">até</label><input type="time" name="hr_fim" class="input !min-h-[38px]" value="{{ $h['fim'] ?? '18:00' }}"></div>
      </div>
      <div class="flex flex-wrap gap-2">
        @foreach([1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'] as $dn=>$dl)
        <label class="flex items-center gap-1 text-sm px-2 py-1 rounded-lg border {{ in_array($dn,$dias)?'bg-white':'bg-slate-100' }}"><input type="checkbox" name="hr_dias[]" value="{{ $dn }}" @checked(in_array($dn,$dias))> {{ $dl }}</label>
        @endforeach
      </div>
    </div>
    <p class="text-[11px] text-slate-400">Fora do horário humano, use “Só fora do horário” em “Quando assume” pra IA cobrir a madrugada.</p>
  </div>

  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="recupera_funil" value="1" @checked($a->recupera_funil ?? false) class="w-5 h-5 rounded"> Recuperar cliente que sumiu (funil)</label>

  <div class="flex items-center justify-between border-t pt-3">
    @if($a)<button type="button" class="text-xs text-red-500 hover:underline" onclick="document.getElementById('del-{{ $a->id }}').submit()">excluir agente</button>@else<span></span>@endif
    <button class="btn btn-primary btn-sm">{{ $a ? 'Salvar agente' : 'Criar agente' }}</button>
  </div>
</form>
@if($a)
<form method="post" action="{{ route('admin.agentes.testar',$a) }}" class="mt-2 flex gap-2">@csrf
  <input name="msg" class="input !min-h-[38px] flex-1" placeholder="Simular mensagem do cliente (ex.: tem passagem pra Parintins?)">
  <button class="btn btn-ghost btn-sm">🧪 Testar agente</button>
</form>
@endif
@once
@push('scripts')
<script>
async function testarAudioAgente(btn, url){
  var form = btn.closest('form');
  var inp = form ? form.querySelector('input[name="regras[audio_token]"]') : null;
  var tok = inp ? (inp.value || '') : '';
  var res = btn.parentElement.querySelector('[data-audio-result]');
  res.textContent = 'testando a OpenAI...'; res.className = 'text-xs text-slate-500';
  btn.disabled = true;
  try {
    var r = await fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Content-Type':'application/json','X-Requested-With':'fetch'}, body: JSON.stringify({token: tok})});
    var j = await r.json();
    res.textContent = j.msg || (j.ok ? 'ok' : 'erro');
    res.className = 'text-xs font-semibold ' + (j.ok ? 'text-emerald-600' : 'text-rose-600');
  } catch (e) { res.textContent = 'Erro de rede: ' + e; res.className = 'text-xs text-rose-600'; }
  btn.disabled = false;
}
</script>
@endpush
@endonce
@if($a)<form id="del-{{ $a->id }}" method="post" action="{{ route('admin.agentes.excluir',$a) }}" class="hidden" onsubmit="return confirm('Remover agente?')">@csrf @method('delete')</form>@endif
