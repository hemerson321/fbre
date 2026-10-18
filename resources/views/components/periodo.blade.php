{{-- seletor de período: hoje / semana / mês / personalizado --}}
<form method="get" class="card p-3 flex flex-wrap gap-2 items-end" x-data="{ p:'{{ $periodo }}' }">
  @foreach(['hoje'=>'Hoje','semana'=>'Semana','mes'=>'Mês','personalizado'=>'Personalizado'] as $k=>$l)
    <label class="btn btn-sm cursor-pointer" :class="p==='{{ $k }}'?'btn-primary':'btn-ghost'"><input type="radio" name="periodo" value="{{ $k }}" x-model="p" class="sr-only" @change="if(p!=='personalizado') $el.form.submit()">{{ $l }}</label>
  @endforeach
  <div x-show="p==='personalizado'" class="flex flex-wrap gap-2 items-end" x-cloak>
    <div><label class="label">De</label><input type="date" name="inicio" value="{{ request('inicio', $inicio->toDateString()) }}" class="input !min-h-[38px]"></div>
    <div><label class="label">Hora</label><input type="time" name="hora_inicio" value="{{ request('hora_inicio','00:00') }}" class="input !min-h-[38px]"></div>
    <div><label class="label">Até</label><input type="date" name="fim" value="{{ request('fim', $fim->toDateString()) }}" class="input !min-h-[38px]"></div>
    <div><label class="label">Hora</label><input type="time" name="hora_fim" value="{{ request('hora_fim','23:59') }}" class="input !min-h-[38px]"></div>
    <button class="btn btn-primary btn-sm">Aplicar</button>
  </div>
  {{ $slot ?? '' }}
  <span class="text-xs text-slate-500 ml-auto">{{ $inicio->format('d/m/Y H:i') }} → {{ $fim->format('d/m/Y H:i') }}</span>
</form>
