<?php

namespace App\Console\Commands;

use App\Models\Embarcacao;
use App\Models\Empresa;
use App\Models\Encomenda;
use App\Services\RastreioBarco;
use App\Services\WhatsApp;
use App\Support\Tenant;
use Illuminate\Console\Command;

/**
 * Avisa o destinatário da encomenda no WhatsApp quando o barco está a ~1h da
 * cidade de destino. Usa a URL de rastreio cadastrada em cada embarcação
 * (ex.: https://rionave.com/wp-json/riotag/v1/posicao/TOKEN).
 */
class AvisarChegadaEncomenda extends Command
{
    protected $signature = 'encomendas:avisar-chegada {--minutos=60 : Avisar quando faltar este tempo ou menos}';
    protected $description = 'Avisa o cliente da encomenda ~1h antes do barco chegar no destino (via WhatsApp)';

    public function handle(WhatsApp $wa, RastreioBarco $rastreio): int
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

            foreach ($pendentes as $enc) {
                $info = $rastreio->trechoAte($barco, (string) $enc->destino);
                if (! $info) {
                    continue; // cidade não está à frente (já passou ou fora da rota)
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
                $barcoNome = (string) ($info['barco'] ?? $barco->nome);
                $empresaNome = $empresa?->nomeExibicao() ?? 'FBRE';
                $barcoLinha = (mb_strtolower(trim($barcoNome)) !== mb_strtolower(trim($empresaNome))) ? "Barco: {$barcoNome}.\n" : '';
                $msg = "📦 Olá, {$enc->destinatario_nome}!\n"
                    ."Sua encomenda Nº {$enc->numeroFormatado()} está chegando em {$enc->destino} em cerca de 1 hora"
                    .($hora !== '' ? " (previsão {$hora})" : '').".\n"
                    .$barcoLinha
                    .'Acompanhe o barco no mapa: https://rionave.com/?b='.rawurlencode($barcoNome)."\n"
                    ."{$empresaNome}\n"
                    ."\n⚠️ Mensagem automática, não responda por aqui.";

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
