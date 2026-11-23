{{-- Atualiza a tela sozinha quando entra venda/baixa nova. Leve: só um hash a cada poucos segundos.
     Com ['soft' => '#seletor'] troca só o conteúdo (sem recarregar a página, sem perder a rolagem);
     sem isso, recarrega a página. Nunca atualiza enquanto o usuário digita ou tem janela aberta. --}}
<script>
(function(){
  var base = null, soft = @json($soft ?? null), ocupado = false;
  function digitando(){ var e=document.activeElement; return e && /^(INPUT|TEXTAREA|SELECT)$/.test(e.tagName); }
  function janelaAberta(){
    var els = document.querySelectorAll('.fixed.inset-0, [data-rt-hold]');
    for (var i=0;i<els.length;i++){ if (els[i].offsetParent !== null || els[i].getClientRects().length) return true; }
    return false;
  }
  function pisca(){
    var b = document.querySelector('[data-rt-live]'); if(!b) return;
    b.classList.add('ring-2','ring-white'); setTimeout(function(){ b.classList.remove('ring-2','ring-white'); }, 900);
  }
  async function atualizar(){
    if (!soft) { location.reload(); return true; }
    var alvo = document.querySelector(soft); if (!alvo) { location.reload(); return true; }
    var r = await fetch(location.href, {headers:{'X-Requested-With':'fetch'}, credentials:'same-origin'});
    if (!r.ok) return false;
    var doc = new DOMParser().parseFromString(await r.text(), 'text/html');
    var novo = doc.querySelector(soft); if (!novo) return false;
    if (digitando() || janelaAberta()) return false; // mudou enquanto buscava
    alvo.innerHTML = novo.innerHTML; // Alpine inicializa sozinho o que entrou
    pisca();
    return true;
  }
  async function tick(){
    if (ocupado || document.hidden) return;
    ocupado = true;
    try{
      var r = await fetch('{{ route('sync.pulse') }}', {headers:{'X-Requested-With':'fetch'}, credentials:'same-origin'});
      if(r.ok){
        var d = await r.json();
        if(base===null){ base = d.hash; }
        else if(d.hash && d.hash!==base && !digitando() && !janelaAberta()){
          if (await atualizar()) base = d.hash;
        }
      }
    }catch(e){}
    ocupado = false;
  }
  setInterval(tick, {{ (int) ($intervalo ?? 8000) }});
  document.addEventListener('visibilitychange', function(){ if(!document.hidden) tick(); });
})();
</script>
