f='resources/views/caixa/index.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) @php: calcula cor + trecho da venda
a1="    @php $rf = $m->resumoFonte(); @endphp"
b1='''    @php
      $rf = $m->resumoFonte();
      $pz = ($m->modulo === 'passagem') ? (($passMov ?? collect())[$m->origem_id] ?? null) : null;
      $corMov = null; $trechoMov = null;
      if ($pz) {
        $trechoMov = $pz->origem.' → '.$pz->destino;
        if ($pz->cancelada) $corMov = '#fee2e2';
        elseif ($pz->forma === 'faturado') $corMov = '#dbeafe';
        elseif ($pz->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'pcd')!==false || mb_stripos((string)$x->tipo,'defic')!==false)) $corMov = '#fef9c3';
        elseif ($pz->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'idoso')!==false)) $corMov = '#dcfce7';
      }
    @endphp'''
if '$corMov = null' not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) <a> ganha a cor de fundo
a2='    <a href="{{ $rf[\'url\'] ?? \'#\' }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 text-sm">'
b2='    <a href="{{ $rf[\'url\'] ?? \'#\' }}" class="flex items-center gap-3 p-3 hover:brightness-95 text-sm"@if($corMov) style="background:{{ $corMov }}"@endif>'
if a2 in s: s=s.replace(a2,b2,1)

# 3) acrescenta o trecho na descricao (apos o nome)
a3='<b>#{{ $rf[\'numero\'] }}</b> {{ $rf[\'nome\'] }}<div class="text-xs text-slate-400">'
b3='<b>#{{ $rf[\'numero\'] }}</b> {{ $rf[\'nome\'] }}@if($trechoMov) <span class="text-slate-500">· {{ $trechoMov }}</span>@endif<div class="text-xs text-slate-400">'
if a3 in s: s=s.replace(a3,b3,1)

open(f,'w',encoding='utf-8').write(s)
print("php:", '$corMov = null' in s, "| a-cor:", 'hover:brightness-95' in s, "| trecho:", '$trechoMov) <span' in s, "| changed:", s!=orig)
