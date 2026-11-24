{{-- Motor do Digital Signage: os 10 modelos de aviso + o player (sequência, intervalo e horário).
     Usado no player da TV (/tv/{token}) e nas prévias da página de administração. --}}
@verbatim
<style>
.sg-stage{position:relative;width:100%;height:100%;overflow:hidden;background:#000;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;--u:6px}
.sg-slide{position:absolute;inset:0;opacity:0;transition:opacity .7s ease;display:flex;align-items:center;justify-content:center;background:#000}
.sg-slide.on{opacity:1;z-index:2}
.sg-slide img,.sg-slide video{width:100%;height:100%;object-fit:contain;background:#000;display:block}
.sg-slide.cover img,.sg-slide.cover video{object-fit:cover}
.sg-idle{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:calc(var(--u)*3);color:#e2e8f0;background:radial-gradient(circle at 30% 20%,#1e293b,#020617)}
.sg-idle b{font-size:calc(var(--u)*9);letter-spacing:.02em;text-align:center;padding:0 calc(var(--u)*4)}
.sg-idle span{font-size:calc(var(--u)*5);opacity:.7;font-variant-numeric:tabular-nums}
.sg-idle small{font-size:calc(var(--u)*2.4);opacity:.45}
/* ---------- modelos de aviso ---------- */
.sg-l{position:absolute;inset:0;display:flex;flex-direction:column;overflow:hidden;color:#fff}
.sg-deco{position:absolute;inset:0;pointer-events:none}
.sg-head{position:relative;display:flex;align-items:center;gap:calc(var(--u)*2.2);padding:calc(var(--u)*3.2) calc(var(--u)*5);min-height:calc(var(--u)*15);flex:none}
.sg-ico{font-size:calc(var(--u)*8);line-height:1;flex:none}
.sg-tit{font-size:calc(var(--u)*7);font-weight:900;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.1}
.sg-body{position:relative;flex:1;min-height:0;display:flex;align-items:center;justify-content:center;padding:calc(var(--u)*3) calc(var(--u)*6);overflow:hidden}
.sg-txt{font-weight:800;line-height:1.14;text-align:center;white-space:pre-wrap;overflow-wrap:normal;word-break:normal;hyphens:none;max-width:100%}
.sg-foot{position:relative;display:flex;align-items:center;justify-content:space-between;gap:calc(var(--u)*2);padding:calc(var(--u)*2) calc(var(--u)*5);font-size:calc(var(--u)*3);font-weight:700;opacity:.92;flex:none}
.sg-foot span:last-child{font-variant-numeric:tabular-nums}
/* 1 Aviso */
.sg-l1{background:#facc15;color:#111827}
.sg-l1 .sg-head{background:#111827;color:#facc15}
.sg-l1 .sg-deco{background:repeating-linear-gradient(135deg,#111827 0 calc(var(--u)*3),transparent calc(var(--u)*3) calc(var(--u)*6));height:calc(var(--u)*2.4);top:auto;bottom:calc(var(--u)*8)}
.sg-l1 .sg-foot{background:#111827;color:#fde68a}
/* 2 Urgente */
.sg-l2{background:linear-gradient(160deg,#b91c1c,#7f1d1d)}
.sg-l2 .sg-head{background:#fff;color:#b91c1c}
.sg-l2 .sg-deco{box-shadow:inset 0 0 0 calc(var(--u)*1.4) rgba(255,255,255,.9);animation:sgPulse 1.4s ease-in-out infinite}
.sg-l2 .sg-foot{background:rgba(0,0,0,.28)}
@keyframes sgPulse{50%{box-shadow:inset 0 0 0 calc(var(--u)*1.4) rgba(255,255,255,.25)}}
/* 3 Informativo */
.sg-l3{background:linear-gradient(135deg,#1d4ed8,#0ea5e9)}
.sg-l3 .sg-head{background:rgba(255,255,255,.14);backdrop-filter:blur(4px)}
.sg-l3 .sg-deco{background:radial-gradient(circle at 88% 12%,rgba(255,255,255,.22),transparent 38%),radial-gradient(circle at 8% 92%,rgba(255,255,255,.14),transparent 42%)}
.sg-l3 .sg-foot{background:rgba(2,6,23,.28)}
/* 4 Boas-vindas */
.sg-l4{background:linear-gradient(140deg,#047857,#10b981)}
.sg-l4 .sg-head{justify-content:center}
.sg-l4 .sg-deco{background:radial-gradient(circle at 50% -10%,rgba(255,255,255,.3),transparent 55%)}
.sg-l4 .sg-foot{justify-content:center;gap:calc(var(--u)*5);background:rgba(0,0,0,.18)}
/* 5 Elegante */
.sg-l5{background:#0b0b0f;color:#f5e6b8}
.sg-l5 .sg-head{justify-content:center;border-bottom:calc(var(--u)*.35) solid #c9a44a;margin:0 calc(var(--u)*6);padding-left:0;padding-right:0}
.sg-l5 .sg-tit{color:#e7c873;letter-spacing:.16em;font-weight:700}
.sg-l5 .sg-txt{font-weight:600;font-family:Georgia,"Times New Roman",serif}
.sg-l5 .sg-deco{box-shadow:inset 0 0 0 calc(var(--u)*.5) #c9a44a;margin:calc(var(--u)*2)}
.sg-l5 .sg-foot{color:#c9a44a}
/* 6 Gradiente */
.sg-l6{background:linear-gradient(135deg,#7c3aed,#db2777 55%,#f59e0b)}
.sg-l6 .sg-deco{background:radial-gradient(circle at 15% 20%,rgba(255,255,255,.25),transparent 30%),radial-gradient(circle at 85% 80%,rgba(255,255,255,.2),transparent 34%)}
.sg-l6 .sg-body{margin:0 calc(var(--u)*4) calc(var(--u)*2);background:rgba(255,255,255,.14);border-radius:calc(var(--u)*4);backdrop-filter:blur(6px)}
/* 7 Promoção */
.sg-l7{background:#ea580c}
.sg-l7 .sg-head{background:#fff;color:#ea580c}
.sg-l7 .sg-tit{background:#ea580c;color:#fff;padding:calc(var(--u)*.6) calc(var(--u)*2.4);border-radius:calc(var(--u)*1.4);transform:rotate(-2deg)}
.sg-l7 .sg-deco{background:repeating-conic-gradient(from 0deg at 50% 55%,rgba(255,255,255,.1) 0 10deg,transparent 10deg 20deg)}
.sg-l7 .sg-txt{text-shadow:0 calc(var(--u)*.5) 0 rgba(124,45,18,.6)}
.sg-l7 .sg-foot{background:#7c2d12}
/* 8 Embarque (painel) */
.sg-l8{background:#06182e;color:#e0f2fe}
.sg-l8 .sg-head{background:#0b2a4d;border-bottom:calc(var(--u)*.6) solid #22d3ee}
.sg-l8 .sg-tit{color:#22d3ee}
.sg-l8 .sg-txt{font-family:"Courier New",ui-monospace,monospace;color:#fde047;text-align:left;letter-spacing:.01em}
.sg-l8 .sg-body{justify-content:flex-start;align-items:flex-start;padding-top:calc(var(--u)*5)}
.sg-l8 .sg-deco{background:repeating-linear-gradient(0deg,rgba(255,255,255,.035) 0 calc(var(--u)*.4),transparent calc(var(--u)*.4) calc(var(--u)*.8))}
.sg-l8 .sg-foot{background:#0b2a4d;color:#7dd3fc}
/* 9 Clean */
.sg-l9{background:#f8fafc;color:#0f172a}
.sg-l9 .sg-head{color:#2563eb}
.sg-l9 .sg-deco{border-left:calc(var(--u)*2.2) solid #2563eb}
.sg-l9 .sg-txt{font-weight:700}
.sg-l9 .sg-foot{color:#64748b;border-top:calc(var(--u)*.3) solid #e2e8f0}
/* 10 Neon */
.sg-l10{background:#030712;color:#bbf7d0}
.sg-l10 .sg-tit{color:#4ade80;text-shadow:0 0 calc(var(--u)*2) #22c55e}
.sg-l10 .sg-txt{color:#fff;text-shadow:0 0 calc(var(--u)*1.2) rgba(74,222,128,.9),0 0 calc(var(--u)*4) rgba(34,197,94,.55)}
.sg-l10 .sg-deco{box-shadow:inset 0 0 0 calc(var(--u)*.6) #22c55e,inset 0 0 calc(var(--u)*8) rgba(34,197,94,.35);margin:calc(var(--u)*2.4);border-radius:calc(var(--u)*4)}
.sg-l10 .sg-foot{color:#4ade80;padding-left:calc(var(--u)*8);padding-right:calc(var(--u)*8);padding-bottom:calc(var(--u)*5)}
</style>
<script>
(function(){
  var ICONES = {1:'⚠️',2:'🚨',3:'ℹ️',4:'👋',5:'✦',6:'✨',7:'🔥',8:'🚤',9:'📌',10:'⚡'};
  var TITULOS = {1:'AVISO',2:'ATENÇÃO',3:'INFORMATIVO',4:'BEM-VINDO',5:'COMUNICADO',6:'NOVIDADE',7:'PROMOÇÃO',8:'EMBARQUE',9:'RECADO',10:'DESTAQUE'};
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function hora(){ var d=new Date(); return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); }
  function unidade(el){ var r=el.getBoundingClientRect(); var u=Math.min(r.width,r.height)/100; el.style.setProperty('--u',(u>0?u:6)+'px'); return u; }

  /* texto ocupa o máximo do espaço sem estourar — vale pra qualquer tela (vertical/horizontal, TV ou prévia pequena) */
  function ajustar(raiz){
    var body=raiz.querySelector('.sg-body'), txt=raiz.querySelector('.sg-txt'); if(!body||!txt) return;
    var cs=getComputedStyle(body);
    var W=body.clientWidth-parseFloat(cs.paddingLeft)-parseFloat(cs.paddingRight);
    var H=body.clientHeight-parseFloat(cs.paddingTop)-parseFloat(cs.paddingBottom);
    if(W<=0||H<=0) return;
    var lo=1, hi=Math.max(8,Math.min(W,H)*1.2), i;
    for(i=0;i<22;i++){
      var mid=(lo+hi)/2; txt.style.fontSize=mid+'px';
      if(txt.scrollWidth<=W+1 && txt.offsetHeight<=H+1) lo=mid; else hi=mid;
    }
    txt.style.fontSize=Math.floor(lo)+'px';
    var tit=raiz.querySelector('.sg-tit'), head=raiz.querySelector('.sg-head');
    if(tit&&head){ tit.style.fontSize=''; var n=0; while(tit.scrollWidth>tit.clientWidth+1 && n<14){ tit.style.fontSize=(parseFloat(getComputedStyle(tit).fontSize)*0.92)+'px'; n++; } }
  }

  function htmlTexto(it, ctx){
    var n=parseInt(it.layout,10)||1; if(n<1||n>10) n=1;
    var tit=(it.titulo&&String(it.titulo).trim())||TITULOS[n];
    return '<div class="sg-l sg-l'+n+'"><div class="sg-deco"></div>'+
      '<div class="sg-head"><span class="sg-ico">'+ICONES[n]+'</span><span class="sg-tit">'+esc(tit)+'</span></div>'+
      '<div class="sg-body"><div class="sg-txt">'+esc(it.texto||'Seu aviso aparece aqui')+'</div></div>'+
      '<div class="sg-foot"><span>'+esc((ctx&&ctx.empresa)||'')+'</span><span class="sg-clock">'+hora()+'</span></div></div>';
  }

  /* desenha UM item num elemento (prévia estática ou slide do player) */
  function desenhar(el, it, ctx){
    unidade(el.closest('.sg-stage')||el);
    if(it.tipo==='texto'){ el.innerHTML=htmlTexto(it,ctx); ajustar(el); return null; }
    if(it.tipo==='video'){
      el.innerHTML='<video playsinline '+((ctx&&ctx.previa)?'muted loop ':'')+'preload="auto"></video>';
      var v=el.firstChild; v.src=it.url||''; return v;
    }
    el.innerHTML=it.url?'<img alt="">':'<div class="sg-idle"><small>sem imagem</small></div>';
    if(it.url) el.firstChild.src=it.url;
    return null;
  }

  /* ---------------- player ---------------- */
  function Player(stage, opt){
    var self=this; opt=opt||{};
    this.stage=stage; this.opt=opt; this.itens=[]; this.idx=-1; this.atual=null; this.interrompendo=false;
    this.timer=null; this.audio=null; this.disparados={}; this.ultimoIntervalo={}; this.empresa=opt.empresa||''; this.assinatura='';
    stage.classList.add('sg-stage');
    stage.innerHTML='<div class="sg-slide"></div><div class="sg-slide"></div><div class="sg-idle" style="z-index:1"><b></b><span></span><small></small></div>';
    this.slides=stage.querySelectorAll('.sg-slide'); this.idle=stage.querySelector('.sg-idle'); this.frente=0;
    this.relogio=setInterval(function(){ self.tique(); },1000);
    this._rz=function(){ unidade(stage); var on=stage.querySelector('.sg-slide.on'); if(on) ajustar(on); };
    window.addEventListener('resize', this._rz);
    unidade(stage); this.ociosa();
  }
  Player.prototype.ociosa=function(){
    this.idle.querySelector('b').textContent=this.empresa||'Digital Signage';
    this.idle.querySelector('span').textContent=hora();
    this.idle.querySelector('small').textContent=this.opt.rotulo||'';
  };
  Player.prototype.carregar=function(dados){
    var a=JSON.stringify(dados.itens||[]);
    this.empresa=dados.empresa||this.empresa;
    if(a===this.assinatura) return;
    this.assinatura=a; this.itens=dados.itens||[];
    var agora=Date.now(), self=this;
    this.itens.forEach(function(it){ if(it.modo==='intervalo' && !self.ultimoIntervalo[it.id]) self.ultimoIntervalo[it.id]=agora; });
    if(!this.atual || !this.itens.some(function(i){return i.id===self.atual.id;})) this.proximo();
  };
  Player.prototype.sequencia=function(){ return this.itens.filter(function(i){return i.modo==='sequencia';}); };
  Player.prototype.proximo=function(){
    this.interrompendo=false;
    var seq=this.sequencia();
    if(!seq.length){ this.atual=null; this.limpar(); return; }
    this.idx=(this.idx+1)%seq.length;
    this.mostrar(seq[this.idx]);
  };
  Player.prototype.limpar=function(){
    clearTimeout(this.timer); if(this.audio){ try{this.audio.pause();}catch(e){} this.audio=null; }
    for(var i=0;i<this.slides.length;i++){ this.slides[i].classList.remove('on'); }
    var self=this; setTimeout(function(){ if(!self.atual) for(var i=0;i<self.slides.length;i++) self.slides[i].innerHTML=''; },800);
  };
  Player.prototype.mostrar=function(it){
    var self=this; clearTimeout(this.timer);
    if(this.audio){ try{this.audio.pause();}catch(e){} this.audio=null; }
    this.atual=it;
    var novo=this.slides[1-this.frente], velho=this.slides[this.frente];
    var v=desenhar(novo, it, {empresa:this.empresa});
    novo.classList.add('on'); velho.classList.remove('on'); this.frente=1-this.frente;
    setTimeout(function(){ var pv=velho.querySelector('video'); if(pv){ try{pv.pause();}catch(e){} } velho.innerHTML=''; },900);
    var seg=parseInt(it.duracao,10)||0, fim=function(){ self.proximo(); };
    if(v){
      v.muted=!it.som || !!this.opt.mudo;
      v.onended=fim; v.onerror=function(){ self.timer=setTimeout(fim,1500); };
      var p=v.play(); if(p&&p.catch) p.catch(function(){ v.muted=true; var q=v.play(); if(q&&q.catch) q.catch(function(){ self.timer=setTimeout(fim,3000); }); });
      if(seg>0) this.timer=setTimeout(fim, seg*1000); else this.timer=setTimeout(fim, 15*60*1000); // trava de segurança
    } else {
      this.timer=setTimeout(fim, Math.max(3,seg||10)*1000);
    }
    if(it.audio && !this.opt.mudo){ try{ this.audio=new Audio(it.audio); var pa=this.audio.play(); if(pa&&pa.catch) pa.catch(function(){}); }catch(e){} }
  };
  Player.prototype.tique=function(){
    var d=new Date(), hm=hora(), dia=d.getDay(), chaveDia=d.toDateString(), self=this;
    var cl=this.stage.querySelectorAll('.sg-clock'); for(var i=0;i<cl.length;i++) cl[i].textContent=hm;
    if(!this.atual) this.ociosa();
    if(this.interrompendo) return;
    var vez=null;
    this.itens.forEach(function(it){
      if(vez) return;
      if(it.dias && it.dias.length && it.dias.indexOf(dia)<0) return;
      if(it.modo==='horario' && (it.horarios||[]).indexOf(hm)>=0){
        var k=it.id+'|'+chaveDia+'|'+hm; if(!self.disparados[k]){ self.disparados[k]=1; vez=it; }
      } else if(it.modo==='intervalo' && it.intervalo>0){
        var u=self.ultimoIntervalo[it.id]||0; if(Date.now()-u>=it.intervalo*60000){ self.ultimoIntervalo[it.id]=Date.now(); vez=it; }
      }
    });
    if(vez){ this.interrompendo=true; if(this.idx>=0) this.idx--; this.mostrar(vez); } // depois volta pro item que foi interrompido
  };
  Player.prototype.forcar=function(it){ this.interrompendo=true; if(this.idx>=0) this.idx--; this.mostrar(it); };
  Player.prototype.destruir=function(){ clearInterval(this.relogio); clearTimeout(this.timer); window.removeEventListener('resize',this._rz); if(this.audio){try{this.audio.pause();}catch(e){}} this.stage.innerHTML=''; };

  window.SG={ desenhar:desenhar, ajustar:ajustar, unidade:unidade, Player:Player, TITULOS:TITULOS, ICONES:ICONES };
})();
</script>
@endverbatim
