#!/usr/bin/env python3
"""
FBRE Print Agent — imprime comprovantes e etiquetas direto na térmica, sem navegador.

Roda no PC do cliente (Windows). Consulta fbre.com.br a cada 2 s, pega os jobs da
estação (token) e envia ESC/POS para a impressora (USB compartilhada, IP:9100 ou nome Windows).

Config: fbre_agent.ini ao lado do executável (criado no primeiro uso).
Empacotar: pyinstaller --onefile --noconsole --name FBREPrintAgent fbre_print_agent.py
"""
import configparser
import json
import os
import socket
import sys
import threading
import time
import urllib.request
import urllib.error

APP = "FBRE Print Agent"
BASE = os.path.dirname(os.path.abspath(sys.argv[0]))
INI = os.path.join(BASE, "fbre_agent.ini")
LOG = os.path.join(BASE, "fbre_agent.log")

DEFAULT_INI = """[fbre]
servidor = https://fbre.com.br
token = COLE_AQUI_O_TOKEN_DA_ESTACAO
intervalo = 2

[impressora_comprovante]
# tipo: rede | windows | arquivo
tipo = rede
host = 192.168.0.50
porta = 9100
nome_windows = POS-80

[impressora_etiqueta]
tipo = rede
host = 192.168.0.50
porta = 9100
nome_windows = POS-80

[escpos]
colunas = 48
cortar = 1
"""

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
        with open(INI, "w", encoding="utf-8") as f:
            f.write(DEFAULT_INI)
        log(f"Config criada em {INI}. Edite o token e a impressora e reinicie.")
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
        n = {1: 0x00, 2: 0x11, 3: 0x22}.get(size, 0x00)
        self.buf += self.GS + b"!" + bytes([n])
        self.buf += s.encode("cp1252", "replace") + b"\n"
        self.buf += self.GS + b"!\x00" + self.ESC + b"E\x00"

    def lr(self, esq, dir_, bold=False):
        esq, dir_ = str(esq), str(dir_)
        esp = max(self.col - len(esq) - len(dir_), 1)
        self.txt(esq + " " * esp + dir_, bold=bold)

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
    p.lr("ORIGEM", e["origem"], True)
    p.lr("DESTINO", e["destino"], True)
    if e.get("embarcacao"): p.lr("TRANSPORTE", e["embarcacao"])
    p.lr("VOLUMES", e["volumes"])
    p.linha()
    p.txt("REMETENTE", bold=True); p.txt(e["remetente"]); p.txt(e.get("remetente_tel") or "")
    p.txt("DESTINATARIO", bold=True); p.txt(e["destinatario"]); p.txt(e.get("destinatario_tel") or "")
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
    tipo = cfg_sec.get("tipo", "rede")
    if tipo == "rede":
        with socket.create_connection((cfg_sec["host"], int(cfg_sec.get("porta", 9100))), timeout=10) as s:
            s.sendall(dados)
    elif tipo == "windows":
        import win32print  # pywin32
        h = win32print.OpenPrinter(cfg_sec["nome_windows"])
        try:
            win32print.StartDocPrinter(h, 1, ("FBRE", None, "RAW"))
            win32print.StartPagePrinter(h); win32print.WritePrinter(h, dados); win32print.EndPagePrinter(h)
            win32print.EndDocPrinter(h)
        finally:
            win32print.ClosePrinter(h)
    elif tipo == "arquivo":
        with open(os.path.join(BASE, f"saida_{int(time.time()*1000)}.bin"), "wb") as f:
            f.write(dados)
    else:
        raise RuntimeError(f"tipo de impressora desconhecido: {tipo}")


# ---------------------------------------------------------------- API
class Api:
    def __init__(self, servidor, token):
        self.base = servidor.rstrip("/") + "/api/print"
        self.token = token

    def _req(self, metodo, caminho, corpo=None):
        req = urllib.request.Request(self.base + caminho, method=metodo)
        req.add_header("X-Estacao-Token", self.token)
        req.add_header("Accept", "application/json")
        data = None
        if corpo is not None:
            data = json.dumps(corpo).encode()
            req.add_header("Content-Type", "application/json")
        with urllib.request.urlopen(req, data, timeout=15) as r:
            return json.loads(r.read().decode())

    def ping(self):  return self._req("GET", "/ping")
    def next(self):  return self._req("GET", "/next")["jobs"]
    def done(self, j): return self._req("POST", f"/{j}/done", {})
    def error(self, j, msg): return self._req("POST", f"/{j}/error", {"erro": msg})


# ---------------------------------------------------------------- loop
def loop(cfg, parar: threading.Event):
    api = Api(cfg["fbre"]["servidor"], cfg["fbre"]["token"])
    col = int(cfg["escpos"].get("colunas", 48))
    intervalo = float(cfg["fbre"].get("intervalo", 2))
    try:
        info = api.ping(); log(f"Conectado: {info['empresa']} / estação {info['estacao']}")
    except Exception as ex:
        log(f"Falha ao conectar: {ex}")
    while not parar.is_set():
        try:
            for job in api.next():
                try:
                    dados = job["dados"]
                    if job["tipo"] == "comprovante":
                        raw = montar_comprovante(dados, col); enviar(cfg["impressora_comprovante"], raw)
                    elif job["tipo"] == "etiqueta":
                        raw = montar_etiqueta(dados, col); enviar(cfg["impressora_etiqueta"], raw)
                    else:
                        raise RuntimeError("tipo não suportado: " + job["tipo"])
                    api.done(job["id"]); log(f"Impresso {job['tipo']} #{dados['encomenda']['numero']}")
                except Exception as ex:
                    log(f"Erro no job {job['id']}: {ex}")
                    try: api.error(job["id"], str(ex))
                    except Exception: pass
        except urllib.error.HTTPError as ex:
            log(f"HTTP {ex.code}: {ex.reason}"); time.sleep(10)
        except Exception as ex:
            log(f"Erro: {ex}"); time.sleep(5)
        parar.wait(intervalo)


def main():
    cfg = carregar_config()
    if "COLE_AQUI" in cfg["fbre"]["token"]:
        log("Configure o token em fbre_agent.ini");
        if os.name == "nt": os.startfile(INI)
        return
    parar = threading.Event()
    t = threading.Thread(target=loop, args=(cfg, parar), daemon=True); t.start()

    # ícone na bandeja (Windows) se pystray/Pillow estiverem disponíveis
    try:
        import pystray
        from PIL import Image, ImageDraw
        img = Image.new("RGB", (64, 64), "#0b1a33"); ImageDraw.Draw(img).rectangle((14, 20, 50, 44), fill="#00a8e8")
        menu = pystray.Menu(
            pystray.MenuItem("Abrir configuração", lambda: os.startfile(INI)),
            pystray.MenuItem("Ver log", lambda: os.startfile(LOG)),
            pystray.MenuItem("Sair", lambda icon: (parar.set(), icon.stop())),
        )
        pystray.Icon("fbre", img, APP, menu).run()
    except ImportError:
        log("Rodando em modo console (Ctrl+C para sair)")
        try:
            while True: time.sleep(1)
        except KeyboardInterrupt:
            parar.set()


if __name__ == "__main__":
    main()
