f='resources/views/passagens/index.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) Botao de editar na linha (antes do botao de impressao)
btn_anchor='<button type="button" onclick="FBRE.abrirImpressao('
edit_btn='@if($podeEditar && !$p->cancelada)<button type="button" @click="editarUm({{ $p->id }})" class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[12px] font-semibold text-amber-700 hover:bg-amber-100" title="Editar tudo desta passagem">✏️</button>@endif\n            '
if 'editarUm({{ $p->id }})' not in s:
    s=s.replace(btn_anchor, edit_btn+btn_anchor, 1)

# 2) Estado Alpine
st_anchor="        f: { embarcacao_id:'', data_viagem:'', origem:'', destino:'', forma:'' },"
st_add=st_anchor+"""
        edModal:false, edLoading:false, edSalvando:false, edId:null,
        ed:{numero:'',embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:'avista',comprador_nome:'',comprador_telefone:'',comprador_documento:'',observacao:'',passageiros:[]},"""
if 'edModal:false' not in s:
    s=s.replace(st_anchor, st_add, 1)

# 3) Metodos editarUm / salvarUm (depois do abrir())
m_anchor="        abrir(){ if(!this.sel.length) return; this.f={embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:''}; this.modal=true; },"
m_add=m_anchor+"""
        async editarUm(id){
          this.edId=id; this.edLoading=true; this.edModal=true;
          try{
            const r=await fetch('/passagens/'+id+'/editar-dados',{headers:{'Accept':'application/json','X-CSRF-TOKEN':FBRE.csrf}});
            const j=await r.json().catch(()=>null);
            if(r.ok && j && j.ok){ this.ed=j; } else { alert((j&&j.erro)||'Nao foi possivel carregar.'); this.edModal=false; }
          }catch(e){ alert('Erro: '+e.message); this.edModal=false; }
          this.edLoading=false;
        },
        async salvarUm(){
          if(!(this.ed.origem||'').trim() || !(this.ed.destino||'').trim()){ alert('Origem e destino sao obrigatorios.'); return; }
          if(this.ed.passageiros.some(p=>!(p.nome||'').trim())){ alert('Todo passageiro precisa de nome.'); return; }
          this.edSalvando=true;
          try{
            const r=await fetch('/passagens/'+this.edId+'/atualizar',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},body:JSON.stringify(this.ed)});
            const j=await r.json().catch(()=>null);
            if(r.ok && j && j.ok){ location.reload(); }
            else if(r.status===422 && j && j.errors){ alert('Confira os campos: '+Object.values(j.errors).flat().join(' ')); }
            else { alert((j&&(j.erro||j.message))||'Nao foi possivel salvar.'); }
          }catch(e){ alert('Erro: '+e.message); }
          this.edSalvando=false;
        },"""
if 'async editarUm(' not in s:
    s=s.replace(m_anchor, m_add, 1)

open(f,'w',encoding='utf-8').write(s)
print("CHANGED" if s!=orig else "NO-CHANGE")
