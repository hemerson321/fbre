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

  <div id="somhint" onclick="this.style.display='none'" style="position:fixed;left:0;right:0;bottom:0;padding:11px;text-align:center;background:rgba(5,150,105,.94);color:#fff;font-size:14px;font-weight:700;cursor:pointer;z-index:9">🔊 Toque na tela pra ativar o som (só na primeira vez)</div>

<script>
(function(){
  var DADOS = @json(route('audio.dados', $terminal->token));
  var SINO_TIPO = @json($sinoTipo ?? 'aviao');
  var SINO_URL = @json(asset('marketing/airplane-chime.mp3'));
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

  var _actx = null;
  function ensureAudio(){
    try{
      if(!_actx){ var AC = window.AudioContext || window.webkitAudioContext; if(AC) _actx = new AC(); }
      if(_actx && _actx.state === 'suspended') _actx.resume();
    }catch(e){}
    return _actx;
  }
  // Sino de cabine de aviao via Web Audio (sem arquivo). tipo: aviao | aeroporto | suave.
  function seqSino(tipo){
    if(tipo==='aeroporto') return [ {f:783.99,t:0.00}, {f:523.25,t:0.42} ];                   // bing-bong agudo->grave
    if(tipo==='suave')     return [ {f:523.25,t:0.00}, {f:659.25,t:0.30}, {f:783.99,t:0.60} ]; // 3 notas subindo
    return [ {f:587.33,t:0.00}, {f:440.00,t:0.44} ];                                           // 'aviao': dim-dom grave (cabine)
  }
  function tocaSino(tipo){
    return new Promise(function(resolve){
      var ctx = ensureAudio();
      if(!ctx){ resolve(); return; }
      try{
        var t0 = ctx.currentTime + 0.03;
        var notas = seqSino(tipo), fim = t0;
        notas.forEach(function(n){
          var s = t0 + n.t;
          [[1,0.5],[2.0,0.14],[3.0,0.05]].forEach(function(p){   // partais -> timbre de sino quente
            var osc = ctx.createOscillator(), g = ctx.createGain();
            osc.type = 'sine'; osc.frequency.value = n.f * p[0];
            g.gain.setValueAtTime(0.0001, s);
            g.gain.exponentialRampToValueAtTime(p[1], s + 0.014);
            g.gain.exponentialRampToValueAtTime(0.0001, s + 1.35);
            osc.connect(g); g.connect(ctx.destination);
            osc.start(s); osc.stop(s + 1.4);
          });
          fim = Math.max(fim, s + 1.35);
        });
        var ms = (fim - ctx.currentTime) * 1000 - 260;
        setTimeout(resolve, Math.max(380, ms));
      }catch(e){ resolve(); }
    });
  }

  // Sino real (MP3 de cabine de aviao). Cai no sino sintetizado se o arquivo falhar.
  function tocaSinoReal(){
    return new Promise(function(resolve){
      try{
        var a = new Audio(SINO_URL), feito=false;
        function fim(){ if(feito) return; feito=true; resolve(); }
        function fallback(){ if(feito) return; feito=true; tocaSino('aviao').then(resolve); }
        a.onended = fim; a.onerror = fallback;
        a.play().catch(fallback);
        setTimeout(fim, 7000);
      }catch(e){ tocaSino('aviao').then(resolve); }
    });
  }

  function tocaItem(it, done){
    addLog(it.nome || 'Áudio');
    setNow('🔊 ' + (it.nome||''), true);
    function falar(){
      if(it.url){
        try{
          var au = new Audio(it.url);
          au.onplay = escondeHint;
          au.onended = done; au.onerror = done;
          au.play().catch(done);
        }catch(e){ done(); }
        return;
      }
      // TTS (falado) — fallback quando nao ha MP3 gerado (sem provedor de voz)
      if(window.speechSynthesis && it.texto){
        try{
          var u = new SpeechSynthesisUtterance(it.texto);
          u.lang = 'pt-BR'; u.rate = 1; u.pitch = 1;
          if(ptbr) u.voice = ptbr;
          u.onstart = escondeHint;
          u.onend = done; u.onerror = done;
          speechSynthesis.speak(u);
        }catch(e){ done(); }
        return;
      }
      done();
    }
    if(it.sino){ escondeHint(); (SINO_TIPO==='aviao' ? tocaSinoReal() : tocaSino(SINO_TIPO)).then(falar); }
    else { falar(); }
  }
  function drena(){
    if(tocando) return;
    var it = fila.shift();
    if(!it){ setNow('aguardando…', false); return; }
    tocando = true;
    var vezes = Math.max(1, parseInt(it.vezes||1,10));
    var intervalo = Math.max(0, parseInt(it.intervalo||0,10))*1000;
    var n = 0;
    (function umaVez(){
      n++;
      tocaItem(it, function(){
        if(n < vezes){ setTimeout(umaVez, intervalo); }
        else { tocando = false; setTimeout(drena, 400); }
      });
    })();
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

  function escondeHint(){ var h=document.getElementById('somhint'); if(h) h.style.display='none'; }
  var somLiberado=false;
  function liberaSom(){ if(somLiberado) return; somLiberado=true;
    ensureAudio();
    try{ if(window.speechSynthesis){ var w=new SpeechSynthesisUtterance(' '); w.volume=0; speechSynthesis.speak(w); escolheVoz(); } }catch(e){}
    escondeHint();
  }
  ['pointerdown','keydown','touchstart'].forEach(function(ev){ document.addEventListener(ev, liberaSom); });

  // Inicia sozinho, já aguardando (sem botão). Tenta liberar o som de cara (kiosk com autoplay liberado);
  // se o navegador exigir gesto, o aviso some no primeiro toque ou quando o 1º áudio tocar.
  try{ if(window.speechSynthesis){ var w0=new SpeechSynthesisUtterance(' '); w0.volume=0; speechSynthesis.speak(w0); escolheVoz(); } }catch(e){}
  clock(); setInterval(clock, 1000);
  poll(); setInterval(poll, 15000);
})();
</script>
</body>
</html>
