<?php

namespace App\Console\Commands;

use App\Models\Embarcacao;
use App\Models\Empresa;
use App\Models\Encomenda;
use App\Services\WhatsApp;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Avisa o destinatário da encomenda no WhatsApp quando o barco está a ~1h da
 * cidade de destino. Usa a URL de rastreio cadastrada em cada embarcação
 * (ex.: https://rionave.com/wp-json/riotag/v1/posicao/TOKEN), que devolve
 * "cidades_a_frente" com o tempo que falta (falta_s) para cada cidade.
 */
class AvisarChegadaEncomenda extends Command
{
    protected $signature = 'encomendas:avisar-chegada {--minutos=60 : Avisar quando faltar este tempo ou menos}';
    protected $description = 'Avisa o cliente da encomenda ~1h antes do barco chegar no destino (via WhatsApp)';

    public function handle(WhatsApp $wa): int
    {
        $limiteSeg = max(5, (int) $this->option('minutos')) * 60;
        $enviados = 0;

        $barcos = Embarcacao::withoutGlobalScopes()
            ->where('ativo', true)
            ->whereNotNull('rastreio_url')
            ->where('rastreio_url', '!=', '')
            ->get();

        foreach ($barcos as $barco) {
            $pendentes = Encomenda::withoutGlobalScopes()
                ->where('embarcacao_id', $barco->id)
                ->where('entregue', false)
                ->whereNull('avisado_chegada_em')
                ->whereNotNull('destinatario_telefone')
                ->where('destinatario_telefone', '!=', '')
                ->get();

            if ($pendentes->isEmpty()) {
                continue;
            }

            // consulta a API do barco uma vez só
            try {
                $resp = Http::timeout(12)->acceptJson()->get($barco->rastreio_url);
            } catch (\Throwable $e) {
                $this->warn("Barco {$barco->nome}: falha ao consultar rastreio — {$e->getMessage()}");
                continue;
            }
            if (! $resp->ok()) {
                continue;
            }
            $j = $resp->json();
            if (! is_array($j) || empty($j['ok'])) {
                continue;
            }

            // mapa cidade(minúsculo) => dados do trecho (falta_s, hora prevista)
            $mapa = [];
            foreach ((array) ($j['cidades_a_frente'] ?? []) as $c) {
                $cidade = mb_strtolower(trim((string) ($c['cidade'] ?? '')));
                if ($cidade !== '') {
                    $mapa[$cidade] = $c;
                }
            }
            if (! $mapa) {
                continue;
            }
            $barcoNome = (string) ($j['barco'] ?? $barco->nome);

            foreach ($pendentes as $enc) {
                $info = $mapa[mb_strtolower(trim((string) $enc->destino))] ?? null;
                if (! $info) {
                    continue; // barco ainda não tem essa cidade à frente (ou já passou)
                }
                $falta = (int) ($info['falta_s'] ?? PHP_INT_MAX);
                if ($falta <= 0 || $falta > $limiteSeg) {
                    continue; // só quando falta <= limite (padrão 1h)
                }

                Tenant::set($enc->empresa_id);
                $empresa = Empresa::withoutGlobalScopes()->find($enc->empresa_id);
                $conexao = $empresa?->whatsappConexao('encomenda');
                if (! $conexao) {
                    continue; // empresa sem WhatsApp configurado para encomenda
                }

                $hora = trim((string) ($info['hora_local_cidade'] ?? ''));
                $msg = "📦 Olá, {$enc->destinatario_nome}!\n"
                    ."Sua encomenda Nº {$enc->numeroFormatado()} está chegando em {$enc->destino} em cerca de 1 hora"
                    .($hora !== '' ? " (previsão {$hora})" : '').".\n"
                    ."Barco: {$barcoNome}.\n"
                    .($empresa?->nomeExibicao() ?? 'FBRE');

                $r = $wa->enviarTexto($conexao, (string) $enc->destinatario_telefone, $msg);
                if (! empty($r['ok'])) {
                    Encomenda::withoutGlobalScopes()->whereKey($enc->id)->update(['avisado_chegada_em' => now()]);
                    $enviados++;
                }
            }
        }

        $this->info("Avisos de chegada enviados: {$enviados}");

        return self::SUCCESS;
    }
}
