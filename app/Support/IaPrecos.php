<?php

namespace App\Support;

/**
 * Tabela de preços das IAs (US$ por 1 milhão de tokens) pra estimar o custo do agente.
 * É uma referência: o administrador pode corrigir o preço de cada modelo na tela "Custo da IA".
 * Cada linha: trecho do nome do modelo => [entrada, saída, leitura de cache]. A primeira que casar vale.
 */
class IaPrecos
{
    public const DOLAR_PADRAO = 5.50;

    public const TABELA = [
        // Claude (Anthropic)
        'haiku-5-5' => [0.10, 0.50, 0.01],
        'haiku' => [1.00, 5.00, 0.10],
        'sonnet-5-5' => [2.00, 10.00, 0.10],
        'sonnet-5' => [2.00, 10.00, 0.20],
        'sonnet' => [3.00, 15.00, 0.30],
        'opus-5-5' => [4.00, 20.00, 0.20],
        'opus' => [5.00, 25.00, 0.50],
        'fable-5-2' => [8.00, 40.00, 0.20],
        'mythos-5-2' => [8.00, 40.00, 0.20],
        'fable' => [10.00, 50.00, 0.25],
        'mythos' => [10.00, 50.00, 0.25],
        // Gemini (Google)
        'gemini-4' => [2.00, 10.00, 0.20],
        '3-8-flash' => [0.75, 3.75, 0.075],
        '3-6-flash' => [0.75, 3.75, 0.075],
        '3-5-flash-lite' => [0.30, 2.50, 0.03],
        '3-1-flash-lite' => [0.25, 1.50, 0.025],
        '3-1-pro' => [2.00, 12.00, 0.20],
        '3-flash' => [0.50, 3.00, 0.05],
        '2-5-pro' => [1.25, 10.00, 0.125],
        '2-5-flash-lite' => [0.10, 0.40, 0.01],
        '2-5-flash' => [0.30, 2.50, 0.03],
        // OpenAI
        'gpt-6-2-astra' => [8.00, 40.00, 0.40],
        'gpt-6-astra' => [10.00, 50.00, 1.00],
        'gpt-6-2-sol' => [2.00, 10.00, 0.10],
        'gpt-6-1-sol' => [2.00, 10.00, 0.10],
        'gpt-5-nano' => [0.05, 0.40, 0.005],
        'gpt-5-mini' => [0.25, 2.00, 0.025],
        'gpt-5' => [1.25, 10.00, 0.125],
        'gpt-4-1-nano' => [0.10, 0.40, 0.025],
        'gpt-4-1-mini' => [0.40, 1.60, 0.10],
        'gpt-4-1' => [2.00, 8.00, 0.50],
        'gpt-4o-mini' => [0.15, 0.60, 0.075],
        'gpt-4o' => [2.50, 10.00, 1.25],
    ];

    /**
     * Preço de um modelo. $ajustes = preços corrigidos pelo administrador ([modelo => ['in'=>..,'out'=>..]]).
     * @return array{in:float,out:float,cache_r:float,cache_w:float,conhecido:bool,ajustado:bool}
     */
    public static function de(string $modelo, array $ajustes = []): array
    {
        $aj = $ajustes[$modelo] ?? null;
        if (is_array($aj) && ((float) ($aj['in'] ?? 0) > 0 || (float) ($aj['out'] ?? 0) > 0)) {
            $in = (float) ($aj['in'] ?? 0);
            return ['in' => $in, 'out' => (float) ($aj['out'] ?? 0), 'cache_r' => round($in * 0.1, 4), 'cache_w' => round($in * 1.25, 4), 'conhecido' => true, 'ajustado' => true];
        }
        $norm = str_replace('.', '-', mb_strtolower($modelo));
        foreach (self::TABELA as $trecho => $p) {
            if (str_contains($norm, $trecho)) {
                return ['in' => $p[0], 'out' => $p[1], 'cache_r' => $p[2], 'cache_w' => round($p[0] * 1.25, 4), 'conhecido' => true, 'ajustado' => false];
            }
        }
        return ['in' => 0.0, 'out' => 0.0, 'cache_r' => 0.0, 'cache_w' => 0.0, 'conhecido' => false, 'ajustado' => false];
    }

    /** Custo em US$ de um consumo ($tok = in/out/cache_r/cache_w em tokens). */
    public static function custoUsd(array $tok, array $preco): float
    {
        return ((int) ($tok['in'] ?? 0) * $preco['in']
            + (int) ($tok['out'] ?? 0) * $preco['out']
            + (int) ($tok['cache_r'] ?? 0) * $preco['cache_r']
            + (int) ($tok['cache_w'] ?? 0) * $preco['cache_w']) / 1000000;
    }
}
