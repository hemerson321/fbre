<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  window.FBRE_OP = @js(['prefixo'=>auth()->user()->prefixo ?: 'OP'.auth()->id(), 'proximoSeq'=>(int)(auth()->user()->proximo_seq ?: 1)]);
  window.FBRE_PREFILL = @js($prefill ?? null);
  window.FBRE_EDICAO = @js($edicao ?? null);

  function novaPassagem(){
    return {
      salvando:false, edMode:false, edUrl:'', edNumero:'', tipoPag:'balcao', metodosAtivos:['dinheiro'], credParcel:false, dividir:false, pag:{dinheiro:null,pix:null,debito:null,credito:null}, proprietarioMaq:false, parcelas:1, imprimir:'direto',
      agenteId:'', agenteAcerto:false,
      fatBusca:'', fatResultados:[], fatSel:null, fatNome:'', fatEmpresa:'', fatDoc:'', fatDocTipo:'', clienteFaturadoId:null,
      pixManual: {{ auth()->user()->podePixManual() ? 'true':'false' }},
      pixDinamico: {{ (auth()->user()->podePixDinamico() && \App\Support\Tenant::empresa()->usaPix('passagem')) ? 'true':'false' }},
      pixModal:false, pixQr:'', pixCopia:'', pixCobId:null, pixValor:0, pixPoll:null, pixPagando:false, pixChecando:false,
      pixEstatico: {{ (auth()->user()->pix_modo === 'estatico' && auth()->user()->pix_chave) ? 'true':'false' }},
      pixEstDados: @js(['chave'=>auth()->user()->pix_chave, 'nome'=>auth()->user()->pix_recebedor, 'cidade'=>auth()->user()->pix_cidade]),
      pixEstModal:false, pixEstCopia:'',
      cidades:@js($cidades), linhas:@js($linhas), tipos:@js($tipos), trechoPrecos:@js($trechoPrecos), taxas:@js($taxas), normalId:@js($normalId),
      embarcacoesData:@js($embarcacoes->map(fn($e)=>['id'=>(string)$e->id,'nome'=>(string)$e->nome,'poltrona'=>(bool)$e->usa_poltrona,'qtd'=>(int)$e->poltronas_qtd,'colunas'=>(int)($e->poltronas_colunas ?: 4),'layout'=>(string)($e->poltronas_lista ?? '')])->values()),
      regrasIdade:@js($regrasIdade ?? []),
      gratInfo:null, pcdInfo:null, idosoMeiaInfo:null, _gratTok:0,
      podeRetroativo:@js($podeRetroativo), checkinAuto:@js($checkinAuto ?? true),
      capturaAberta:false, capturaUrl:'', capturaSid:'', capturaDesde:0, capturaTimer:null, capturaStatus:'', capturaAviso:'', _avisoT:null,
      camAberta:false, camStream:null, camFacing:'environment', camTargetIdx:0, camStatus:'', camLendo:false,
      usaEmbarque:@js($usaTaxaEmbarque ?? false), usaAlim:@js($usaAlimentacao ?? true), labelTaxa:@js($labelTaxa ?? 'Taxa de embarque'), labelAlim:@js($labelAlim ?? 'Alimentação'),
      trechoMap:{}, taxaMap:{},
      origemSel:@js($ultimaOrigem ?? ''), destinoSel:'',
      embarcacaoSel:@js((string) old('embarcacao_id', $embarcacaoPrincipal)),
      compradorNome:'', compradorTel:'', compradorDoc:'', observacao:'', documentosGerais:[],
      lancamentoEm:@js(now()->toDateString()),
      desconto:null,
      passageiros:[], abas:[], abaAtual:0, _restaurando:false,
      precoInfo:false,
      paxLista:[], paxAberto:-1,
      nonce: Math.random().toString(36).slice(2,10),  // nome de campo aleatorio -> mata o autofill do Chrome
      // viagem
      rotaId:'', linhaNome:'', paradasInfo:[], viagemData:'', viagemLabel:'', viagemDia:null, viagemHora:'', calAberto:false, calMes:null, calEscolha:null,
      diasCurto:['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
      // poltrona
      usaPoltrona:false, qtdPoltronas:0, colunasPoltrona:4, poltronasLayout:'', temLayout:false, ocupadas:[], reservadas:[], reservadasInfo:{}, travadas:[], _rtTimer:null, mapaAberto:false, paxAtual:0,
      numeroPreview:'—',

      init(){
        const op=window.FBRE_OP||{}; this.numeroPreview=(op.prefixo||'OP')+'-'+String(op.proximoSeq||1).padStart(4,'0');
        // mapas de preco por tipo e de taxa de embarque (origem|destino[|tipo])
        (this.trechoPrecos||[]).forEach(r=>{ this.trechoMap[(String(r.origem).toLowerCase())+'|'+(String(r.destino).toLowerCase())+'|'+r.tipo_passageiro_id]={tarifa:Number(r.tarifa)||0, alim:Number(r.alimentacao)||0, integral:Number(r.integral)||0}; });
        (this.taxas||[]).forEach(r=>{ this.taxaMap[(String(r.origem).toLowerCase())+'|'+(String(r.destino).toLowerCase())]=Number(r.taxa)||0; });
        // garante tipo_id inicial nos passageiros
        if(this.tipos.length && this.normalId==null) this.normalId=this.tipos[0].id;
        try{ const o=localStorage.getItem('fbre_origem'); if(o) this.origemSel=o; }catch(e){}
        // origem = ultima escolha (localStorage acima) ou servidor; se vier vazia, 1a parada da linha (nunca fica "—")
        if(!this.origemSel){ const ogs=this.origens; this.origemSel=(ogs&&ogs.length)?ogs[0]:(this.cidades[0]||''); }
        // destino NÃO é lembrado: muda a cada venda, começa sempre em branco
        try{ const t=localStorage.getItem('fbre_transporte'); if(t) this.embarcacaoSel=t; }catch(e){}
        this.$watch('origemSel', v=>{ try{ if(v) localStorage.setItem('fbre_origem',v);}catch(e){} });
        this.$watch('embarcacaoSel', v=>{ if(this._restaurando) return; this.mudarEmbarcacao(); if(this.linhasTransporte.length){ const n=x=>String(x||'').trim().toLowerCase(); if(this.origemSel && !this.origens.some(c=>n(c)===n(this.origemSel))) this.origemSel=''; if(!this.origemSel){ const ogs=this.origens; if(ogs.length) this.origemSel=ogs[0]; } if(this.destinoSel && !this.destinos.some(c=>n(c)===n(this.destinoSel))) this.destinoSel=''; } this.resolverLinha(); this.passageiros.forEach(p=>{ if(p.nascimento) this.aplicarIdade(p); }); try{ localStorage.setItem('fbre_transporte', v||''); }catch(e){} });
        this.$watch('origemSel', ()=>this.carregarGratuidade());
        this.$watch('destinoSel', ()=>this.carregarGratuidade());
        this.$watch('viagemData', ()=>this.carregarGratuidade());
        this.$watch('embarcacaoSel', ()=>this.carregarGratuidade());
        this.$watch('origemSel', ()=>this.carregarOcupadas());
        this.$watch('destinoSel', ()=>this.carregarOcupadas());
        this.$watch('viagemData', ()=>this.carregarOcupadas());
        this.iniciarTempoReal();
        window.addEventListener('beforeunload', ()=>this.soltarTudoBeacon());
        this.addPax();
        this.mudarEmbarcacao();
        this.onRota();
        // Re-sincroniza o <select> da origem com o valor restaurado: as opções (x-for)
        // podem renderizar depois do x-model ligar, deixando o select em "—" mesmo com o
        // modelo correto. Limpa e re-aplica no próximo tick, quando as opções já existem.
        this.$nextTick(()=>{ const _o=this.origemSel; if(_o){ this.origemSel=''; this.$nextTick(()=>{ this.origemSel=_o; }); } });
        this.iniciarCaptura();
        if(window.FBRE_EDICAO){ this.edMode=true; this.edUrl=window.FBRE_EDICAO._url||''; this.edNumero=window.FBRE_EDICAO._numero||''; this.imprimir='nao';
          // _load SINCRONO: roda antes dos $nextTick de init (re-sync da origem) p/ nao ser sobrescrito pelo default.
          var ED=window.FBRE_EDICAO; this._load(ED);
          // Cascata transporte->origem->destino->viagem: origem/destino sao getters, entao o destino so
          // 'cola' depois que a origem esta estavel e as options renderizaram; e resolverLinha troca a data
          // de viagem pela 'proxima', entao restauramos a data real da passagem no fim.
          var _n=function(x){ return String(x||'').trim().toLowerCase(); };
          var _tent=0;
          var reaplicarEd=()=>{
            var dest=ED.destinoSel||'', via=ED.viagemData||'', rota=ED.rotaId||'';
            if(dest && _tent<20 && !(this.destinos||[]).some(c=>_n(c)===_n(dest))){ _tent++; return setTimeout(reaplicarEd, 40); }
            this.destinoSel=dest;
            this.$nextTick(()=>{
              try{ this.resolverLinha(); }catch(e){}
              if(!this.rotaId && rota) this.rotaId=String(rota);
              if(via){ this.viagemData=via; try{ this.viagemLabel=this.rotulo(via); }catch(e){} }
              try{ this.carregarOcupadas(); }catch(e){}
            });
          };
          this.$nextTick(()=>setTimeout(reaplicarEd, 0)); }
        else { this.aplicarPrefill(); }
        // Pagamento: o operador escolhe o meio; nada vem preenchido por padrao.
      },
      aplicarPrefill(){
        try{
          var pf=window.FBRE_PREFILL; if(!pf) return;
          var n=function(x){ return String(x||'').trim().toLowerCase(); };
          var acha=function(val,lista){ val=n(val); return (lista||[]).find(function(c){return n(c)===val;})||null; };
          this.$nextTick(()=>{
            if(pf.origem){ var o=acha(pf.origem,this.cidades); if(o) this.origemSel=o; }
            this.$nextTick(()=>{
              if(pf.destino){ var lista=(this.destinos&&this.destinos.length)?this.destinos:this.cidades; var d=acha(pf.destino,lista); if(d){ this.destinoSel=d; if(this.onRota) this.onRota(); } }
              if(pf.nome && this.passageiros && this.passageiros[0]) this.passageiros[0].nome=pf.nome;
            });
          });
        }catch(e){}
      },
      ajustarAvista(){ /* desativado: operador escolhe o meio de pagamento, nao preenche dinheiro automatico */ },
      metodosChips(){ const base=[{k:'dinheiro',l:'Dinheiro'},{k:'pix',l:'PIX'},{k:'debito',l:'Débito'},{k:'credito',l:'Crédito'},{k:'credito_parcelado',l:'Parcelado'}]; return this.tipoPag==='online' ? base.filter(m=>m.k!=='dinheiro') : base; },
      setTipo(t){ this.tipoPag=t; if(t==='online'){ this.metodosAtivos=this.metodosAtivos.filter(k=>k!=='dinheiro'); this.pag.dinheiro=null; if(!this.metodosAtivos.length) this.metodosAtivos=['pix']; } },
      restante(){ const soma=this.metodosAtivos.reduce((a,k)=>a+(Number(this.pag[k])||0),0); return Math.round(Math.max((Number(this.total)||0)-soma,0)*100)/100; },
      preencherRestante(k){ this.pag[k]=Math.round(((Number(this.pag[k])||0)+this.restante())*100)/100; },
      rotuloMetodo(k){ if(k==='credito') return this.credParcel ? ('Crédito '+(Number(this.parcelas)||1)+'x') : 'Crédito'; return ({dinheiro:'Dinheiro',pix:'PIX',debito:'Débito'})[k]||k; },
      metodoOn(k){ if(k==='credito') return this.metodosAtivos.includes('credito') && !this.credParcel; if(k==='credito_parcelado') return this.metodosAtivos.includes('credito') && this.credParcel; return this.metodosAtivos.includes(k); },
      toggleMetodo(k){
        const slot=(k==='credito'||k==='credito_parcelado')?'credito':k; const wantP=(k==='credito_parcelado');
        if(!this.dividir){ this.metodosAtivos=[slot]; this.credParcel=(slot==='credito')?wantP:false; if(slot==='credito' && !wantP) this.parcelas=1; this.pag={dinheiro:null,pix:null,debito:null,credito:null}; return; }
        if(slot==='credito'){ const on=this.metodosAtivos.includes('credito');
          if(on && this.credParcel===wantP){ this.metodosAtivos=this.metodosAtivos.filter(x=>x!=='credito'); this.pag.credito=null; this.credParcel=false; }
          else if(on){ this.credParcel=wantP; if(!wantP) this.parcelas=1; }
          else { this.metodosAtivos.push('credito'); this.credParcel=wantP; if(!wantP) this.parcelas=1; this.pag.credito=this.restante(); }
          return; }
        const i=this.metodosAtivos.indexOf(slot);
        if(i>=0){ this.metodosAtivos.splice(i,1); this.pag[slot]=null; } else { this.metodosAtivos.push(slot); this.pag[slot]=this.restante(); }
      },
      toggleDividir(){ this.dividir=!this.dividir; const k=this.metodosAtivos[0]||(this.tipoPag==='online'?'pix':'dinheiro');
        if(this.dividir){ this.pag={dinheiro:null,pix:null,debito:null,credito:null}; this.metodosAtivos=[k]; this.pag[k]=Math.round((Number(this.total)||0)*100)/100; }
        else { this.metodosAtivos=[k]; this.pag={dinheiro:null,pix:null,debito:null,credito:null}; } },

      // ---- passageiros ----
      _fileToDataUrl(file){ return new Promise((res,rej)=>{ const r=new FileReader(); r.onload=()=>res(r.result); r.onerror=rej; r.readAsDataURL(file); }); },
      async _lerArquivo(fl){ const isImg=(fl.type||'').startsWith('image/'); let d=null; try{ d= isImg ? await this._imgToDataUrl(fl) : await this._fileToDataUrl(fl); }catch(e){ d=null; } if(!d) return null; return {d:d, n:(fl.name||'arquivo'), t:(fl.type || (isImg?'image/jpeg':'application/octet-stream'))}; },
      anexarDocs(i, ev){ const files=Array.from(ev.target.files||[]); const arr=(this.passageiros[i].documentos=this.passageiros[i].documentos||[]); const run=async()=>{ for(const fl of files){ if(arr.length>=20) break; const o=await this._lerArquivo(fl); if(o) arr.push(o); } }; run(); ev.target.value=''; },
      removerDoc(i,j){ this.passageiros[i].documentos.splice(j,1); },
      anexarGerais(ev){ const files=Array.from(ev.target.files||[]); const run=async()=>{ for(const fl of files){ if(this.documentosGerais.length>=20) break; const o=await this._lerArquivo(fl); if(o) this.documentosGerais.push(o); } }; run(); ev.target.value=''; },
      docSrc(d){ if(d && typeof d==='object'){ return d.d || (d.u ? '/'+d.u : ''); } return d ? '/'+d : ''; },
      docNome(d){ if(d && typeof d==='object'){ return d.n || (d.u? String(d.u).split('/').pop() : 'arquivo'); } return String(d||'').split('/').pop(); },
      ehImgDoc(d){ const o=(d&&typeof d==='object'); const t=o?(d.t||''):''; const u=o?(d.u||d.d||''):String(d||''); if(String(t).startsWith('image/'))return true; if(String(u).startsWith('data:image/'))return true; return /\.(jpe?g|png|webp|gif|bmp|heic|heif)(\?|$)/i.test(String(u)); },
      iconeDoc(d){ if(this.ehImgDoc(d))return'🖼️'; const o=(d&&typeof d==='object'); const s=(o?((d.t||'')+' '+(d.n||d.u||'')):String(d||'')).toLowerCase(); if(s.includes('pdf'))return'📄'; if(/word|\.docx?/.test(s))return'📝'; if(/sheet|excel|\.xlsx?|csv/.test(s))return'📊'; return'📎'; },
      abrirDoc(d){ const src=this.docSrc(d); if(!src)return; const w=window.open('','_blank'); if(!w){ try{ window.open(src,'_blank'); }catch(e){} return; } const nome=this.docNome(d); if(this.ehImgDoc(d)){ w.document.write('<title>'+nome+'</title><body style="margin:0;background:#111"><img src="'+src+'" style="max-width:100%;display:block;margin:auto"></body>'); } else { w.document.write('<title>'+nome+'</title><body style="margin:0"><iframe src="'+src+'" style="border:0;position:fixed;inset:0;width:100%;height:100%"></iframe></body>'); } w.document.close(); },
      _imgToDataUrl(file){ return new Promise((res,rej)=>{ const img=new Image(); const u=URL.createObjectURL(file); img.onload=()=>{ URL.revokeObjectURL(u); const max=1280; let w=img.width,h=img.height; if(w>max||h>max){ const r=Math.min(max/w,max/h); w=Math.round(w*r); h=Math.round(h*r); } const c=document.createElement('canvas'); c.width=w; c.height=h; c.getContext('2d').drawImage(img,0,0,w,h); try{ res(c.toDataURL('image/jpeg',0.8)); }catch(e){ rej(e); } }; img.onerror=rej; img.src=u; }); },
      novoPax(){ const t=this.tipos[0]||{id:this.normalId,nome:'Adulto',percentual:100,paga_taxa:true,modo:'valor',eh_normal:true}; return {id:Date.now()+Math.random(), nome:'', tipo_id:t.id, tipo:t.nome, percentual:t.percentual, paga_taxa:!!t.paga_taxa, modo:t.modo||'valor', documento:'', doc_tipo:'', poltrona:'', sexo:'', telefone:'', nacionalidade:'Brasileira', nascimento:'', nasc_br:'', foto_rg:'', foto:'', documentos:[], checkin:!!this.checkinAuto, temColo:false, bebeColo:{nome:'',documento:'',nascimento:'',nasc_br:''}, _mais:false, valor_passagem:0, valor_taxa:0, valor_alimentacao:0, valor_integral:0}; },
      addPax(){ const p=this.novoPax(); this.aplicarTipo(p); this.passageiros.push(p); if(this.usaPoltrona){ this.$nextTick(()=>this.abrirMapa(this.passageiros.length-1)); } },
      paxTotal(p){ return Math.round(((Number(p.valor_passagem)||0)+(Number(p.valor_taxa)||0)+(Number(p.valor_alimentacao)||0))*100)/100; },
      setPaxTotal(p,v){ const total=Math.max(Number(v)||0,0); const fixo=(Number(p.valor_taxa)||0)+(Number(p.valor_alimentacao)||0); p.valor_passagem=Math.round(Math.max(total-fixo,0)*100)/100; },
      // ---- captura por foto (QR -> celular -> IA -> autofill) ----
      async iniciarCaptura(){
        try{
          const r=await fetch(@js(route('captura.novo')),{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}});
          const j=await r.json(); this.capturaSid=j.sid; this.capturaUrl=j.url;
          try{ const d=parseInt(localStorage.getItem('fbre_captura_desde_'+this.capturaSid)||'0',10); this.capturaDesde=isNaN(d)?0:d; }catch(e){}
          this.iniciarPoll();
          return true;
        }catch(e){ return false; }
      },
      async abrirCaptura(){
        this.capturaAberta=true;
        if(!this.capturaUrl){ this.capturaStatus='Gerando QR...'; await this.iniciarCaptura(); }
        this.capturaStatus = this.capturaUrl ? 'Aponte a câmera do celular pro QR. O link é fixo: dá pra salvar e usar sempre.' : 'Não consegui gerar o QR. Tente de novo.';
        this.$nextTick(()=>this.renderQr());
      },
      renderQr(){ const el=document.getElementById('qr-captura'); if(!el||!window.QRCode||!this.capturaUrl) return; el.innerHTML=''; new QRCode(el,{text:this.capturaUrl,width:220,height:220,correctLevel:QRCode.CorrectLevel.M}); },
      iniciarPoll(){ this.pararPoll(); this.capturaTimer=setInterval(()=>this.pollCaptura(),2500); },
      pararPoll(){ if(this.capturaTimer){ clearInterval(this.capturaTimer); this.capturaTimer=null; } },
      fecharCaptura(){ this.capturaAberta=false; }, // segue recebendo em segundo plano
      async pollCaptura(){
        if(!this.capturaSid) return;
        try{
          const r=await fetch(@js(route('captura.pendentes'))+'?s='+encodeURIComponent(this.capturaSid)+'&desde='+this.capturaDesde,{headers:{Accept:'application/json'}});
          const j=await r.json();
          (j.itens||[]).forEach(it=>{
            this.aplicarCaptura(it);
            this.capturaDesde=Math.max(this.capturaDesde, it.id);
            try{ localStorage.setItem('fbre_captura_desde_'+this.capturaSid, this.capturaDesde); }catch(e){}
            fetch('/passagens/captura/'+it.id+'/usar',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf}});
          });
        }catch(e){}
      },
      aplicarCaptura(it){
        let idx=this.passageiros.findIndex(p=>!(p.nome||'').trim());
        if(idx<0){ const np=this.novoPax(); this.aplicarTipo(np); this.passageiros.push(np); idx=this.passageiros.length-1; }
        const p=this.passageiros[idx];
        if(it.nome) p.nome=it.nome;
        if(it.nascimento){ p.nascimento=it.nascimento; p.nasc_br=this.isoToBr(it.nascimento); }
        if(it.sexo) p.sexo=it.sexo;
        if(it.nacionalidade) p.nacionalidade=it.nacionalidade;
        if(it.documento) p.documento=it.documento;
        if(it.foto_rg) p.foto_rg=it.foto_rg;
        if(it.foto) p.foto=it.foto;
        p._mais=true;
        if(it.origem){ this.origemSel=it.origem; }
        if(it.destino){ this.destinoSel=it.destino; }
        if(it.origem||it.destino){ this.onRota(); }
        this.aplicarIdade(p);
        this.capturaStatus='✅ '+(it.nome||'Passageiro')+' recebido! Pode enviar o próximo.';
        this.capturaAviso='📷 '+(it.nome||'Passageiro')+' recebido da foto'+(it.nome?'':' (preencha o nome)');
        clearTimeout(this._avisoT); this._avisoT=setTimeout(()=>this.capturaAviso='',5000);
        if(this.capturaAberta) this.fecharCaptura(); // chegou captura: fecha o QR (segue recebendo em 2o plano)
      },
      // ---- webcam / arquivo no proprio desktop ----
      async abrirCam(i){ this.camTargetIdx=i; this.camAberta=true; this.camStatus='';
        try{ this.camFacing=localStorage.getItem('fbre_cam_facing')||'environment'; }catch(e){}
        await this.ligarCamera(); },
      async ligarCamera(){ this.pararCamera();
        try{ this.camStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:this.camFacing}, audio:false});
          this.$nextTick(()=>{ const v=document.getElementById('cam-video'); if(v){ v.srcObject=this.camStream; v.play().catch(()=>{}).finally(()=>{ this.pixChecando=false; }); } });
        }catch(e){ this.camStatus='Não consegui abrir a câmera. Use "Selecionar arquivo". ('+e.message+')'; } },
      trocarCamera(){ this.camFacing=this.camFacing==='environment'?'user':'environment'; try{ localStorage.setItem('fbre_cam_facing',this.camFacing);}catch(e){} this.ligarCamera(); },
      pararCamera(){ if(this.camStream){ this.camStream.getTracks().forEach(t=>t.stop()); this.camStream=null; } },
      fecharCam(){ this.pararCamera(); this.camAberta=false; },
      redimImg(dataUrl){ return new Promise(res=>{ const img=new Image(); img.onload=()=>{ const max=1600; let w=img.width,h=img.height; if(w>max||h>max){const k=Math.min(max/w,max/h); w=Math.round(w*k); h=Math.round(h*k);} const c=document.createElement('canvas'); c.width=w; c.height=h; c.getContext('2d').drawImage(img,0,0,w,h); res(c.toDataURL('image/jpeg',0.82)); }; img.onerror=()=>res(dataUrl); img.src=dataUrl; }); },
      async capturarCam(){ const v=document.getElementById('cam-video'); if(!v||!v.videoWidth){ this.camStatus='Câmera não pronta.'; return; }
        const max=1600; let w=v.videoWidth,h=v.videoHeight; if(w>max||h>max){const k=Math.min(max/w,max/h); w=Math.round(w*k); h=Math.round(h*k);}
        const c=document.createElement('canvas'); c.width=w; c.height=h; c.getContext('2d').drawImage(v,0,0,w,h);
        await this.enviarOcr(c.toDataURL('image/jpeg',0.82)); },
      selecionarArquivo(ev){ const f=ev.target.files&&ev.target.files[0]; if(!f) return; const r=new FileReader(); r.onload=()=>this.redimImg(r.result).then(d=>this.enviarOcr(d)); r.readAsDataURL(f); ev.target.value=''; },
      async enviarOcr(dataUrl){ this.camLendo=true; this.camStatus='Lendo documento...';
        try{ const r=await fetch(@js(route('captura.ocr')),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf},body:JSON.stringify({foto:dataUrl})});
          const j=await r.json(); this.preencherPax(this.camTargetIdx, j);
          if(j.ok){ this.camStatus='✅ Lido!'; this.fecharCam(); } else { this.camStatus='Foto anexada. Leitura falhou: '+(j.erro||'preencha manualmente'); }
        }catch(e){ this.camStatus='Erro: '+e.message; }
        this.camLendo=false; },
      preencherPax(idx, j){ const p=this.passageiros[idx]; if(!p) return;
        if(j.nome) p.nome=j.nome; if(j.nascimento){ p.nascimento=j.nascimento; p.nasc_br=this.isoToBr(j.nascimento); } if(j.sexo) p.sexo=j.sexo;
        if(j.nacionalidade) p.nacionalidade=j.nacionalidade; if(j.documento) p.documento=j.documento;
        if(j.foto) p.foto=j.foto; if(j.foto_rg) p.foto_rg=j.foto_rg; p._mais=true; this.aplicarIdade(p); },
      rmPax(i){ const pol=this.passageiros[i]?.poltrona; this.passageiros.splice(i,1); if(pol && !this.passageiros.some(p=>String(p.poltrona)===String(pol))) this.soltar([pol]); },
      aplicarTipo(p){
        const t=this.tipos.find(x=>String(x.id)===String(p.tipo_id)) || this.tipos[0];
        if(t){ p.tipo_id=t.id; p.tipo=t.nome; p.percentual=t.percentual; p.paga_taxa=!!t.paga_taxa; p.modo=t.modo||'valor'; }
        const pr=this.precoDoTipo(t);
        const _tar=Number(pr.tarifa)||0, _alim=this.usaAlim?(Number(pr.alim)||0):0, _integ=Number(pr.integral)||0;
        // regra: "com desconto" vazio => cobra o INTEGRAL (preco normal); com desconto preenchido => cobra o com desconto
        if(t && (t.modo==='manual')){ p.valor_passagem=0; p.valor_alimentacao=0; }
        else if(_tar>0){ p.valor_passagem=_tar; p.valor_alimentacao=_alim; }
        else { p.valor_passagem=_integ; p.valor_alimentacao=0; }
        p.valor_taxa = (this.usaEmbarque && p.paga_taxa) ? this.taxaDoTrecho() : 0;
        p.valor_integral = Math.max(_integ, (Number(p.valor_passagem)||0)+(Number(p.valor_alimentacao)||0)+(Number(p.valor_taxa)||0));
      },
      // ---- tipo automatico pela idade (data de nascimento) ----
      idadeAnos(nasc){ if(!nasc) return null; const d=new Date(nasc); if(isNaN(d.getTime())) return null; const h=new Date(); let a=h.getFullYear()-d.getFullYear(); const m=h.getMonth()-d.getMonth(); if(m<0||(m===0&&h.getDate()<d.getDate())) a--; return a>=0?a:null; },
      bandasAtivas(){ const emb=this.embarcacaoSel?String(this.embarcacaoSel):''; const ov=this.regrasIdade&&this.regrasIdade[emb]; if(ov&&ov.length) return ov;
        const has=v=>v!==null&&v!==undefined&&v!==''; return this.tipos.filter(t=>has(t.idade_min)||has(t.idade_max)).map(t=>({tipo_id:t.id,min:t.idade_min,max:t.idade_max})); },
      tipoPorIdade(nasc){ const a=this.idadeAnos(nasc); if(a===null) return null; let melhor=null, span=1e9;
        this.bandasAtivas().forEach(b=>{ const mn=(b.min===null||b.min===undefined||b.min==='')?null:Number(b.min); const mx=(b.max===null||b.max===undefined||b.max==='')?null:Number(b.max); if(mn===null&&mx===null) return; if((mn===null||a>=mn)&&(mx===null||a<=mx)){ const s=(mx===null?120:mx)-(mn===null?0:mn); if(s<span){ span=s; melhor=this.tipos.find(t=>String(t.id)===String(b.tipo_id)); } } });
        return melhor||null; },
      idadeTxt(p){ const a=this.idadeAnos(p.nascimento); if(a===null) return ''; const t=this.tipos.find(x=>String(x.id)===String(p.tipo_id)); return a+(a===1?' ano':' anos')+(t?(' · '+t.nome):''); },
      carregarGratuidade(){ if(this._restaurando) return; if(!this.origemSel||!this.destinoSel){ this.gratInfo=null; return; }
        try{ const u=new URL('/passagens/gratuidade', location.origin); u.searchParams.set('origem', this.origemSel); u.searchParams.set('destino', this.destinoSel);
          if(this.embarcacaoSel) u.searchParams.set('embarcacao_id', this.embarcacaoSel); if(this.rotaId) u.searchParams.set('rota_id', this.rotaId); if(this.viagemData) u.searchParams.set('data_viagem', this.viagemData);
          const tok=++this._gratTok; fetch(u,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.ok?r.json():null).then(j=>{ if(tok!==this._gratTok) return; this.gratInfo=(j&&j.idoso&&j.idoso.ativo)?j.idoso:null; this.pcdInfo=(j&&j.pcd&&j.pcd.ativo)?j.pcd:null; this.idosoMeiaInfo=null; }).catch(()=>{}); }catch(e){} },
      isoToBr(iso){ if(!iso) return ''; const a=(''+iso).slice(0,10).split('-'); return a.length===3 ? a[2]+'/'+a[1]+'/'+a[0] : ''; },
      mascaraNasc(p){ let v=(p.nasc_br||'').replace(/[^0-9]/g,'').slice(0,8); let o=v; if(v.length>4) o=v.slice(0,2)+'/'+v.slice(2,4)+'/'+v.slice(4); else if(v.length>2) o=v.slice(0,2)+'/'+v.slice(2); p.nasc_br=o; if(v.length===8){ const d=+v.slice(0,2), m=+v.slice(2,4), a=+v.slice(4,8); if(d>=1&&d<=31&&m>=1&&m<=12&&a>=1900&&a<=2100){ p.nascimento=v.slice(4,8)+'-'+v.slice(2,4)+'-'+v.slice(0,2); this.aplicarIdade(p); return; } } p.nascimento=''; },
      mascaraNascColo(p){ let v=(p.bebeColo.nasc_br||'').replace(/[^0-9]/g,'').slice(0,8); let o=v; if(v.length>4) o=v.slice(0,2)+'/'+v.slice(2,4)+'/'+v.slice(4); else if(v.length>2) o=v.slice(0,2)+'/'+v.slice(2); p.bebeColo.nasc_br=o; if(v.length===8){ const d=+v.slice(0,2), m=+v.slice(2,4), a=+v.slice(4,8); if(d>=1&&d<=31&&m>=1&&m<=12&&a>=1900&&a<=2100){ p.bebeColo.nascimento=v.slice(4,8)+'-'+v.slice(2,4)+'-'+v.slice(0,2); return; } } p.bebeColo.nascimento=''; },
      aplicarIdade(p){ const t=this.tipoPorIdade(p.nascimento); if(t){ p.tipo_id=t.id; this.aplicarTipo(p); } },
      trechoGet(tipoId){ const o=(this.origemSel||'').trim().toLowerCase(), d=(this.destinoSel||'').trim().toLowerCase();
        return this.trechoMap[o+'|'+d+'|'+tipoId] || this.trechoMap[d+'|'+o+'|'+tipoId] || null; },
      precoDoTipo(t){
        if(!t) return {tarifa:0,alim:0,integral:0};
        // override salvo para este trecho+tipo (manual) tem prioridade
        const c=this.trechoGet(t.id);
        if(c){ const tt=Number(c.tarifa)||0, aa=this.usaAlim?Number(c.alim)||0:0; const ii=Math.max(Number(c.integral)||0, tt+aa); return {tarifa:tt, alim:aa, integral:ii}; }
        // porcentagem automatica: calcula a partir do Normal
        if((t.modo||'valor')==='porcentagem' && !t.eh_normal){
          const n=this.tipos.find(x=>String(x.id)===String(this.normalId));
          const nc=n?this.trechoGet(n.id):null;
          const baseT=nc?Number(nc.tarifa)||0:(n?Number(n.tarifa_padrao)||0:0);
          const baseA=nc?Number(nc.alim)||0:(n?Number(n.alimentacao_padrao)||0:0);
          const pct=(Number(t.percentual)||0)/100;
          const tarifa=Math.round(baseT*pct*100)/100;
          const alim=this.usaAlim ? ((t.pct_sobre==='tudo') ? Math.round(baseA*pct*100)/100 : Math.round(baseA*100)/100) : 0;
          const integral=this.integralNormal();
          return {tarifa:tarifa, alim:alim, integral:Math.max(integral, tarifa+alim)};
        }
        // valor sem linha: usa padrao do tipo
        const tt=Number(t.tarifa_padrao)||0, aa=this.usaAlim?Number(t.alimentacao_padrao)||0:0;
        return {tarifa: tt, alim: aa, integral: tt+aa};
      },
      taxaDoTrecho(){ const o=(this.origemSel||'').trim().toLowerCase(), d=(this.destinoSel||'').trim().toLowerCase();
        return Number(this.taxaMap[o+'|'+d] ?? this.taxaMap[d+'|'+o] ?? 0)||0; },
      get baseNormal(){ const n=this.tipos.find(x=>String(x.id)===String(this.normalId)); const c=n?this.trechoGet(n.id):null; return c?Number(c.tarifa)||0:(n?Number(n.tarifa_padrao)||0:0); },
      integralNormal(){ const n=this.tipos.find(x=>String(x.id)===String(this.normalId)); if(!n) return 0; const pr=this.precoDoTipo(n); return Math.round(((Number(pr.tarifa)||0)+(this.usaAlim?(Number(pr.alim)||0):0))*100)/100; },

      // ---- preço pela tabela (origem/destino) ----
      aplicarPreco(){
        this.passageiros.forEach(p=>this.aplicarTipo(p));
        this.precoInfo = this.baseNormal>0 || this.passageiros.some(p=>Number(p.valor_passagem)>0);
      },
      onRota(){ const n=x=>String(x||'').trim().toLowerCase(); if(this.destinoSel && !this.destinos.some(c=>n(c)===n(this.destinoSel))) this.destinoSel=''; this.resolverLinha(); this.aplicarPreco(); if(this.usaPoltrona) this.carregarOcupadas(); },
      teclaAtalho(e){
        const k=e.key; if(k!=='F1'&&k!=='F2'&&k!=='F3') return; e.preventDefault();
        if(k==='F1'){ const el=this.$refs.fDestino; if(el){ el.focus(); try{ el.showPicker&&el.showPicker(); }catch(_){} } }
        else if(k==='F2'){ if(this.usaPoltrona){ let i=this.passageiros.findIndex(p=>!p.poltrona); if(i<0)i=0; this.abrirMapa(i); } else { const el=this.$refs.fForma&&this.$refs.fForma.querySelector('button,input'); if(el) el.focus(); } }
        else if(k==='F3'){ const w=this.$refs.fForma; if(w){ const r=w.querySelector('button,input'); if(r) r.focus(); w.scrollIntoView({block:'center',behavior:'smooth'}); } }
      },

      // ---- viagem (igual encomenda) ----
      isoLocal(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
      fmtBr(d){ return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear(); },
      get hojeISO(){ return this.isoLocal(new Date()); },
      proximaViagem(dia){ const d=new Date(); d.setHours(0,0,0,0); while(d.getDay()!==dia) d.setDate(d.getDate()+1); return this.isoLocal(d); },
      rotulo(iso){ if(!iso) return ''; const [y,m,day]=iso.split('-').map(Number); const d=new Date(y,m-1,day); const tag=iso<this.hojeISO?' · anterior':(iso===this.hojeISO?' · hoje':''); return this.diasCurto[d.getDay()]+' '+this.fmtBr(d)+(this.viagemHora?' '+this.viagemHora:'')+tag; },
      recalcularDatas(){
        const _n=x=>String(x||'').trim().toLowerCase();
        let p=this.paradasInfo.find(x=>_n(x.n)===_n(this.origemSel))||this.paradasInfo[0];
        if(!p || p.d===null||p.d===undefined||p.d===''){ this.viagemDia=null; this.viagemData=''; this.viagemLabel=''; return; }
        this.viagemDia=Number(p.d); this.viagemHora=p.h||'';
        this.viagemData=this.proximaViagem(this.viagemDia);
        this.$nextTick(()=>{ this.viagemLabel=this.rotulo(this.viagemData); });
      },
      abrirCal(){ if(this.viagemDia===null) return; const base=this.viagemData||this.hojeISO; const [y,m]=base.split('-').map(Number); this.calMes=new Date(y,m-1,1); this.calAberto=true; },
      mudarMes(n){ const d=new Date(this.calMes); d.setMonth(d.getMonth()+n); this.calMes=d; },
      get nomeMes(){ return this.calMes ? this.calMes.toLocaleDateString('pt-BR',{month:'long',year:'numeric'}) : ''; },
      embNome(id){ const e=(this.embarcacoesData||[]).find(x=>String(x.id)===String(id)); return e && e.nome ? e.nome : 'Outra lancha'; },
      get mapaDias(){ // dia-da-semana -> [{embId, embNome, hora}] de TODAS as lanchas que saem da origem atual
        const map={}; if(!this.origemSel) return map;
        const _n=x=>String(x||'').trim().toLowerCase();
        for(const L of this.linhas){ const ps=L.paradas||[]; const idx=ps.findIndex(x=>_n(x.n)===_n(this.origemSel));
          if(idx<0 || idx>=ps.length-1) continue; const p=ps[idx]; if(p.d===null||p.d===undefined||p.d==='') continue;
          const embId=String(L.embarcacao_id||''); if(!embId) continue; const w=Number(p.d);
          if(!map[w]) map[w]=[]; if(!map[w].some(o=>o.embId===embId)) map[w].push({embId, embNome:this.embNome(embId), hora:p.h||''}); }
        return map;
      },
      get calDias(){
        if(!this.calMes) return [];
        const ano=this.calMes.getFullYear(), mes=this.calMes.getMonth();
        const ini=new Date(ano,mes,1); ini.setDate(1-ini.getDay());
        const md=this.mapaDias; const cur=this.embarcacaoSel?String(this.embarcacaoSel):''; const out=[];
        for(let i=0;i<42;i++){ const d=new Date(ini); d.setDate(ini.getDate()+i); const iso=this.isoLocal(d); const outroMes=d.getMonth()!==mes; const w=d.getDay();
          const dataOk=(this.podeRetroativo || iso>=this.hojeISO);
          const viagens=(!outroMes && md[w]) ? md[w] : [];
          const temAtual=viagens.some(o=>o.embId===cur);
          const valido=viagens.length>0 && dataOk;
          out.push({iso,dia:d.getDate(),valido,viagens,temAtual,outroMes,sel:iso===this.viagemData}); }
        return out;
      },
      clicarDia(c){ if(!c.valido) return; if(c.viagens.length===1){ this.escolherDiaEmb(c.iso, c.viagens[0].embId); } else { this.calEscolha={ iso:c.iso, opcoes:c.viagens }; } },
      escolherDiaEmb(iso, embId){ const cur=this.embarcacaoSel?String(this.embarcacaoSel):''; this.calEscolha=null; if(String(embId)===cur){ this.escolherDia(iso); } else { this.escolherDiaOutra(iso, embId); } },
      escolherDia(iso){ this.viagemData=iso; this.viagemLabel=this.rotulo(iso); this.calAberto=false; },
      escolherDiaOutra(iso, embId){ if(!embId) return; this.embarcacaoSel=String(embId); this.$nextTick(()=>{ this.viagemData=iso; this.viagemLabel=this.rotulo(iso); this.calAberto=false; }); },
      get linhasTransporte(){
        const emb=this.embarcacaoSel?String(this.embarcacaoSel):'';
        if(!emb) return [];
        return this.linhas.filter(L=>String(L.embarcacao_id||'')===emb);
      },
      get origens(){
        const Ls=this.linhasTransporte;
        if(!Ls.length) return this.cidades;
        const set=new Set(); Ls.forEach(L=>L.paradas.slice(0,-1).forEach(p=>set.add(p.n)));
        return [...set];
      },
      get destinos(){
        const Ls=this.linhasTransporte;
        const n=x=>String(x||'').trim().toLowerCase();
        if(!Ls.length) return this.cidades.filter(c=>n(c)!==n(this.origemSel));
        if(!this.origemSel){ const s=new Set(); Ls.forEach(L=>L.paradas.forEach(p=>s.add(p.n))); return [...s]; }
        const set=new Set();
        Ls.forEach(L=>{ const io=L.paradas.findIndex(p=>n(p.n)===n(this.origemSel)); if(io>=0) L.paradas.slice(io+1).forEach(p=>set.add(p.n)); });
        return [...set];
      },
      resolverLinha(){
        this.rotaId=''; this.linhaNome=''; this.paradasInfo=[];
        if(!this.origemSel){ this.viagemDia=null; this.viagemData=''; this.viagemLabel=''; return; }
        const norm=x=>String(x||'').trim().toLowerCase();
        const idx=(L,c)=>L.paradas.findIndex(p=>norm(p.n)===norm(c));
        const pick=arr =>
             arr.find(L=>{ const io=idx(L,this.origemSel), id=idx(L,this.destinoSel); return io>=0 && this.destinoSel && id>=0 && io<id; })
          || arr.find(L=>idx(L,this.origemSel)>=0 && (!this.destinoSel || idx(L,this.destinoSel)>=0))
          || arr.find(L=>idx(L,this.origemSel)>=0);
        const emb=this.embarcacaoSel?String(this.embarcacaoSel):'';
        let linha = emb ? pick(this.linhas.filter(L=>String(L.embarcacao_id||'')===emb)) : null;
        if(!linha) linha = pick(this.linhas);
        if(!linha){ this.viagemDia=null; this.viagemData=''; this.viagemLabel=''; return; }
        this.rotaId=String(linha.id); this.linhaNome=linha.nome; this.paradasInfo=linha.paradas;
        this.recalcularDatas();
      },

      // ---- poltrona ----
      mudarEmbarcacao(){
        const e=this.embarcacoesData.find(x=>x.id===String(this.embarcacaoSel));
        this.usaPoltrona=!!(e&&e.poltrona); this.qtdPoltronas=e?e.qtd:0; this.colunasPoltrona=e&&e.colunas?e.colunas:4;
        this.poltronasLayout=(e&&e.layout)?e.layout:''; this.temLayout=this.poltronasLayout.trim().length>0;
        if(this.usaPoltrona) this.carregarOcupadas(); else this.passageiros.forEach(p=>p.poltrona='');
      },
      async carregarOcupadas(){
        if(!this.usaPoltrona || !this.embarcacaoSel || !navigator.onLine) return;
        try{ const u=new URL('/passagens/assentos',location.origin); u.searchParams.set('embarcacao_id',this.embarcacaoSel);
          if(this.viagemData)u.searchParams.set('data_viagem',this.viagemData);
          if(this.rotaId)u.searchParams.set('rota_id',this.rotaId);
          if(this.origemSel)u.searchParams.set('origem',this.origemSel);
          if(this.destinoSel)u.searchParams.set('destino',this.destinoSel);
          const r=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin'}); const j=await r.json();
          this.ocupadas=(j.ocupadas||[]).map(String); this.reservadas=(j.reservadas||[]).map(String); this.reservadasInfo=j.reservadasInfo||{}; this.travadas=(j.travadas||[]); if(j.colunas)this.colunasPoltrona=j.colunas; if(j.qtd)this.qtdPoltronas=j.qtd; if(j.layout!==undefined){ this.poltronasLayout=j.layout||''; this.temLayout=(j.layout||'').trim().length>0; }
        }catch(e){ this.ocupadas=[]; }
      },
      abrirMapa(i){ this.paxAtual=i; this.mapaAberto=true; this.carregarOcupadas(); },
      estaOcupada(n){ return this.ocupadas.includes(String(n)); },
      estaTravada(n){ return (this.travadas||[]).some(t=>String(t.poltrona)===String(n)); },
      motivoTravada(n){ const t=(this.travadas||[]).find(t=>String(t.poltrona)===String(n)); if(!t) return ''; return '🔒 Travada'+(t.motivo?(': '+t.motivo):'')+(t.todas?' · todas as viagens':' · esta viagem'); },
      nomeReserva(n){ return (this.reservadasInfo||{})[String(n)] || ''; },
      tituloAssento(n){ const r=this.nomeReserva(n); if(r && !this.poltronaMinha(n)) return '🟡 Reservando agora: '+r; return this.motivoTravada(n); },
      get listaReservas(){ const o=this.reservadasInfo||{}; return Object.keys(o).map(k=>({pol:k, nome:o[k]})); },
      estaReservada(n){ return this.reservadas.includes(String(n)); },
      poltronaMinha(n){ return this.passageiros.some(p=>String(p.poltrona)===String(n)); },
      assentoClasse(n){ const minha=String(this.passageiros[this.paxAtual]?.poltrona)===String(n); if(minha) return 'text-white'; if(this.estaTravada(n)) return 'bg-rose-200 text-rose-800 border-rose-400 opacity-90'; if(this.estaOcupada(n)||this.poltronaMinha(n)) return 'bg-red-100 text-red-700 border-red-200 opacity-70'; if(this.estaReservada(n)) return 'bg-amber-100 text-amber-700 border-amber-300 opacity-80'; return 'bg-slate-50 hover:bg-slate-100 border-slate-200'; },
      async escolherPoltrona(n){ n=String(n); if((this.estaOcupada(n)||this.estaReservada(n))&&!this.poltronaMinha(n))return; const i=this.paxAtual; const ant=this.passageiros[i].poltrona; const ok=await this.reservar(n); if(!ok){ alert('Essa poltrona acabou de ser escolhida por outro operador. Escolha outra.'); this.carregarOcupadas(); return; } if(ant && String(ant)!==n && !this.passageiros.some((p,k)=>k!==i&&String(p.poltrona)===String(ant))) this.soltar([ant]); this.passageiros[i].poltrona=n; this.mapaAberto=false; this.$nextTick(()=>{ const el=document.getElementById('pax-nome-'+i); if(el){ el.focus(); el.select && el.select(); } }); },
      _holdBody(extra){ const b={embarcacao_id:this.embarcacaoSel||'', rota_id:this.rotaId||'', data_viagem:this.viagemData||'', origem:this.origemSel||'', destino:this.destinoSel||''}; return Object.assign(b, extra||{}); },
      async reservar(n){ try{ const r=await fetch('/passagens/poltrona-hold',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(this._holdBody({action:'hold',poltronas:[String(n)]}))}); const j=await r.json(); if(j&&j.ocupadas)this.ocupadas=j.ocupadas.map(String); if(j&&j.reservadas)this.reservadas=j.reservadas.map(String); if(j&&j.reservadasInfo)this.reservadasInfo=j.reservadasInfo; if(j&&j.travadas)this.travadas=j.travadas; return j?!!j.ok:false; }catch(e){ return true; } },
      soltar(seats){ const s=(seats||[]).map(String).filter(Boolean); if(!s.length)return; try{ fetch('/passagens/poltrona-hold',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf},credentials:'same-origin',body:JSON.stringify(this._holdBody({action:'release',poltronas:s}))}); }catch(e){} },
      soltarTudoBeacon(){ try{ const seats=this.passageiros.map(p=>p.poltrona).filter(Boolean).map(String); if(!seats.length||!navigator.sendBeacon)return; const fd=new FormData(); fd.append('_token',FBRE.csrf); fd.append('action','release'); fd.append('embarcacao_id',this.embarcacaoSel||''); if(this.rotaId)fd.append('rota_id',this.rotaId); if(this.viagemData)fd.append('data_viagem',this.viagemData); seats.forEach(x=>fd.append('poltronas[]',x)); navigator.sendBeacon('/passagens/poltrona-hold', fd); }catch(e){} },
      iniciarTempoReal(){ if(this._rtTimer)return; this._rtTimer=setInterval(()=>this.tickTempoReal(), 3000); },
      async tickTempoReal(){ if(!this.usaPoltrona||!this.embarcacaoSel||!navigator.onLine)return; const seats=this.passageiros.map(p=>p.poltrona).filter(Boolean).map(String); if(!this.mapaAberto && seats.length===0)return; if(seats.length){ try{ const r=await fetch('/passagens/poltrona-hold',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(this._holdBody({action:'heartbeat',poltronas:seats}))}); const j=await r.json(); if(j&&j.ocupadas)this.ocupadas=j.ocupadas.map(String); if(j&&j.reservadas)this.reservadas=j.reservadas.map(String); if(j&&j.reservadasInfo)this.reservadasInfo=j.reservadasInfo; if(j&&j.travadas)this.travadas=j.travadas; return; }catch(e){} } this.carregarOcupadas(); },

      // mapa no formato da lancha: fileiras com corredor no meio
      get fileiras(){
        const col=this.colunasPoltrona||4, qtd=this.qtdPoltronas||0, meio=Math.ceil(col/2);
        const rows=[]; let n=1;
        while(n<=qtd){ const r=[]; for(let c=0;c<col && n<=qtd;c++){ r.push(n++); } rows.push({esq:r.slice(0,meio), dir:r.slice(meio)}); }
        return rows;
      },
      // mapa por NOME: cada bloco = fileiras; "|" separa esquerda/direita; linha vazia separa blocos
      get blocosPoltrona(){
        const txt=(this.poltronasLayout||'').replace(/\r/g,'');
        return txt.split(/\n[ \t]*\n/).map(b=>
          b.split('\n').filter(l=>l.trim()).map(linha=>{
            const cols=linha.split('|').map(x=>x.trim().split(/\s+/).filter(Boolean)).filter(c=>c.length);
            return { cols, esq:cols[0]||[], dir:cols.slice(1).flat() };
          })
        ).filter(b=>b.length);
      },

      // contagem de poltronas livres (total + por tipo), pra mostrar no topo do mapa
      get contPoltronas(){
        const occ=new Set((this.ocupadas||[]).map(String));
        const res=new Set((this.reservadas||[]).map(String));
        const trv=new Set((this.travadas||[]).map(t=>String(t.poltrona)));
        const c={total:0,livre:0,janela:0,meio:0,corredor:0};
        const classeLinha=(cols)=>{ const out=[]; const nc=cols.length; cols.forEach((seats,ci)=>{ const n=seats.length; seats.forEach((s,i)=>{ let t='meio'; const pontaEsq=(i===0), pontaDir=(i===n-1); if(ci===0 && pontaEsq) t='janela'; else if(ci===nc-1 && pontaDir) t='janela'; else if((pontaDir && ci<nc-1)||(pontaEsq && ci>0)) t='corredor'; if(n===1 && (ci===0||ci===nc-1)) t='janela'; out.push({s:String(s),t}); }); }); return out; };
        (this.blocosPoltrona||[]).forEach(bloco=>bloco.forEach(row=>{
          classeLinha(row.cols||[row.esq||[],row.dir||[]]).forEach(({s,t})=>{
            c.total++;
            if(!occ.has(s)&&!res.has(s)&&!trv.has(s)){ c.livre++; if(c[t]!==undefined)c[t]++; }
          });
        }));
        return c;
      },

      // ---- totais ----
      get subPassagem(){ return this.passageiros.reduce((s,p)=>s+(Number(p.valor_passagem)||0),0); },
      get subTaxa(){ return this.passageiros.reduce((s,p)=>s+(Number(p.valor_taxa)||0),0); },
      get subAlim(){ return this.passageiros.reduce((s,p)=>s+(Number(p.valor_alimentacao)||0),0); },
      get total(){ return Math.max(this.subPassagem+this.subTaxa+this.subAlim-(Number(this.desconto)||0),0); },
      get forma(){ if(this.tipoPag==='faturado') return 'faturado'; if(this.tipoPag==='online') return 'online'; return 'avista'; },
      get agenteOn(){ return this.tipoPag==='agente'; },
      get pagoProprietario(){ return this.tipoPag==='balcao' && this.proprietarioMaq; },
      get pago(){ if(this.tipoPag==='faturado') return 0; if(!this.dividir) return (Number(this.total)||0); return this.metodosAtivos.reduce((s,k)=>s+(Number(this.pag[k])||0),0); },
      get devido(){ return Math.max((Number(this.total)||0)-this.pago,0); },

      fmtPix(v){ return (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); },
      emvF(id,v){ v=String(v); return id+String(v.length).padStart(2,'0')+v; },
      crc16Pix(p){ let c=0xFFFF; for(let i=0;i<p.length;i++){ c^=p.charCodeAt(i)<<8; for(let j=0;j<8;j++){ c=(c&0x8000)?((c<<1)^0x1021):(c<<1); c&=0xFFFF; } } return c.toString(16).toUpperCase().padStart(4,'0'); },
      pixEstPayload(valor){ const d=this.pixEstDados||{}; const limpa=s=>(s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^A-Za-z0-9 ]/g,'').toUpperCase().trim(); const chave=(d.chave||'').trim(); const nome=(limpa(d.nome)||'RECEBEDOR').slice(0,25); const cidade=(limpa(d.cidade)||'CIDADE').slice(0,15); const mai=this.emvF('00','BR.GOV.BCB.PIX')+this.emvF('01',chave); let p=this.emvF('00','01')+this.emvF('26',mai)+this.emvF('52','0000')+this.emvF('53','986'); if(valor>0) p+=this.emvF('54',Number(valor).toFixed(2)); p+=this.emvF('58','BR')+this.emvF('59',nome)+this.emvF('60',cidade)+this.emvF('62',this.emvF('05','***'))+'6304'; return p+this.crc16Pix(p); },
      gerarPixEstatico(){ const valor=Math.max(Number(this.total)||0,0); this.pixValor=valor; this.pixEstCopia=this.pixEstPayload(valor); this.pixEstModal=true; this.$nextTick(()=>{ const el=document.getElementById('qr-pix-est'); if(el){ el.innerHTML=''; try{ new QRCode(el,{text:this.pixEstCopia,width:220,height:220}); }catch(e){} } }); },
      copiarPixEst(){ try{ navigator.clipboard.writeText(this.pixEstCopia); }catch(e){} },
      gerarPixDinamico(){
        if(this.pixPagando) return;
        if(!this.origemSel || !this.destinoSel){ alert('Escolha a origem e o destino antes de gerar o PIX.'); return; }
        if(!this.validarPassageiros()) return;
        const valor=Math.max(Number(this.total)||0,0);
        if(valor<=0){ alert('Sem valor a cobrar.'); return; }
        const pedido=this.montarDados();
        this.pixCobId=null; this.pixCopia=''; this.pixQr=''; this.pixValor=valor; this.pixModal=true; this.pixPagando=true;
        fetch('/cobranca/pix/gerar',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},body:JSON.stringify({modulo:'passagem',valor:valor,descricao:'Passagem',imprimir:this.imprimir,payload:pedido})})
          .then(r=>r.json()).then(d=>{
            if(!d.ok){ this.pixModal=false; this.pixPagando=false; alert(d.erro||'Falha ao gerar PIX'); return; }
            this.pixCobId=d.id; this.pixQr=d.qr_base64||''; this.pixCopia=d.copia_cola||''; this.pixValor=d.valor; this.imprimirPix(d.id);
            this.pixPoll=setInterval(()=>this.checarPix(),4000);
          }).catch(()=>{ this.pixModal=false; this.pixPagando=false; alert('Falha de conexão ao gerar PIX'); });
      },
      checarPix(){
        if(!this.pixCobId || this.pixChecando) return;
        this.pixChecando=true;
        fetch('/cobranca/'+this.pixCobId+'/status',{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(d=>{
          if(d && d.pago){
            clearInterval(this.pixPoll); this.pixPagando=false;
            this.finalizarPix(d);
          }
        }).catch(()=>{}).finally(()=>{ this.pixChecando=false; });
      },
      // pago: o servidor já criou a passagem. Só imprimimos (se for o caso) e redirecionamos.
      finalizarPix(d){
        if(d.erro || !d.url){
          this.pixModal=false;
          alert('Pagamento recebido! '+(d.erro ? ('Não consegui cadastrar automaticamente: '+d.erro+'. ') : '')+'Confira os dados e clique em Salvar.');
          return;
        }
        this.salvando=true;
        if(this.imprimir==='pix'){ window.location.href='/imprimir/pix-passagem/'+d.id; return; }
        if(this.imprimir==='direto'){ this.imprimirDiretoENovo(d.id); return; }
        window.location.href = d.url;
      },
      cancelarPix(){
        if(!confirm('Cancelar esta cobrança? Se o cliente já pagou, a venda será registrada normalmente.')) return;
        if(this.pixPoll) clearInterval(this.pixPoll); this.pixPagando=false;
        const id=this.pixCobId;
        if(!id){ this.pixModal=false; return; }
        fetch('/cobranca/'+id+'/cancelar',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{
          if(d && d.pago){ this.finalizarPix(d); return; }
          this.pixModal=false; this.pixCobId=null;
        }).catch(()=>{ this.pixModal=false; this.pixCobId=null; });
      },
      copiarPix(){ try{ navigator.clipboard.writeText(this.pixCopia); }catch(e){} },
    imprimirPix(id){ fetch('/cobranca/'+id+'/imprimir-direto',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ if(!d||!d.ok){ alert((d&&d.erro)||'Nenhuma estação de impressão ativa. Mostre o QR na tela.'); } }).catch(()=>{}); },
      // Imprime o bilhete direto na térmica (via estação) e já libera a tela p/ o próximo passageiro.
      async imprimirDiretoENovo(id){
        let ok=false, cancelou=false;
        try{
          const qs = FBRE.perguntarImpressao('O que imprimir?');
          if(qs===null){ cancelou=true; }
          else {
            const _sp=new URLSearchParams(qs); const so=_sp.get('so')||'tudo'; const vias=Math.max(1,parseInt(_sp.get('vias')||'1',10));
            const __rz = await this._rasterComprovante('/passagens/'+id+'/comprovante?so='+so);
            let imagens = [];
            if(so!=='resumo' && __rz && __rz.imagens && __rz.imagens.length){ for(let __v=0; __v<vias; __v++) imagens=imagens.concat(__rz.imagens); }
            if(so!=='bilhete' && __rz && __rz.resumo) imagens.push(__rz.resumo);
            if(imagens && imagens.length){
              const dd = await fetch('/passagens/'+id+'/imprimir-direto',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({imagens})}).then(r=>r.json()).catch(()=>null);
              ok = !!(dd && dd.ok);
              if(!ok) alert((dd && dd.erro) || 'Não consegui imprimir direto. Vou abrir a passagem pra você imprimir.');
            } else { alert('Nada selecionado pra imprimir.'); }
          }
        }catch(e){}
        if(cancelou){ window.location.href='/passagens/'+id; return; }       // cancelou a impressão: abre a passagem (salva) p/ imprimir depois
        if(ok){ window.location.href='{{ route('passagens.create') }}'; }    // imprimiu: libera p/ novo cadastro
        else { window.location.href='/passagens/'+id; }                      // falhou: abre a passagem p/ imprimir manual
      },
      // Renderiza o comprovante em iframe oculto (raster) e devolve as imagens dos bilhetes.
      _rasterComprovante(url){
        return new Promise((resolve)=>{
          let done=false; const ifr=document.createElement('iframe');
          ifr.style.cssText='position:fixed;left:-10000px;top:0;width:360px;height:10px;border:0;opacity:0';
          const fim=(res)=>{ if(done)return; done=true; window.removeEventListener('message',onMsg); try{ifr.remove();}catch(e){} resolve(res||{imagens:[],resumo:null}); };
          const onMsg=(ev)=>{ const d=ev.data; if(!d||!d.fbreRaster)return; fim({imagens:d.imagens||[], resumo:d.resumo||null}); };
          window.addEventListener('message',onMsg);
          document.body.appendChild(ifr);
          ifr.src = url + (url.indexOf('?')>=0?'&':'?') + 'raster=1';
          setTimeout(()=>fim({imagens:[],resumo:null}), 20000);
        });
      },
      validarPassageiros(){
        if(!this.passageiros.length){ alert('Adicione ao menos um passageiro.'); return false; }
        if(this.passageiros.some(p=>!p.nome.trim())){ alert('Informe o nome de todos os passageiros.'); return false; }
        for(const p of this.passageiros){
          const _nm=(p.nome||'').trim()||'sem nome';
          if(!p.nascimento){ alert('Informe a data de nascimento de "'+_nm+'".'); return false; }
          if(!p.sexo){ alert('Selecione o sexo de "'+_nm+'".'); return false; }
          if(!p.doc_tipo){ alert('Selecione o tipo de documento de "'+_nm+'".'); return false; }
          if(!(p.documento||'').trim()){ alert('Informe o número do documento de "'+_nm+'".'); return false; }
        }
        const pxErr=this.passageiros.find(p=>!FBRE.docOk(p.documento));
        if(pxErr){ alert('CPF inválido no passageiro "'+((pxErr.nome||'').trim()||'sem nome')+'". Confira os números ou deixe em branco.'); return false; }
        if(!FBRE.docOk(this.compradorDoc)){ alert('CPF do comprador inválido. Confira os números ou deixe em branco.'); return false; }
        if(this.usaPoltrona){ const semPol=this.passageiros.find(p=>!p.poltrona); if(semPol){ alert('Este transporte usa poltrona. Escolha a poltrona de "'+((semPol.nome||'').trim()||'sem nome')+'" antes de salvar.'); return false; } const ass=this.passageiros.map(p=>p.poltrona).filter(Boolean); if(new Set(ass).size!==ass.length){ alert('Há poltronas repetidas.'); return false; } }
        return true;
      },
      // ---- autocomplete de passageiro (busca em pessoas já cadastradas) ----
      async buscarPax(i){ const q=(this.passageiros[i]?.nome||'').trim(); if(q.length<2){ this.paxLista=[]; this.paxAberto=-1; return; } try{ const r=await fetch(`{{ route('pessoas.buscar') }}?q=${encodeURIComponent(q)}&fonte=passagem`); this.paxLista=await r.json(); this.paxAberto=i; }catch(e){} },
      escolherPax(i,p){ const px=this.passageiros[i]; if(!px) return; px.nome=p.nome;
        if(p.doc_tipo) px.doc_tipo=p.doc_tipo;
        if(p.documento){ px.documento=p.documento; if(!px.doc_tipo && FBRE.pareceCpf(p.documento)) px.doc_tipo='CPF'; }
        if(p.telefone) px.telefone=FBRE.foneMask(p.telefone);
        if(p.sexo) px.sexo=p.sexo;
        if(p.nacionalidade) px.nacionalidade=p.nacionalidade;
        if(p.nascimento){ px.nascimento=p.nascimento; px.nasc_br=this.isoToBr(p.nascimento); }
        if(p.foto) px.foto=p.foto; if(p.foto_rg) px.foto_rg=p.foto_rg;
        px._mais=true; this.aplicarIdade(px);
        this.paxAberto=-1; this.paxLista=[]; },
      // Converte anexos (proxies reativos do Alpine) em objetos puros p/ o IndexedDB clonar sem erro.
      _docsPlanos(lista){ return (lista||[]).map(d=> (d && typeof d==='object') ? {d:(d.d||null), u:(d.u||null), n:(d.n||null), t:(d.t||null)} : (d!=null?String(d):null)).filter(x=>x && (x===Object(x) ? (x.d||x.u) : true)); },
      montarDados(){
        return {
          origem:this.origemSel, destino:this.destinoSel,
          embarcacao_id:this.embarcacaoSel||null, rota_id:this.rotaId||null, data_viagem:this.viagemData||null,
          comprador_nome:this.compradorNome||null, comprador_telefone:this.compradorTel||null, comprador_documento:this.compradorDoc||null,
          forma:this.forma, parcelas:((this.metodosAtivos.includes('credito') && this.credParcel)?(Number(this.parcelas)||1):1), desconto:Number(this.desconto)||0, observacao:this.observacao||null,
          agente_id:(this.tipoPag==='agente' && this.agenteId)?Number(this.agenteId):null, agente_acerto:(this.tipoPag==='agente' && this.agenteAcerto)?1:0,
          cliente_faturado_id:(this.tipoPag==='faturado' && this.clienteFaturadoId)?Number(this.clienteFaturadoId):null,
          passageiros:this.passageiros.map(p=>({nome:p.nome, tipo:p.tipo, percentual:p.percentual, modo:p.modo||'porcentagem', documento:p.documento||null, doc_tipo:p.doc_tipo||null, poltrona:p.poltrona||null, sexo:p.sexo||null, telefone:p.telefone||null, nacionalidade:p.nacionalidade||null, nascimento:p.nascimento||null, foto_rg:p.foto_rg||null, foto:p.foto||null, documentos:this._docsPlanos(p.documentos), papel:p.papel||null, bebe_colo:((p.temColo && (p.bebeColo.nome||'').trim()) ? {nome:p.bebeColo.nome.trim(), documento:(p.bebeColo.documento||'').trim()||null, nascimento:p.bebeColo.nascimento||null} : null), checkin:p.checkin?1:0, valor_passagem:Number(p.valor_passagem)||0, valor_taxa:Number(p.valor_taxa)||0, valor_alimentacao:Number(p.valor_alimentacao)||0, valor_integral:Number(p.valor_integral)||0})),
          documentos_gerais:this._docsPlanos(this.documentosGerais),
          pagamentos:this.tipoPag==='faturado'?[]:(this.dividir ? this.metodosAtivos.filter(k=>Number(this.pag[k])>0).map(k=>({meio:k, valor:Number(this.pag[k]), proprietario:(this.pagoProprietario?1:0)})) : [{meio:(this.metodosAtivos[0]||'dinheiro'), valor:this.total, proprietario:(this.pagoProprietario?1:0)}]),
        };
      },
      fatDocTipoCalc(){ const d=(this.fatDoc||'').replace(/\D/g,''); this.fatDocTipo = d.length===14?'CNPJ':(d.length>=11?'CPF':''); },
      async fatBuscar(){ const q=(this.fatBusca||'').trim(); if(q.length<1){ this.fatResultados=[]; return; } try{ const r=await fetch(@js(route('passagens.cliente-faturado.buscar'))+'?q='+encodeURIComponent(q),{headers:{Accept:'application/json'},credentials:'same-origin'}); this.fatResultados = r.ok ? (await r.json()) : []; }catch(e){ this.fatResultados=[]; } },
      fatSelecionar(c){ this.fatSel=c; this.clienteFaturadoId=c.id; this.fatResultados=[]; this.fatBusca=''; },
      fatLimpar(){ this.fatSel=null; this.clienteFaturadoId=null; },
      async fatCriar(){ const nome=(this.fatNome||'').trim(); if(!nome) return; try{ const r=await fetch(@js(route('passagens.cliente-faturado.criar')),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},credentials:'same-origin',body:JSON.stringify({nome:nome, empresa:(this.fatEmpresa||'').trim()||null, documento:(this.fatDoc||'').trim()||null})}); const j=await r.json(); if(j&&j.ok&&j.cliente){ this.fatSelecionar(j.cliente); this.fatNome=''; this.fatEmpresa=''; this.fatDoc=''; this.fatDocTipo=''; } else { alert('Nao deu pra cadastrar o cliente.'); } }catch(e){ alert('Erro ao cadastrar cliente.'); } },
      async salvar(ev){
        if(this.salvando) return;
        if(this.edMode){ return this.atualizarVenda(ev); }
        if(this.abas.length>1){ return this.finalizarAbas(ev); }
        if(!this.validarPassageiros()) return;
        if((this.forma==='avista' || this.forma==='online') && this.devido > 0.009){ alert('Falta lançar '+FBRE.moeda(this.devido)+'. Lance o valor nas formas ou use Faturado pra deixar a receber.'); return; }
        this.salvando=true;
        try{ if(this.origemSel) localStorage.setItem('fbre_origem',this.origemSel); if(this.embarcacaoSel) localStorage.setItem('fbre_transporte',this.embarcacaoSel); }catch(e){}
        const dados=this.montarDados();
        dados.pix_cobranca_id=this.pixCobId||null;
        try{
          const r=await FBREOffline.salvarModulo('passagem', dados);
          if(r.servidor && r.servidor.ok){ if(this.imprimir==='pix'){ window.location.href='/imprimir/pix-passagem/'+r.servidor.id; return; } if(this.imprimir==='direto'){ await this.imprimirDiretoENovo(r.servidor.id); return; } window.location.href=r.servidor.url; return; }
          if(this.imprimir!=='nao') await FBREOffline.imprimirModuloLocal(r.local);
          alert('Sem conexão: passagem '+r.local.dados.numero_completo+' guardada neste aparelho. Sobe sozinha quando a internet voltar.');
          window.location.href='{{ route('passagens.create') }}';
        }catch(e){ alert('Não foi possível salvar: '+e.message); this.salvando=false; }
      },
      async atualizarVenda(ev){
        if(!this.validarPassageiros()) return;
        if((this.forma==='avista' || this.forma==='online') && this.devido > 0.009){ alert('Falta lançar '+FBRE.moeda(this.devido)+'. Lance o valor nas formas ou use Faturado pra deixar a receber.'); return; }
        this.salvando=true;
        try{
          const dados=this.montarDados();
          const r=await fetch(this.edUrl,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'X-Requested-With':'fetch','Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(dados)});
          if(r.ok){ const j=await r.json().catch(()=>null); window.location.href=(j&&j.url)?j.url:'/passagens'; return; }
          if(r.status===422){ const j=await r.json().catch(()=>null); const msg=(j&&j.message)?j.message:'Confira os campos.'; const errs=(j&&j.errors)?Object.values(j.errors).flat().join('\n'):''; alert((msg+'\n'+errs).trim()); this.salvando=false; return; }
          const j=await r.json().catch(()=>null); alert((j&&(j.message||j.erro))||('Não foi possível atualizar ('+r.status+').')); this.salvando=false;
        }catch(e){ alert('Erro ao atualizar: '+e.message); this.salvando=false; }
      },
      // ---------- ABAS DE VENDAS (várias vendas, cada uma editável) ----------
      _vendaKeys:['origemSel','destinoSel','embarcacaoSel','rotaId','linhaNome','paradasInfo','viagemData','viagemLabel','viagemDia','viagemHora','passageiros','tipoPag','metodosAtivos','credParcel','dividir','pag','proprietarioMaq','parcelas','desconto','agenteId','agenteAcerto','clienteFaturadoId','fatSel','compradorNome','compradorTel','compradorDoc','observacao','documentosGerais'],
      _clone(v){ try{ return JSON.parse(JSON.stringify(v===undefined?null:v)); }catch(e){ return v; } },
      _snap(){ const o={}; this._vendaKeys.forEach(k=>{ o[k]=this._clone(this[k]); }); return o; },
      _load(snap){
        this._restaurando=true;
        this._vendaKeys.forEach(k=>{ if(k in snap) this[k]=this._clone(snap[k]); });
        this.$nextTick(()=>{ this._restaurando=false; try{ this.mudarEmbarcacao(); }catch(e){} try{ this.carregarGratuidade(); }catch(e){} });
      },
      _freshVenda(base){
        const s = base ? this._clone(base) : this._snap();
        s.passageiros=[]; s.destinoSel='';
        s.tipoPag='balcao'; s.metodosAtivos=['dinheiro']; s.credParcel=false; s.dividir=false;
        s.pag={dinheiro:null,pix:null,debito:null,credito:null}; s.proprietarioMaq=false; s.parcelas=1; s.desconto=null;
        s.agenteId=''; s.agenteAcerto=false; s.clienteFaturadoId=null; s.fatSel=null;
        s.compradorNome=''; s.compradorTel=''; s.compradorDoc=''; s.observacao=''; s.documentosGerais=[];
        return s;
      },
      abaLabel(i){ return 'Venda '+(i+1); },
      novaAba(){
        if(this.salvando) return;
        if(!this.abas.length) this.abas=[this._snap()];
        this.abas[this.abaAtual]=this._snap();
        const nova=this._freshVenda(this.abas[this.abaAtual]);
        this.abas.push(nova); this.abaAtual=this.abas.length-1;
        this._load(nova);
        this.$nextTick(()=>{ if(!this.passageiros.length){ const np=this.novoPax(); this.aplicarTipo(np); this.passageiros.push(np); } });
        try{ window.scrollTo({top:0,behavior:'smooth'}); }catch(e){}
      },
      trocarAba(i){
        if(i===this.abaAtual) return;
        if(!this.abas.length) this.abas=[this._snap()];
        this.abas[this.abaAtual]=this._snap();
        this.abaAtual=i; this._load(this.abas[i]);
      },
      fecharAba(i){
        if(this.abas.length<=1) return;
        if(!confirm('Remover esta venda da lista?')) return;
        this.abas.splice(i,1);
        if(this.abaAtual>i) this.abaAtual--;
        if(this.abaAtual>this.abas.length-1) this.abaAtual=this.abas.length-1;
        this._load(this.abas[this.abaAtual]);
      },
      async _printDiretoId(id, so, vias){
        try{
          const __rz = await this._rasterComprovante('/passagens/'+id+'/comprovante?so='+so);
          let imagens=[];
          if(so!=='resumo' && __rz && __rz.imagens && __rz.imagens.length){ for(let v=0; v<vias; v++) imagens=imagens.concat(__rz.imagens); }
          if(so!=='bilhete' && __rz && __rz.resumo) imagens.push(__rz.resumo);
          if(imagens.length){ const dd=await fetch('/passagens/'+id+'/imprimir-direto',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({imagens})}).then(r=>r.json()).catch(()=>null); return !!(dd && dd.ok); }
        }catch(e){}
        return false;
      },
      async finalizarAbas(ev){
        if(ev && ev.preventDefault) ev.preventDefault();
        if(this.salvando) return;
        if(!this.abas.length) this.abas=[this._snap()];
        this.abas[this.abaAtual]=this._snap();
        const payloads=[];
        for(let i=0;i<this.abas.length;i++){
          this.abaAtual=i; this._load(this.abas[i]); await this.$nextTick();
          if(!this.validarPassageiros()){ return; }
          if((this.forma==='avista' || this.forma==='online') && this.devido > 0.009){ alert('Venda '+(i+1)+': falta lançar '+FBRE.moeda(this.devido)+'. Feche o pagamento ou use Faturado.'); return; }
          payloads.push(this.montarDados());
        }
        this.salvando=true;
        let so=null, vias=1, modo=this.imprimir;
        if(modo==='direto'){ const qs=FBRE.perguntarImpressao('O que imprimir? (vale pra todas as vendas)'); if(qs===null){ modo='nao'; } else { const sp=new URLSearchParams(qs); so=sp.get('so')||'tudo'; vias=Math.max(1,parseInt(sp.get('vias')||'1',10)); } }
        let ok=0, falhas=0;
        for(const dados of payloads){
          try{
            const r=await FBREOffline.salvarModulo('passagem', dados);
            if(r.servidor && r.servidor.ok){ ok++; if(modo==='direto' && so){ await this._printDiretoId(r.servidor.id, so, vias); } }
            else if(r.local){ ok++; if(modo!=='nao'){ try{ await FBREOffline.imprimirModuloLocal(r.local); }catch(e){} } }
            else { falhas++; }
          }catch(e){ falhas++; }
        }
        this.salvando=false;
        alert(ok+' venda(s) finalizada(s).'+(falhas?(' '+falhas+' não subiram — tente de novo.'):''));
        window.location.href='{{ route('passagens.create') }}';
      },
    };
  }

  function pessoaBusca(){
    return { lista:[], aberto:false,
      async buscar(){ const q=this.compradorNome; if(!q || q.length<2){this.lista=[];return;} try{ const r=await fetch(`{{ route('pessoas.buscar') }}?q=${encodeURIComponent(q)}`); this.lista=await r.json(); this.aberto=true; }catch(e){} },
      escolher(p){ this.compradorNome=p.nome; this.compradorTel=p.telefone||''; this.compradorDoc=p.documento||''; this.aberto=false; this.lista=[]; } };
  }

  // comprovante local 80mm (offline)
  document.addEventListener('DOMContentLoaded',()=>{
    if(!window.FBREOffline) return;
    window.FBREOffline.renderers['passagem']=function(item,b){
      const e=(b&&b.empresa)||{}; const d=item.dados;
      const esc=s=>String(s??'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
      const moeda=v=>(Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
      const subT=d.passageiros.reduce((s,p)=>s+(Number(p.valor_passagem)||0),0);
      const subA=d.passageiros.reduce((s,p)=>s+(Number(p.valor_alimentacao)||0),0);
      const subX=d.passageiros.reduce((s,p)=>s+(Number(p.valor_taxa)||0),0);
      const sub=subT+subA+subX;
      const desc=Number(d.desconto)||0, total=Math.max(sub-desc,0);
      const pago=(d.pagamentos||[]).reduce((s,p)=>s+(Number(p.valor)||0),0), devido=Math.max(total-pago,0);
      const linhas=d.passageiros.map(p=>{
        const vt=Number(p.valor_passagem)||0, va=Number(p.valor_alimentacao)||0, vx=Number(p.valor_taxa)||0;
        const pago=vt+va+vx; const vi=Number(p.valor_integral)||0;
        let bd='Transp. '+moeda(vt).replace('R$','').trim(); if((va+vx)>0) bd+=' · Alim.+Adic. '+moeda(va+vx).replace('R$','').trim();
        const normal = (vi>pago+0.009) ? `<div class="sm">Preço normal ${moeda(vi).replace('R$','').trim()} · Pago ${moeda(pago).replace('R$','').trim()}</div>` : '';
        return `<tr><td>${esc(p.nome)}<div class="sm">${esc(p.tipo)}${p.poltrona?(' · poltrona '+esc(p.poltrona)):''}</div>${normal}<div class="sm">${bd}</div></td><td class="r b">${moeda(pago).replace('R$','').trim()}</td></tr>`;
      }).join('');
      const html=`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Passagem ${esc(d.numero_completo)}</title>
      <style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px}
      .c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}
      table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}</style></head><body onload="window.print()">
      <div class="c b lg">${esc((e.nome||'FBRE').toUpperCase())}</div>${e.cnpj?`<div class="c sm">CNPJ ${esc(e.cnpj)}</div>`:''}<hr>
      <div class="c b xl">PASSAGEM ${esc(d.numero_completo)}</div>
      <div class="c sm">${new Date().toLocaleString('pt-BR')}</div><hr>
      <table><tr><td class="b">ORIGEM</td><td class="r">${esc(d.origem)}</td></tr><tr><td class="b">DESTINO</td><td class="r">${esc(d.destino)}</td></tr>${d.data_viagem?`<tr><td class="b">VIAGEM</td><td class="r">${esc(d.data_viagem)}</td></tr>`:''}</table><hr>
      <div class="b">PASSAGEIROS</div><table>${linhas}</table><hr>
      <table><tr><td>Tarifa de Transporte</td><td class="r">${moeda(subT)}</td></tr><tr><td>Alimentação + Adicional</td><td class="r">${moeda(subA+subX)}</td></tr>${desc>0?`<tr><td>Desconto</td><td class="r">-${moeda(desc)}</td></tr>`:''}<tr class="lg"><td class="b">TOTAL</td><td class="r b">${moeda(total)}</td></tr><tr><td>Pago</td><td class="r">${moeda(pago)}</td></tr><tr><td class="b">DEVIDO</td><td class="r b">${moeda(devido)}</td></tr></table>
      ${e.rodape?`<hr><div class="c sm">${esc(e.rodape)}</div>`:''}<hr><div class="c sm">Apresente esta passagem no embarque.</div><br><br></body></html>`;
      const w=window.open('','fbre_print','width=420,height=640'); if(!w)return alert('Permita pop-ups para imprimir.'); w.document.open(); w.document.write(html); w.document.close();
    };
  });
</script>
