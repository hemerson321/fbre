<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $titulo ?? 'Impressão' }}</title>
<style>
  @page { margin: 0; size: {{ $pagina ?? '80mm auto' }}; }
  * { box-sizing: border-box; }
  body { margin: 0; padding: 3mm 4mm; width: {{ $largura ?? '80mm' }}; font-family: "Courier New", Courier, monospace; font-size: 11.5px; color: #000; background:#fff; }
  .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
  .xl { font-size: 18px; } .lg { font-size: 14px; } .sm { font-size: 10px; }
  hr { border: 0; border-top: 1px dashed #000; margin: 4px 0; }
  table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
  .logo { max-height: 18mm; max-width: 60mm; filter: grayscale(1); }
  .quebra { page-break-after: always; }
  .box { border: 1px solid #000; padding: 2mm; margin: 2mm 0; }
  @media screen { body { margin: 10px auto; box-shadow: 0 0 8px #0003; } }
</style>
</head>
<body onload="window.print()">
@yield('corpo')
</body>
</html>
