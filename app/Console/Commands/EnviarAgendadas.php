<?php

namespace App\Console\Commands;

use App\Models\AtendAgendada;
use App\Models\Conversa;
use App\Models\WhatsappConexao;
use App\Services\Atendimento;
use App\Services\WhatsApp;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Envia as mensagens agendadas do atendimento (agendamento por conversa, funil de mensagens, envio em massa e status). */
class EnviarAgendadas extends Command
{
    protected $signature = 'atendimento:agendadas';
    protected $description = 'Envia mensagens agendadas / em massa / status do WhatsApp que já venceram';

    public function handle(Atendimento $at, WhatsApp $wa): int
    {
        $n = 0;
        // Poucas por rodada: envio em massa precisa ir devagar pra não bloquear o número.
        $lote = AtendAgendada::withoutGlobalScopes()->where('status', 'pendente')->where('enviar_em', '<=', now())
            ->orderBy('enviar_em')->orderBy('id')->limit(12)->get();
        foreach ($lote as $a) {
            // trava otimista: só um processo pega cada mensagem
            $pegou = AtendAgendada::withoutGlobalScopes()->whereKey($a->id)->where('status', 'pendente')->update(['status' => 'enviando']);
            if (! $pegou) continue;
            try {
                Tenant::set($a->empresa_id);
                $r = self::enviar($a, $at, $wa);
                AtendAgendada::withoutGlobalScopes()->whereKey($a->id)->update(($r['ok'] ?? false)
                    ? ['status' => 'enviada', 'enviada_em' => now(), 'erro' => null]
                    : ['status' => 'erro', 'erro' => mb_substr((string) ($r['erro'] ?? 'falha'), 0, 250)]);
                if ($r['ok'] ?? false) $n++;
            } catch (\Throwable $e) {
                AtendAgendada::withoutGlobalScopes()->whereKey($a->id)->update(['status' => 'erro', 'erro' => mb_substr($e->getMessage(), 0, 250)]);
                Log::warning('Agendada '.$a->id.': '.$e->getMessage());
            }
        }
        Tenant::set(null);
        $this->info("Enviadas: {$n}");
        return self::SUCCESS;
    }

    public static function enviar(AtendAgendada $a, Atendimento $at, WhatsApp $wa): array
    {
        $caminho = (string) $a->arquivo;
        $abs = $caminho !== '' ? storage_path('app/public/'.preg_replace('#^storage/#', '', $caminho)) : null;
        $temArq = $abs && is_file($abs);

        if ($a->tipo === 'status') {
            $cx = WhatsappConexao::withoutGlobalScopes()->where('empresa_id', $a->empresa_id)->when($a->whatsapp_conexao_id, fn ($q) => $q->where('id', $a->whatsapp_conexao_id))->where('ativo', true)->first();
            if (! $cx) return ['ok' => false, 'erro' => 'Sem conexão de WhatsApp ativa.'];
            // Status com imagem/video: manda a URL publica do arquivo (Evolution baixa). base64 cru da ENAMETOOLONG.
            $midiaUrl = null;
            if ($temArq) {
                $rel = ltrim(preg_replace('#^storage/#', '', $caminho), '/');
                $baseApp = rtrim((string) (\App\Support\Tenant::empresa()?->config('evo_webhook_base') ?: config('app.url')), '/');
                $midiaUrl = $baseApp.'/storage/'.$rel;
            }
            // Audiencia: quem ja conversou no WhatsApp (contatos do Atendimento) -> JIDs 55...@s.whatsapp.net
            $jids = Conversa::withoutGlobalScopes()
                ->where('empresa_id', $a->empresa_id)
                ->whereNotNull('cliente_telefone')->where('cliente_telefone', '!=', '')
                ->pluck('cliente_telefone')
                ->map(fn ($tel) => $wa->normalizar((string) $tel))
                ->filter(fn ($d) => strlen($d) >= 12 && strlen($d) <= 13)
                ->unique()->values()
                ->map(fn ($d) => $d.'@s.whatsapp.net')
                ->all();
            return $wa->enviarStatus($cx, (string) $a->texto, $midiaUrl, $temArq ? (string) $a->mime : null, $jids);
        }

        $conversa = $a->conversa_id ? Conversa::withoutGlobalScopes()->find($a->conversa_id) : null;
        if ($conversa) {
            if ($temArq) return $at->registrarSaidaMidia($conversa, $caminho, (string) $a->mime, (string) ($a->nome_arquivo ?: basename($caminho)), (string) $a->texto, 'sistema', $a->user_id);
            if (trim((string) $a->texto) === '') return ['ok' => false, 'erro' => 'Mensagem vazia.'];
            return $at->registrarSaida($conversa, (string) $a->texto, 'sistema', $a->user_id);
        }

        // número avulso (envio em massa pra quem ainda não tem conversa)
        $cx = WhatsappConexao::withoutGlobalScopes()->where('empresa_id', $a->empresa_id)->when($a->whatsapp_conexao_id, fn ($q) => $q->where('id', $a->whatsapp_conexao_id))->where('ativo', true)->first();
        if (! $cx) return ['ok' => false, 'erro' => 'Sem conexão de WhatsApp ativa.'];
        if ($temArq) return $wa->enviarMidia($cx, (string) $a->telefone, base64_encode((string) file_get_contents($abs)), (string) $a->mime, (string) ($a->nome_arquivo ?: basename($caminho)), (string) $a->texto);
        if (trim((string) $a->texto) === '') return ['ok' => false, 'erro' => 'Mensagem vazia.'];
        return $wa->enviarTexto($cx, (string) $a->telefone, (string) $a->texto);
    }
}
