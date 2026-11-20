f='resources/views/admin/usuario-form.blade.php'
s=open(f,encoding='utf-8').read()
anchor="""  @foreach($admPerms as $k=>[$l,$d])
    <label class="flex items-start gap-3 py-1.5 cursor-pointer">
      <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old($k, $u->exists ? $u->$k : in_array($k,['perm_parcial'])))>
      <span><span class="font-semibold text-sm">{{ $l }}</span><br><span class="text-xs text-slate-500">{{ $d }}</span></span>
    </label>
  @endforeach"""
assert anchor in s, "anchor admPerms foreach nao achado"
add='''
  @php $formaPerms = [
    'perm_forma_avista'    => ['À vista', 'Pode vender à vista (dinheiro, PIX, cartão na hora).'],
    'perm_forma_parcelado' => ['Parcelado', 'Pode vender parcelado (escolhe o nº de vezes).'],
    'perm_forma_faturado'  => ['Faturado', 'Pode vender faturado (cliente paga depois / empresa conveniada).'],
    'perm_forma_online'    => ['Compra Online', 'Pode lançar a venda como Compra Online.'],
  ]; @endphp
  <div class="mt-2 pt-2 border-t"><div class="text-xs font-bold text-slate-500 uppercase mb-1">💳 Formas de pagamento que pode usar</div><p class="text-[11px] text-slate-400 mb-1">Admin e gerente podem usar todas. Para operador de balcão, marque só o que ele pode.</p></div>
  @foreach($formaPerms as $k=>[$l,$d])
    <label class="flex items-start gap-3 py-1.5 cursor-pointer">
      <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old($k, $u->exists ? $u->$k : in_array($k,['perm_forma_avista','perm_forma_online'])))>
      <span><span class="font-semibold text-sm">{{ $l }}</span><br><span class="text-xs text-slate-500">{{ $d }}</span></span>
    </label>
  @endforeach'''
if 'formaPerms' not in s: s=s.replace(anchor, anchor+add, 1)
open(f,'w',encoding='utf-8').write(s)
print("OK:", 'formaPerms' in s)
