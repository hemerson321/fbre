# FBRE Print Agent

Imprime comprovantes e etiquetas direto na impressora térmica, sem janela do navegador.

## Instalar no PC do cliente (Windows)
1. Copie `FBREPrintAgent.exe` para `C:\FBRE\`
2. Execute uma vez: ele cria `fbre_agent.ini` e abre pra edição
3. Cole o **token da estação** (gerado em *Barcos, locais, produtos → Estações de impressão*)
4. Configure a impressora:
   - `tipo = rede` → impressora com IP (porta 9100)
   - `tipo = windows` → nome da impressora instalada no Windows (ex.: `POS-80`)
5. Execute de novo. Ícone aparece na bandeja. Para iniciar com o Windows, crie um atalho em
   `shell:startup`.

## Gerar o .exe (uma vez, no seu PC)
```
pip install pyinstaller pystray pillow pywin32
pyinstaller --onefile --noconsole --name FBREPrintAgent fbre_print_agent.py
```

## Testar sem impressora
`tipo = arquivo` grava o ESC/POS em `saida_*.bin` na pasta do agente.
