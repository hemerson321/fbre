# FBRE Print Agent

Imprime comprovantes e etiquetas direto na impressora térmica, sem janela do navegador.

## Instalar (recomendado: instalador)
1. Baixe o **FBREPrintAgentSetup.exe** (GitHub → Actions → último build → Artifacts) e execute.
2. No fim da instalação abre a janela de **Configuração**: cole o **token da estação**
   (gerado em *Cadastros gerais → Estações de impressão*) e escolha a **impressora do comprovante**
   e a **impressora da etiqueta** (lista as instaladas no Windows; "Padrão do Windows" usa a padrão).
3. Pronto. O agente fica na **bandeja** (ao lado do relógio). Pra trocar de impressora depois,
   clique no ícone → **Configurações**. Já inicia junto com o Windows.

## Instalar no PC do cliente (Windows)
1. Copie `FBREPrintAgent.exe` para `C:\FBRE\`
2. Execute uma vez: ele cria o `fbre_agent.ini` **já apontando para a impressora padrão do
   Windows** (a mesma que o operador usa) e lista as impressoras instaladas no arquivo.
3. Cole o **token da estação** (gerado em *Cadastros gerais → Estações de impressão*).
4. Impressora: por padrão já vem `tipo = windows` e `nome_windows = padrao` (usa a impressora
   padrão do PC, para comprovante **e** etiqueta). Só mexa se quiser outra:
   - `nome_windows = <nome exato>` → uma impressora específica da lista no arquivo.
   - `tipo = rede` + `host = <IP>` → impressora de rede (porta 9100), se precisar.
5. Execute de novo. Ícone aparece na bandeja. Para iniciar com o Windows, crie um atalho em
   `shell:startup`.

## Gerar o .exe (uma vez, no seu PC)
```
pip install pyinstaller pystray pillow pywin32
pyinstaller --onefile --noconsole --name FBREPrintAgent fbre_print_agent.py
```

## Testar sem impressora
`tipo = arquivo` grava o ESC/POS em `saida_*.bin` na pasta do agente.
