#!/usr/bin/env python3
"""
Agente de Impressão — imprime comprovantes e etiquetas direto na térmica, sem navegador.

Roda no PC do cliente (Windows). Consulta fbre.com.br a cada 2 s, pega os jobs da
estação (token) e envia ESC/POS para a impressora (USB compartilhada, IP:9100 ou nome Windows).

Config: fbre_agent.ini ao lado do executável (criado no primeiro uso).
Empacotar: pyinstaller --onefile --noconsole --name agenteprint fbre_print_agent.py
"""
import configparser
import json
import os
import socket
import subprocess
import sys
import threading
import time
import urllib.request
import urllib.error

APP = "Agente de Impressão"
# config/log numa pasta gravável (o instalador põe o .exe em Program Files, que é só leitura)
DATA = os.path.join(os.environ.get("PROGRAMDATA", os.path.expanduser("~")), "AgentePrint")
try:
    os.makedirs(DATA, exist_ok=True)
except Exception:
    DATA = os.path.dirname(os.path.abspath(sys.argv[0]))
INI = os.path.join(DATA, "fbre_agent.ini")
LOG = os.path.join(DATA, "fbre_agent.log")

INI_TEMPLATE = """[fbre]
servidor = https://fbre.com.br
token = COLE_AQUI_O_TOKEN_DA_ESTACAO
intervalo = 2

# Impressoras instaladas neste computador:
{lista}
#
# nome_windows = padrao  -> usa a impressora PADRAO do Windows (a mesma que o operador usa)
# ou troque por um nome exato da lista acima.

[impressora_comprovante]
# tipo: windows (impressora instalada) | rede (IP:9100) | arquivo (teste)
tipo = windows
nome_windows = padrao

[impressora_etiqueta]
tipo = windows
nome_windows = padrao

[escpos]
colunas = 48
cortar = 1
"""


def listar_impressoras():
    """Nomes das impressoras instaladas no Windows + a padrao."""
    try:
        import win32print
        flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
        nomes = [p[2] for p in win32print.EnumPrinters(flags)]
        try:
            padrao = win32print.GetDefaultPrinter()
        except Exception:
            padrao = None
        return nomes, padrao
    except Exception:
        return [], None

# ---------------------------------------------------------------- util
def log(msg):
    linha = time.strftime("%d/%m %H:%M:%S ") + msg
    print(linha)
    try:
        with open(LOG, "a", encoding="utf-8") as f:
            f.write(linha + "\n")
    except Exception:
        pass


def carregar_config():
    if not os.path.exists(INI):
        nomes, padrao = listar_impressoras()
        lista = "\n".join("#   - " + n for n in nomes) if nomes else "#   (nenhuma detectada)"
        with open(INI, "w", encoding="utf-8") as f:
            f.write(INI_TEMPLATE.format(lista=lista))
        log(f"Config criada em {INI}.")
        if padrao:
            log(f"Impressora padrao do Windows: {padrao} (sera usada automaticamente).")
        log("Falta colar o TOKEN da estacao no arquivo e reiniciar.")
    cfg = configparser.ConfigParser()
    cfg.read(INI, encoding="utf-8")
    return cfg


def brl(v):
    return "R$ " + f"{float(v or 0):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")


# ---------------------------------------------------------------- ESC/POS
class EscPos:
    ESC, GS = b"\x1b", b"\x1d"

    def __init__(self, colunas=48):
        self.col = colunas
        self.buf = bytearray()
        self.buf += self.ESC + b"@"            # reset
        self.buf += self.ESC + b"t\x10"        # codepage CP1252 (acentos)

    def txt(self, s="", align="l", bold=False, size=1):
        a = {"l": 0, "c": 1, "r": 2}[align]
        self.buf += self.ESC + b"a" + bytes([a])
        self.buf += self.ESC + b"E" + (b"\x01" if bold else b"\x00")
        n = {1: 0x00, 2: 0x11, 3: 0x22, 4: 0x01}.get(size, 0x00)  # 4 = só dobra a altura (não alarga, não quebra)
        self.buf += self.GS + b"!" + bytes([n])
        self.buf += s.encode("cp1252", "replace") + b"\n"
        self.buf += self.GS + b"!\x00" + self.ESC + b"E\x00"

    def lr(self, esq, dir_, bold=False):
        esq, dir_ = str(esq), str(dir_)
        esp = max(self.col - len(esq) - len(dir_), 1)
        self.txt(esq + " " * esp + dir_, bold=bold)

    def col2(self, esq, dir_, bold=False):
        """Duas colunas lado a lado (metade da largura cada), cortando se passar."""
        w = self.col // 2
        e = str(esq)[:w - 1].ljust(w)
        d = str(dir_)[:w - 1]
        self.txt(e + d, bold=bold)

    def linha(self, ch="-"):
        self.txt(ch * self.col)

    def feed(self, n=3):
        self.buf += b"\n" * n

    def cortar(self):
        self.buf += self.GS + b"V\x41\x03"

    def bytes(self):
        return bytes(self.buf)


def montar_comprovante(d, col):
    e, emp = d["encomenda"], d["empresa"]
    p = EscPos(col)
    p.txt(emp["nome"].upper(), "c", True, 2)
    if emp.get("cnpj"): p.txt(f"CNPJ {emp['cnpj']}", "c")
    tel = " ".join(x for x in [emp.get("telefone"), emp.get("whatsapp") and f"WhatsApp {emp['whatsapp']}"] if x)
    if tel: p.txt(tel, "c")
    p.linha()
    p.txt(f"ENCOMENDA No {e['numero']}", "c", True, 2)
    p.txt(f"{e['data']}  Op: {e.get('operador') or ''}", "c")
    p.linha()
    # Rota em destaque: origem e destino lado a lado, letra maior (só mais alta, não quebra)
    p.txt("ORIGEM  >  DESTINO", "c")
    p.txt(f"{e['origem']}  >  {e['destino']}", "c", True)
    if e.get("embarcacao"): p.lr("TRANSPORTE", e["embarcacao"])
    p.lr("VOLUMES", e["volumes"])
    p.linha()
    p.col2("REMETENTE", "DESTINATARIO", True)
    p.col2(e["remetente"], e["destinatario"])
    if (e.get("remetente_tel") or e.get("destinatario_tel")):
        p.col2(e.get("remetente_tel") or "", e.get("destinatario_tel") or "")
    p.linha()
    for it in e["itens"]:
        p.txt(it["descricao"])
        p.lr(f"  {it['qtd']:g} x {brl(it['unit'])}", brl(it["total"]))
    p.linha()
    if e["desconto"] > 0:
        p.lr("Subtotal", brl(e["subtotal"])); p.lr("Desconto", "-" + brl(e["desconto"]))
    p.lr("TOTAL", brl(e["total"]), True)
    p.lr("Pago", brl(e["pago"]))
    p.lr("DEVIDO", brl(e["devido"]), True)
    p.lr("Forma", e["forma"])
    p.linha()
    p.txt(e["status"].upper(), "c", True)
    if e.get("observacao"): p.linha(); p.txt("Obs: " + e["observacao"])
    if emp.get("pix"): p.linha(); p.txt("PIX: " + emp["pix"], "c")
    if emp.get("rodape"): p.linha(); p.txt(emp["rodape"], "c")
    p.linha()
    p.txt("Apresente este comprovante para retirar.", "c")
    p.txt("fbre.com.br", "c")
    p.feed(4); p.cortar()
    return p.bytes()


def montar_etiqueta(d, col):
    e, emp = d["encomenda"], d["empresa"]
    out = bytearray()
    total = int(e["volumes"])
    for v in range(1, total + 1):
        p = EscPos(col)
        p.txt(emp["nome"].upper(), "c", True)
        p.txt(f"No {e['numero']}", "c", True, 3)
        p.txt(f"VOLUME {v}/{total}", "c", True, 2)
        p.linha("=")
        p.txt(f"DESTINO: {e['destino'].upper()}", bold=True, size=2)
        p.txt(f"Origem: {e['origem']}" + (f" - {e['embarcacao']}" if e.get("embarcacao") else ""))
        p.linha()
        p.txt(f"PARA: {e['destinatario'].upper()}", bold=True)
        p.txt(e.get("destinatario_tel") or "")
        p.txt(f"DE: {e['remetente']} {e.get('remetente_tel') or ''}")
        p.linha()
        p.lr(e["data"], ("A RECEBER " + brl(e["devido"])) if e["devido"] > 0 else "PAGO", True)
        p.feed(3); p.cortar()
        out += p.bytes()
    return bytes(out)


# ---------------------------------------------------------------- saída
def enviar(cfg_sec, dados: bytes):
    tipo = cfg_sec.get("tipo", "windows")
    if tipo == "rede":
        host = cfg_sec.get("host")
        if not host:
            raise RuntimeError("tipo=rede exige 'host' (IP da impressora) no fbre_agent.ini")
        with socket.create_connection((host, int(cfg_sec.get("porta", 9100))), timeout=10) as s:
            s.sendall(dados)
    elif tipo == "windows":
        import win32print  # pywin32
        nome = (cfg_sec.get("nome_windows") or "").strip()
        if not nome or nome.lower() in ("padrao", "padrão", "default", "auto"):
            nome = win32print.GetDefaultPrinter()   # mesma impressora que o operador usa
        h = win32print.OpenPrinter(nome)
        try:
            win32print.StartDocPrinter(h, 1, ("Agente de Impressão", None, "RAW"))
            win32print.StartPagePrinter(h); win32print.WritePrinter(h, dados); win32print.EndPagePrinter(h)
            win32print.EndDocPrinter(h)
        finally:
            win32print.ClosePrinter(h)
    elif tipo == "arquivo":
        with open(os.path.join(DATA, f"saida_{int(time.time()*1000)}.bin"), "wb") as f:
            f.write(dados)
    else:
        raise RuntimeError(f"tipo de impressora desconhecido: {tipo}")


# ---------------------------------------------------------------- API
# Contexto TLS que não depende do pacote de CAs embutido no .exe (que pode
# estar vencido em máquinas antigas). O agente fala só com o servidor do
# próprio cliente, autenticado por token, então não verificamos a cadeia.
import ssl as _ssl
_SSL_CTX = _ssl.create_default_context()
_SSL_CTX.check_hostname = False
_SSL_CTX.verify_mode = _ssl.CERT_NONE


class Api:
    def __init__(self, servidor, token):
        self.base = (servidor or "").strip().rstrip("/") + "/api/print"
        self.token = (token or "").strip()

    def _req(self, metodo, caminho, corpo=None):
        req = urllib.request.Request(self.base + caminho, method=metodo)
        req.add_header("X-Estacao-Token", self.token)
        req.add_header("Accept", "application/json")
        data = None
        if corpo is not None:
            data = json.dumps(corpo).encode()
            req.add_header("Content-Type", "application/json")
        with urllib.request.urlopen(req, data, timeout=15, context=_SSL_CTX) as r:
            return json.loads(r.read().decode())

    def ping(self):  return self._req("GET", "/ping")
    def next(self):  return self._req("GET", "/next")["jobs"]
    def done(self, j): return self._req("POST", f"/{j}/done", {})
    def error(self, j, msg): return self._req("POST", f"/{j}/error", {"erro": msg})


# ---------------------------------------------------------------- loop (recarrega config a cada ciclo)
def loop(parar: threading.Event):
    api = None; token_atual = None; colunas_srv = None
    while not parar.is_set():
        cfg = carregar_config()
        token = (cfg["fbre"].get("token", "") or "").strip()
        servidor = (cfg["fbre"].get("servidor", "https://fbre.com.br") or "").strip()
        # largura do papel: o servidor manda (por estação); se não vier, usa a do arquivo
        col = colunas_srv or int(cfg["escpos"].get("colunas", 48))
        intervalo = float(cfg["fbre"].get("intervalo", 2))
        if "COLE_AQUI" in token or not token:
            parar.wait(3); continue                       # ainda sem token
        if api is None or token != token_atual:
            api = Api(servidor, token); token_atual = token
            try:
                info = api.ping(); colunas_srv = int(info.get("colunas") or 0) or None
                if colunas_srv: col = colunas_srv
                log(f"Conectado: {info['empresa']} / estação {info['estacao']} ({col} col)")
            except Exception as ex:
                log(f"Falha ao conectar: {ex}")
        try:
            for job in api.next():
                try:
                    dados = job["dados"]
                    if job["tipo"] == "comprovante":
                        raw = montar_comprovante(dados, col); enviar(cfg["impressora_comprovante"], raw)
                    elif job["tipo"] == "etiqueta":
                        if (cfg["impressora_etiqueta"].get("nome_windows", "") or "").upper() == "NAO_IMPRIMIR":
                            api.done(job["id"]); log(f"Etiqueta #{dados['encomenda']['numero']} ignorada (etiqueta desativada)"); continue
                        raw = montar_etiqueta(dados, col); enviar(cfg["impressora_etiqueta"], raw)
                    else:
                        raise RuntimeError("tipo não suportado: " + job["tipo"])
                    api.done(job["id"]); log(f"Impresso {job['tipo']} #{dados['encomenda']['numero']}")
                except Exception as ex:
                    log(f"Erro no job {job['id']}: {ex}")
                    try: api.error(job["id"], str(ex))
                    except Exception: pass
        except urllib.error.HTTPError as ex:
            log(f"HTTP {ex.code}: {ex.reason}"); parar.wait(10); continue
        except Exception as ex:
            log(f"Erro: {ex}"); parar.wait(5); continue
        parar.wait(intervalo)


# ---------------------------------------------------------------- configuração (ini + janela)
def salvar_config(v):
    cfg = configparser.ConfigParser()
    if os.path.exists(INI): cfg.read(INI, encoding="utf-8")
    for sec in ("fbre", "impressora_comprovante", "impressora_etiqueta", "escpos"):
        if not cfg.has_section(sec): cfg.add_section(sec)
    cfg["fbre"]["servidor"] = v.get("servidor") or "https://fbre.com.br"
    cfg["fbre"]["token"] = v.get("token", "")
    if not cfg["fbre"].get("intervalo"): cfg["fbre"]["intervalo"] = "2"
    for sec, key in (("impressora_comprovante", "comprovante"), ("impressora_etiqueta", "etiqueta")):
        cfg[sec]["tipo"] = "windows"
        cfg[sec]["nome_windows"] = v.get(key, "padrao")
    if not cfg["escpos"].get("colunas"): cfg["escpos"]["colunas"] = "48"
    if not cfg["escpos"].get("cortar"): cfg["escpos"]["cortar"] = "1"
    with open(INI, "w", encoding="utf-8") as f: cfg.write(f)


def janela_config():
    import tkinter as tk
    from tkinter import ttk, messagebox

    nomes, _ = listar_impressoras()
    PADRAO = "Padrão do Windows"
    NAO = "(não imprimir)"
    opcoes = [PADRAO] + nomes
    opcoes_etq = [NAO, PADRAO] + nomes  # etiqueta pode ser desligada (ex.: ainda sem etiquetadora)

    cfg = configparser.ConfigParser()
    if os.path.exists(INI): cfg.read(INI, encoding="utf-8")
    g = lambda s, k, d="": cfg[s].get(k, d) if cfg.has_section(s) else d
    tok = g("fbre", "token"); tok = "" if "COLE_AQUI" in tok else tok
    srv = g("fbre", "servidor", "https://fbre.com.br")
    comp = g("impressora_comprovante", "nome_windows", "padrao")
    etq = g("impressora_etiqueta", "nome_windows", "padrao")

    def sel(atual):
        if (atual or "padrao").lower() == "padrao": return PADRAO
        return atual if atual in nomes else PADRAO

    def sel_etq(atual):
        if (atual or "").upper() == "NAO_IMPRIMIR": return NAO
        return sel(atual)

    def val(c):
        return "padrao" if c.get() == PADRAO else c.get()

    def val_etq(c):
        if c.get() == NAO: return "NAO_IMPRIMIR"
        return val(c)

    root = tk.Tk(); root.title("Agente de Impressão — Configuração"); root.resizable(False, False)
    frm = ttk.Frame(root, padding=16); frm.grid()
    ttk.Label(frm, text="Agente de Impressão", font=("Segoe UI", 15, "bold")).grid(column=0, row=0, columnspan=2, sticky="w")
    ttk.Label(frm, text="Impressão direta de comprovantes e etiquetas.").grid(column=0, row=1, columnspan=2, sticky="w", pady=(0, 12))

    ttk.Label(frm, text="Token da estação:").grid(column=0, row=2, sticky="w", pady=4)
    e_tok = ttk.Entry(frm, width=48); e_tok.insert(0, tok); e_tok.grid(column=1, row=2, pady=4)
    ttk.Label(frm, text="Servidor:").grid(column=0, row=3, sticky="w", pady=4)
    e_srv = ttk.Entry(frm, width=48); e_srv.insert(0, srv); e_srv.grid(column=1, row=3, pady=4)

    ttk.Label(frm, text="Impressora do comprovante:").grid(column=0, row=4, sticky="w", pady=4)
    c_comp = ttk.Combobox(frm, width=45, values=opcoes, state="readonly"); c_comp.set(sel(comp)); c_comp.grid(column=1, row=4, pady=4)
    ttk.Label(frm, text="Impressora da etiqueta:").grid(column=0, row=5, sticky="w", pady=4)
    c_etq = ttk.Combobox(frm, width=45, values=opcoes_etq, state="readonly"); c_etq.set(sel_etq(etq)); c_etq.grid(column=1, row=5, pady=4)
    ttk.Label(frm, text="Sem etiquetadora? Escolha \"(não imprimir)\" na etiqueta.", foreground="#888").grid(column=1, row=6, sticky="w")

    def coletar():
        return {"token": e_tok.get().strip(), "servidor": e_srv.get().strip(),
                "comprovante": val(c_comp), "etiqueta": val_etq(c_etq)}

    def salvar():
        if not e_tok.get().strip():
            messagebox.showwarning("Agente de Impressão", "Cole o token da estação (gerado no sistema em Cadastros gerais → Estações).")
            return
        salvar_config(coletar())
        messagebox.showinfo("Agente de Impressão", "Configuração salva! O agente já passa a usar essas impressoras.")
        root.destroy()

    def testar(qual):
        try:
            salvar_config(coletar())
            cfg2 = configparser.ConfigParser(); cfg2.read(INI, encoding="utf-8")
            if qual == "etiqueta":
                if (cfg2["impressora_etiqueta"].get("nome_windows", "") or "").upper() == "NAO_IMPRIMIR":
                    messagebox.showinfo("Agente de Impressão", "Etiqueta está em \"(não imprimir)\". Escolha uma impressora pra testar.")
                    return
                p = EscPos(48); p.txt("TESTE ETIQUETA", "c", True, 2); p.txt("Agente de Impressão", "c"); p.feed(2); p.cortar()
                enviar(cfg2["impressora_etiqueta"], p.bytes())
            else:
                p = EscPos(48); p.txt("TESTE COMPROVANTE", "c", True, 2); p.txt("Agente de Impressão OK", "c"); p.feed(3); p.cortar()
                enviar(cfg2["impressora_comprovante"], p.bytes())
            messagebox.showinfo("Agente de Impressão", "Enviado para a impressora. Confira o papel.")
        except Exception as ex:
            messagebox.showerror("Agente de Impressão", f"Falha no teste: {ex}")

    bar = ttk.Frame(frm); bar.grid(column=0, row=7, columnspan=2, pady=(14, 0), sticky="e")
    ttk.Button(bar, text="Testar comprovante", command=lambda: testar("comprovante")).grid(column=0, row=0, padx=4)
    ttk.Button(bar, text="Testar etiqueta", command=lambda: testar("etiqueta")).grid(column=1, row=0, padx=4)
    ttk.Button(bar, text="Salvar", command=salvar).grid(column=2, row=0, padx=4)
    try: root.eval('tk::PlaceWindow . center')
    except Exception: pass
    root.mainloop()


def _abrir_config():
    """Abre a janela de config num processo separado (não trava a bandeja)."""
    try:
        if getattr(sys, "frozen", False):
            subprocess.Popen([sys.executable, "--config"])
        else:
            subprocess.Popen([sys.executable, os.path.abspath(sys.argv[0]), "--config"])
    except Exception as ex:
        log(f"Falha ao abrir config: {ex}")


def main():
    if "--config" in sys.argv:
        janela_config(); return

    carregar_config()  # cria o ini se faltar
    cfg = configparser.ConfigParser(); cfg.read(INI, encoding="utf-8")
    tok = (cfg["fbre"].get("token", "") if cfg.has_section("fbre") else "")
    if "COLE_AQUI" in tok or not tok.strip():
        janela_config()  # primeira vez: abre a configuração (bloqueia até salvar/fechar)

    parar = threading.Event()
    threading.Thread(target=loop, args=(parar,), daemon=True).start()

    try:
        import pystray
        from PIL import Image, ImageDraw
        img = Image.new("RGB", (64, 64), "#0b1a33"); ImageDraw.Draw(img).rectangle((14, 20, 50, 44), fill="#00a8e8")
        menu = pystray.Menu(
            pystray.MenuItem("Configurações (impressoras e token)", lambda: _abrir_config()),
            pystray.MenuItem("Ver log", lambda: os.startfile(LOG) if os.path.exists(LOG) else None),
            pystray.MenuItem("Sair", lambda icon: (parar.set(), icon.stop())),
        )
        pystray.Icon("fbre", img, APP, menu).run()
    except ImportError:
        log("Modo console (Ctrl+C para sair)")
        try:
            while True: time.sleep(1)
        except KeyboardInterrupt:
            parar.set()


if __name__ == "__main__":
    main()
