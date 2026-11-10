<?php

namespace App\Support;

/**
 * Monta, no SERVIDOR, as "linhas" de um impresso térmico (ESC/POS) que o
 * Agente de Impressão executa cegamente (tipo 'generico' na fila).
 *
 * Assim o LAYOUT de cada impresso vive aqui (online) — criar um módulo novo ou
 * mudar um comprovante NÃO exige mexer no Agente instalado nos PCs.
 *
 * Uso:
 *   $doc = (new Recibo)->txt('EMPRESA','c',true,2)->linha()->lr('TOTAL','R$ 10',true)->cortar();
 *   FilaImpressao::create(['tipo'=>'generico','dados'=>['linhas'=>$doc->toArray(),'impressora'=>'comprovante','ref'=>'OP1-0006']]);
 */
class Recibo
{
    protected array $linhas = [];

    /** Texto. align: l|c|r · size: 1..3 (altura). */
    public function txt(string $t = '', string $align = 'l', bool $bold = false, int $size = 1): static
    {
        $this->linhas[] = ['op' => 'txt', 't' => $t, 'align' => $align, 'bold' => $bold, 'size' => $size];
        return $this;
    }

    /** Esquerda ... direita (rótulo + valor). */
    public function lr(string $esq, string $dir, bool $bold = false): static
    {
        $this->linhas[] = ['op' => 'lr', 'l' => $esq, 'r' => $dir, 'bold' => $bold];
        return $this;
    }

    /** Duas colunas lado a lado. */
    public function col2(string $esq, string $dir, bool $bold = false): static
    {
        $this->linhas[] = ['op' => 'col2', 'l' => $esq, 'r' => $dir, 'bold' => $bold];
        return $this;
    }

    /** Linha divisória. */
    public function linha(string $ch = '-'): static
    {
        $this->linhas[] = ['op' => 'linha', 'ch' => $ch];
        return $this;
    }

    /** Imagem (logo) em base64 (PNG/JPG) — o agente converte pra raster ESC/POS. */
    public function img(string $base64): static
    {
        $this->linhas[] = ['op' => 'img', 'data' => $base64];
        return $this;
    }

    /** QR code (ex.: URL de check-in / PIX copia-e-cola). */
    public function qr(string $data, int $size = 8): static
    {
        $this->linhas[] = ['op' => 'qr', 'data' => $data, 'size' => $size];
        return $this;
    }

    /** Avança papel. */
    public function feed(int $n = 3): static
    {
        $this->linhas[] = ['op' => 'feed', 'n' => $n];
        return $this;
    }

    /** Corta o papel (fim do impresso). */
    public function cortar(): static
    {
        $this->linhas[] = ['op' => 'cut'];
        return $this;
    }

    public function toArray(): array
    {
        return $this->linhas;
    }

    /** Dinheiro BRL simples: 1234.5 -> "R$ 1.234,50". */
    public static function brl($v): string
    {
        return 'R$ '.number_format((float) $v, 2, ',', '.');
    }
}
