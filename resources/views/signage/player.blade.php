<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>{{ $tela->nome }} · Digital Signage</title>
<style>html,body{margin:0;height:100%;background:#000;overflow:hidden;cursor:none}#palco{position:fixed;inset:0}</style>
</head>
<body>
<div id="palco"></div>
@include('signage._engine')
<script>
(function(){
  var demo = @json($demo);
  var url = @json(route('signage.dados', $tela->token));
  var p = new SG.Player(document.getElementById('palco'), { empresa: @json($empresa), rotulo: @json($tela->nome.($tela->local ? ' · '.$tela->local : '')), mudo: demo });
  var falhas = 0;
  function buscar(){
    fetch(url + (demo ? '?demo=1' : ''), {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(d){
      falhas = 0;
      if (d.versao && window.__v && d.versao !== window.__v) { location.reload(); return; } // sistema atualizado: recarrega o player
      window.__v = d.versao || window.__v;
      p.carregar(d);
    }).catch(function(){ falhas++; });
  }
  buscar(); setInterval(buscar, demo ? 6000 : 30000);
  if (demo) document.body.style.cursor = 'default';
  // tela cheia ao tocar/clicar (TV Box em modo quiosque já abre cheia)
  document.addEventListener('click', function(){ if (demo) return; var e=document.documentElement; if(!document.fullscreenElement && e.requestFullscreen){ e.requestFullscreen().catch(function(){}); } });
})();
</script>
</body>
</html>
