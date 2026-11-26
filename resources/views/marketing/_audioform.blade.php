{{-- Formulário de áudio (usado no modal "Novo" e na expansão "Editar"). Usa o escopo Alpine do card de Áudios:
     f.{nome,tipo,texto,provedor,voz,vozGoogle,estilo}, editId, baseAudio, testar(), testando, testMsg, testErro, fecharNovo(), fecharEditar() --}}
<form method="post" :action="editId ? baseAudio + '/' + editId : baseAudio" enctype="multipart/form-data" class="space-y-2">
  @csrf
  <div><label class="label">Nome</label><input name="nome" x-model="f.nome" class="input" required placeholder="Ex.: Chamada de embarque Codajás"></div>
  <div class="grid grid-cols-2 gap-2">
    <button type="button" @click="f.tipo='tts'" :class="f.tipo==='tts' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">🗣 Falado (texto)</button>
    <button type="button" @click="f.tipo='arquivo'" :class="f.tipo==='arquivo' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">📁 Arquivo</button>
  </div>
  <input type="hidden" name="tipo" :value="f.tipo">

  <div x-show="f.tipo==='tts'" x-cloak class="space-y-2">
    <div><label class="label">Texto que vai ser falado</label><textarea name="texto" x-model="f.texto" class="input" rows="3" placeholder="Ex.: Senhores passageiros, o barco para Codajás parte às 14 horas."></textarea></div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="label">Falar com</label>
        <select name="provedor" x-model="f.provedor" class="input">
          <option value="google">🟦 Google</option>
          <option value="openai">🤖 OpenAI</option>
        </select>
      </div>
      <div x-show="f.provedor==='openai'" x-cloak><label class="label">Tipo de voz</label>
        <select name="voz" x-model="f.voz" class="input">
          <option value="nova">👩 Nova</option><option value="shimmer">👩 Shimmer</option><option value="coral">👩 Coral</option><option value="alloy">🧑 Alloy</option><option value="onyx">👨 Onyx</option><option value="echo">👨 Echo</option>
        </select>
      </div>
      <div x-show="f.provedor==='google'" x-cloak><label class="label">Tipo de voz</label>
        <select name="voz_google" x-model="f.vozGoogle" class="input">
          <optgroup label="Chirp 3 HD (mais natural · conta de serviço)">
            <option value="pt-BR-Chirp3-HD-Aoede">👩 Aoede</option><option value="pt-BR-Chirp3-HD-Kore">👩 Kore</option><option value="pt-BR-Chirp3-HD-Leda">👩 Leda</option><option value="pt-BR-Chirp3-HD-Zephyr">👩 Zephyr</option><option value="pt-BR-Chirp3-HD-Charon">👨 Charon</option>
          </optgroup>
          <optgroup label="Neural2 / Wavenet (chave de API)">
            <option value="pt-BR-Neural2-A">👩 Neural2 A</option><option value="pt-BR-Neural2-C">👩 Neural2 C</option><option value="pt-BR-Wavenet-A">👩 Wavenet A</option><option value="pt-BR-Wavenet-C">👩 Wavenet C</option><option value="pt-BR-Neural2-B">👨 Neural2 B</option>
          </optgroup>
        </select>
      </div>
    </div>
    <div><label class="label !text-[10px] !mb-0.5 opacity-70">Estilo / como se comportar (opcional)</label>
      <input name="estilo" x-model="f.estilo" class="input !py-1 text-[12px]" placeholder="ex.: voz de aeromoça, calma e clara">
    </div>
    <label class="flex items-center gap-2 text-[13px] text-slate-600 cursor-pointer select-none">
      <input type="checkbox" name="sino" value="1" x-model="f.sino" class="w-4 h-4 rounded"> 🔔 Tocar sinal (tim-tim) antes de falar
    </label>
  </div>

  <div x-show="f.tipo==='arquivo'" x-cloak class="space-y-2">
    <div><label class="label">Arquivo de áudio (mp3, ogg, wav…)</label><input type="file" name="arquivo" accept="audio/*" class="input"></div>
    <div><label class="label">…ou cole o link do áudio</label><input name="arquivo_url" class="input" placeholder="https://..."></div>
  </div>

  <div class="flex items-center gap-2 flex-wrap pt-1">
    <button class="btn btn-primary btn-sm" x-text="editId ? 'Salvar alterações' : 'Salvar áudio'"></button>
    <button type="button" x-show="f.tipo==='tts'" @click="testar()" :disabled="testando" class="btn btn-ghost btn-sm" x-text="testando ? 'Gerando…' : '🔊 Ouvir'"></button>
    <button type="button" @click="editId ? fecharEditar() : fecharNovo()" class="btn btn-ghost btn-sm text-slate-500">Cancelar</button>
  </div>
  <p class="text-[12px] text-emerald-700 font-semibold" x-show="testMsg" x-cloak><span x-text="testMsg"></span></p>
  <p class="text-[12px] text-rose-600 font-semibold" x-show="testErro" x-cloak><span x-text="testErro"></span></p>
  <p class="text-[11px] text-slate-400" x-show="f.tipo==='tts'" x-cloak>O <b>Ouvir</b> gera na hora com a voz/estilo escolhidos aqui (usa a conta/chave do Google ou OpenAI das Integrações).</p>
</form>
