<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Áudio terminal · {{ $terminal->nome }}</title>
<style>
  :root{color-scheme:dark}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;background:radial-gradient(130% 120% at 50% 0%,#12233f,#070d17 70%);
    color:#e7edf5;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center}
  .emp{font-size:13px;letter-spacing:.18em;text-transform:uppercase;color:#7f93b4;font-weight:700}
  .nome{font-size:clamp(28px,6vw,54px);font-weight:900;letter-spacing:-.02em;margin:6px 0 2px}
  .sub{color:#8aa0bc;font-size:14px}
  .ico{font-size:clamp(60px,14vw,130px);line-height:1;margin:18px 0}
  .ico.on{animation:pulse 1.1s infinite}
  @keyframes pulse{0%,100%{transform:scale(1);opacity:.9}50%{transform:scale(1.08);opacity:1}}
  .now{min-height:28px;font-size:clamp(16px,3vw,24px);font-weight:700;color:#5ef0a4}
  .status{display:inline-flex;align-items:center;gap:8px;margin-top:14px;font-size:13px;color:#8aa0bc}
  .dot{width:9px;height:9px;border-radius:50%;background:#64748b}
  .dot.live{background:#34d399;box-shadow:0 0 10px #34d399}
  .clock{font-variant-numeric:tabular-nums;font-size:15px;color:#aab8cf;margin-top:4px}
  .log{margin-top:22px;width:100%;max-width:520px;text-align:left}
  .log h4{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#6b7f9e;margin:0 0 6px}
  .log div{font-size:13px;color:#aab8cf;padding:4px 0;border-top:1px solid #1b2942}
  #overlay{position:fixed;inset:0;background:#070d17;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:18px;z-index:9}
  #start{background:#059669;color:#fff;border:none;border-radius:16px;padding:18px 34px;font-size:20px;font-weight:800;cursor:pointer;box-shadow:0 10px 30px -10px #059669}
  .hint{color:#8aa0bc;font-size:14px;max-width:320px}
</style>
</head>
<body>
  <div class="emp">{{ $empresa }}</div>
  <div class="nome">🔊 {{ $terminal->nome }}</div>
  <div class="sub">áudio terminal</div>
  <div id="ico" class="ico">🔈</div>
  <div class="now" id="now">aguardando…</div>
  <div class="status"><span class="dot" id="dot"></span><span id="st">conectando…</span></div>
  <div class="clock" id="clock"></div>
  <div class="log"><h4>Últimos tocados</h4><div id="loglist"></div></div>

  <div id="overlay">
    <div class="emp">{{ $empresa }}</div>
    <div class="nome" style="font-size:clamp(22px,5vw,40px)">🔊 {{ $terminal->nome }}</div>
    <button id="start">▶ Iniciar áudio terminal</button>
    <div class="hint">Toque uma vez pra liberar o som. Depois é só deixar aberto — os áudios tocam sozinhos no horário e quando alguém mandar tocar agora.</div>
  </div>

<script>
(function(){
  var DADOS = @json(route('audio.dados', $terminal->token));
  var played = [];
  try { played = JSON.parse(localStorage.getItem('audioterm_played') || '[]') || []; } catch(e){ played = []; }
  function marca(oid){ played.push(oid); if(played.length>400) played = played.slice(-400); try{ localStorage.setItem('audioterm_played', JSON.stringify(played)); }catch(e){} }
  function visto(oid){ return played.indexOf(oid) !== -1; }

  var fila = [], tocando = false, ptbr = null;

  function escolheVoz(){
    try{
      var vs = window.speechSynthesis ? speechSynthesis.getVoices() : [];
      ptbr = vs.filter(function(v){ return /pt[-_]?br/i.test(v.lang) || /pt[-_]?pt/i.test(v.lang) || /^pt/i.test(v.lang); })[0] || null;
    }catch(e){}
  }
  if(window.speechSynthesis){ escolheVoz(); try{ speechSynthesis.onvoiceschanged = escolheVoz; }catch(e){} }

  function addLog(nome){
    var el = document.getElementById('loglist');
    var d = document.createElement('div');
    var hh = new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
    d.textContent = '▶ ' + nome + '  ·  ' + hh;
    el.insertBefore(d, el.firstChild);
    while(el.children.length>8) el.removeChild(el.lastChild);
  }
  function setNow(txt, on){
    document.getElementById('now').textContent = txt;
    document.getElementById('ico').className = 'ico' + (on?' on':'');
    document.getElementById('ico').textContent = on ? '🔊' : '🔈';
  }

  function tocaItem(it, done){
    addLog(it.nome || 'Áudio');
    setNow('🔊 ' + (it.nome||''), true);
    if(it.tipo === 'arquivo' && it.url){
      try{
        var au = new Audio(it.url);
        au.onended = done; au.onerror = done;
        au.play().catch(done);
      }catch(e){ done(); }
      return;
    }
    // TTS (falado)
    if(window.speechSynthesis && it.texto){
      try{
        var u = new SpeechSynthesisUtterance(it.texto);
        u.lang = 'pt-BR'; u.rate = 1; u.pitch = 1;
        if(ptbr) u.voice = ptbr;
        u.onend = done; u.onerror = done;
        speechSynthesis.speak(u);
      }catch(e){ done(); }
      return;
    }
    done();
  }
  function drena(){
    if(tocando) return;
    var it = fila.shift();
    if(!it){ setNow('aguardando…', false); return; }
    tocando = true;
    tocaItem(it, function(){ tocando = false; setTimeout(drena, 400); });
  }

  async function poll(){
    try{
      var r = await fetch(DADOS, {headers:{'X-Requested-With':'fetch'}, cache:'no-store'});
      if(!r.ok) throw 0;
      var d = await r.json();
      setStatus(true);
      (d.itens||[]).forEach(function(it){
        if(!visto(it.oid)){ marca(it.oid); fila.push(it); }
      });
      drena();
    }catch(e){ setStatus(false); }
  }
  function setStatus(ok){
    document.getElementById('dot').className = 'dot' + (ok?' live':'');
    document.getElementById('st').textContent = ok ? 'conectado · tocando programação e disparos' : 'sem conexão — tentando de novo';
  }
  function clock(){
    document.getElementById('clock').textContent = new Date().toLocaleString('pt-BR',{weekday:'short',hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }

  document.getElementById('start').addEventListener('click', function(){
    document.getElementById('overlay').style.display = 'none';
    // desbloqueia TTS no gesto do usuário
    try{ if(window.speechSynthesis){ var w=new SpeechSynthesisUtterance(' '); w.volume=0; speechSynthesis.speak(w); escolheVoz(); } }catch(e){}
    clock(); setInterval(clock, 1000);
    poll(); setInterval(poll, 15000);
  });
})();
</script>
</body>
</html>
