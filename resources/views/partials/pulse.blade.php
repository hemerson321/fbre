{{-- Atualiza a tela sozinha quando entra venda/baixa nova. Leve: só um hash a cada 8s; recarrega só quando muda e se não estiver digitando. --}}
<script>
(function(){
  var base = null;
  function digitando(){ var e=document.activeElement; return e && /^(INPUT|TEXTAREA|SELECT)$/.test(e.tagName); }
  async function tick(){
    try{
      var r = await fetch('{{ route('sync.pulse') }}', {headers:{'X-Requested-With':'fetch'}, credentials:'same-origin'});
      if(!r.ok) return;
      var d = await r.json();
      if(base===null){ base = d.hash; return; }
      if(d.hash && d.hash!==base && !digitando()){ location.reload(); }
    }catch(e){}
  }
  setInterval(tick, 8000);
})();
</script>
