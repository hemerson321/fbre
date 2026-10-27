<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Conversa;
use App\Models\Encomenda;
use App\Models\Frete;
use App\Models\Passagem;
use App\Support\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Confirma cobranças PIX: consulta o Mercado Pago, marca como paga, dá baixa
 * (encomenda/passagem) e AVISA o cliente no WhatsApp (quando veio do agente).
 * Usado pelo webhook do MP e pelo verificador agendado (a cada minuto).
 */
class ConfirmaPagamento
{
    public function __construct(
        protected MercadoPago $mp,
        protected Atendimento $at,
        protected EncomendaService $enc,
        protected PassagemService $pas,
        protected FreteService $fre,
    ) {}

    /** Varre cobranças pendentes recentes e aplica as que já foram pagas. Retorna quantas confirmou. */
    public function verificarPendentes(int $horas = 48): int
    {
        $n = 0;
        Cobranca::withoutGlobalScopes()
            ->where('status', 'pendente')
            ->whereNotNull('provider_payment_id')
            ->where('created_at', '>=', now()->subHours($horas))
            ->with('api')
            ->chunkById(100, function ($lote) use (&$n) {
                foreach ($lote as $cob) {
                    if (! $cob->api) continue;
                    Tenant::set($cob->empresa_id);
                    try {
                        $st = $this->mp->consultar($cob->api, (string) $cob->provider_payment_id);
                        if (($st['ok'] ?? false) && ($st['pago'] ?? false) && $this->aplicar($cob)) {
                            $n++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('verificarPendentes erro cobranca '.$cob->id.': '.$e->getMessage());
                    }
                }
            });
        return $n;
    }

    /** Marca paga + baixa + aviso. Retorna true se confirmou agora (não estava paga). */
    public function aplicar(Cobranca $cob): bool
    {
        if ($cob->status === 'pago' && ! ($cob->payload && ! $cob->referencia_id)) return false;
        $cob->forceFill(['status' => 'pago', 'pago_em' => $cob->pago_em ?: now()])->save();
        Tenant::set($cob->empresa_id);

        // Pedido de balcão montado na própria cobrança (encomenda/passagem): o SERVIDOR cria agora.
        if (! $cob->referencia_id && $cob->payload && in_array($cob->modulo, ['encomenda', 'passagem', 'frete'], true)) {
            $this->criarPedidoDoPayload($cob);
            return true;
        }

        $valorFmt = number_format((float) $cob->valor, 2, ',', '.');

        if ($cob->referencia_tipo === Conversa::class && $cob->referencia_id) {
            $conv = Conversa::withoutGlobalScopes()->find($cob->referencia_id);
            if ($conv) {
                $ctx = $conv->contexto ?? [];
                $ctx['pago'] = true;
                $conv->forceFill(['funil' => 'pago', 'contexto' => $ctx])->save();
                try { $this->at->registrarSaida($conv, "✅ Pagamento confirmado! Recebi seu PIX de R$ {$valorFmt}.", 'sistema'); } catch (\Throwable $e) {}
                // venda primeiro, dados depois: se ainda não tem dados do passageiro, pede agora
                if (! $this->tentarCriarPassagem($conv)) {
                    try { $this->at->registrarSaida($conv, "Agora pra emitir sua passagem, me passa o *nome completo*, o *documento* (RG ou CPF) e a *data de nascimento* do passageiro — ou manda a *foto do RG/CNH* que eu pego os dados. 😊", 'sistema'); } catch (\Throwable $e) {}
                }
            }
        } elseif ($cob->referencia_tipo === Encomenda::class && $cob->referencia_id) {
            $e = Encomenda::withoutGlobalScopes()->find($cob->referencia_id);
            if ($e) $this->enc->baixa($e, ['pix' => (float) $cob->valor, 'pix_auto' => true], (int) $e->user_id, 'mp-'.$cob->id);
        } elseif ($cob->referencia_tipo === Passagem::class && $cob->referencia_id) {
            $p = Passagem::withoutGlobalScopes()->find($cob->referencia_id);
            if ($p) $this->pas->baixa($p, ['pix' => (float) $cob->valor, 'pix_auto' => true], (int) $p->user_id);
        }

        return true;
    }

    /**
     * Cria o pedido (encomenda/passagem) guardado na cobrança, exatamente como se o operador
     * tivesse clicado em "Salvar" — só que disparado pela confirmação do PIX. Idempotente.
     */
    public function criarPedidoDoPayload(Cobranca $cob): void
    {
        if ($cob->referencia_id || ! $cob->payload) return;

        \Illuminate\Support\Facades\DB::transaction(function () use ($cob) {
            $c = Cobranca::withoutGlobalScopes()->whereKey($cob->id)->lockForUpdate()->first();
            if (! $c || $c->referencia_id || ! $c->payload) return; // outra via (webhook/poll) já criou

            Tenant::set($c->empresa_id);
            $op = $c->user_id ? \App\Models\User::withoutGlobalScopes()->find($c->user_id) : null;
            if (! $op) $op = \App\Models\User::withoutGlobalScopes()->where('empresa_id', $c->empresa_id)->orderByDesc('admin')->first();
            if (! $op) { $c->forceFill(['erro' => 'Operador não encontrado'])->save(); return; }

            $data = $c->payload;
            try {
                // camarote: resolve preço/ocupantes/anti-sobreposição agora (no pagamento)
                if ($c->modulo === 'passagem' && ! empty($data['camarote_id']) && ! empty($data['ocupantes'])) {
                    $data = app(\App\Services\CamaroteVenda::class)->montarPayload($data);
                }

                // garante o pagamento PIX no pedido (valor da cobrança), sem duplicar
                $pags = collect($data['pagamentos'] ?? [])->reject(fn ($p) => ($p['meio'] ?? '') === 'pix')->values()->all();
                $pags[] = ['meio' => 'pix', 'valor' => (float) $c->valor, 'auto' => true];
                $data['pagamentos'] = $pags;

                if ($c->modulo === 'passagem') {
                    $rec = $this->pas->criar($data, (int) $op->id, 'pix-pas-'.$c->id);
                    $tipo = Passagem::class;
                } elseif ($c->modulo === 'frete') {
                    $rec = $this->fre->criar($data, (int) $op->id, 'pix-fre-'.$c->id);
                    $tipo = Frete::class;
                } else {
                    $rec = $this->enc->criar($data, (int) $op->id, 'pix-enc-'.$c->id);
                    $tipo = Encomenda::class;
                }
            } catch (\Throwable $e) {
                report($e);
                $c->forceFill(['erro' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 250)])->save();
                return;
            }

            $c->forceFill(['referencia_tipo' => $tipo, 'referencia_id' => $rec->id, 'erro' => null])->save();
        });
    }

    /** Cria a passagem SE já pagou e já tem venda + dados do passageiro. Retorna true se já existe/criou. */
    public function tentarCriarPassagem(Conversa $conv): bool
    {
        $ctx = $conv->contexto ?? [];
        if (! empty($ctx['passagem_id'])) return true;
        if (empty($ctx['pago'])) return false;
        $venda = $ctx['venda'] ?? null;
        if (! $venda || empty($venda['origem']) || empty($venda['destino'])) return false;
        $pax = $venda['passageiros'] ?? ($ctx['passageiros'] ?? []);
        if (! $pax) return false;

        $cob = Cobranca::withoutGlobalScopes()
            ->where('referencia_tipo', Conversa::class)->where('referencia_id', $conv->id)
            ->where('status', 'pago')->latest('id')->first();
        if (! $cob) return false;

        Tenant::set($conv->empresa_id);
        $this->criarPassagem($conv, $cob);
        return ! empty($conv->fresh()->contexto['passagem_id']);
    }

    /** Cadastra a passagem no sistema a partir dos dados da venda; operador = usuário do agente. */    /** Cadastra a passagem no sistema a partir dos dados da venda; operador = usuário do agente. */
    protected function criarPassagem(Conversa $conv, Cobranca $cob): void
    {
        try {
            $ctx = $conv->contexto ?? [];
            if (! empty($ctx['passagem_id'])) return;
            $venda = $ctx['venda'] ?? null;
            if (! $venda || empty($venda['origem']) || empty($venda['destino'])) return;

            $agente = (! empty($ctx['agente_id']) ? \App\Models\Agente::find($ctx['agente_id']) : null)
                ?: \App\Models\Agente::where('modulo', $conv->modulo)->where('ativo', true)->first();
            $checkinAuto = $agente ? (bool) ($agente->regras['checkin_auto'] ?? false) : false;
            $op = $agente ? $this->operadorDoAgente($agente)
                : \App\Models\User::withoutGlobalScopes()->where('empresa_id', $conv->empresa_id)->orderByDesc('admin')->first();
            if (! $op) return;

            $paxList = $venda['passageiros'] ?? ($ctx['passageiros'] ?? []);
            if (! $paxList) $paxList = [['nome' => $conv->cliente_nome ?: 'Passageiro']];
            $n = max(count($paxList), 1);
            $unit = round(((float) $cob->valor) / $n, 2);

            $passageiros = array_map(fn ($px) => [
                'nome' => $px['nome'] ?? 'Passageiro',
                'tipo' => 'Adulto',
                'documento' => $px['documento'] ?? null,
                'nascimento' => $this->dataIso($px['nascimento'] ?? null),
                'valor_passagem' => $unit,
                'valor_integral' => $unit,
                'checkin' => $checkinAuto,
            ], $paxList);

            $dados = [
                'origem' => $venda['origem'],
                'destino' => $venda['destino'],
                'data_viagem' => $this->dataIso($venda['data'] ?? null),
                'comprador_nome' => $conv->cliente_nome,
                'comprador_telefone' => $conv->cliente_telefone,
                'forma' => 'avista',
                'passageiros' => $passageiros,
            ];
            $p = $this->pas->criar($dados, (int) $op->id);

            // registra o pagamento PIX SEM embarcar (o cliente embarca depois, no check-in)
            \App\Models\Pagamento::create([
                'empresa_id' => $p->empresa_id, 'modulo' => 'passagem', 'origem_id' => $p->id, 'encomenda_id' => null,
                'meio' => 'pix', 'pix_auto' => true, 'valor' => (float) $cob->valor, 'user_id' => (int) $op->id, 'momento' => 'baixa', 'tipo_mov' => 'entrada',
            ]);
            $p->recalcular();

            $ctx['passagem_id'] = $p->id;
            $conv->forceFill(['contexto' => $ctx])->save();
            $cob->forceFill(['referencia_tipo' => \App\Models\Passagem::class, 'referencia_id' => $p->id])->save();

            // envia o código da passagem pro cliente (texto)
            try {
                $num = $p->numeroFormatado();
                $quando = $p->data_viagem ? \Carbon\Carbon::parse($p->data_viagem)->format('d/m/Y') : '';
                $nomes = collect($passageiros)->pluck('nome')->implode(', ');
                $msg = "🎫 Passagem emitida!\nNº *{$num}*\n{$venda['origem']} → {$venda['destino']}".($quando ? "\nData: {$quando}" : '')."\nPassageiro(s): {$nomes}\n\nGuarde esse número — no embarque é só apresentar.";
                $this->at->registrarSaida($conv, $msg, 'sistema');
            } catch (\Throwable $e) {}

            // envia o PDF do comprovante (com QR) pelo WhatsApp
            try {
                if ($conv->conexao) {
                    $pdf = app(\App\Services\PdfComprovante::class)->gerar($p);
                    if ($pdf) {
                        app(\App\Services\WhatsApp::class)->enviarDocumento(
                            $conv->conexao, $conv->cliente_telefone, base64_encode($pdf),
                            'passagem-'.$p->numeroFormatado().'.pdf', 'Seu comprovante em PDF 📄'
                        );
                    }
                }
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            Log::warning('criarPassagem (agente) erro: '.$e->getMessage());
        }
    }

    /** Usuário-operador do agente (cria uma vez e reaproveita). Faz a passagem aparecer como o agente. */
    protected function operadorDoAgente(\App\Models\Agente $a): ?\App\Models\User
    {
        $uid = $a->regras['user_id'] ?? null;
        if ($uid && ($u = \App\Models\User::withoutGlobalScopes()->find($uid))) return $u;
        try {
            $u = \App\Models\User::create([
                'empresa_id' => $a->empresa_id,
                'name' => $a->nome ?: 'Agente IA',
                'login' => 'agente-'.$a->id.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4)),
                'password' => \Illuminate\Support\Str::random(24),
                'prefixo' => $this->prefixoLivre(),
                'ativo' => true,
                'admin' => false,
                'perm_passagem' => true,
                'perm_imprimir' => true,
                'setor' => 'Agente IA',
            ]);
            $regras = $a->regras ?? [];
            $regras['user_id'] = $u->id;
            $a->forceFill(['regras' => $regras])->save();
            return $u;
        } catch (\Throwable $e) {
            Log::warning('operadorDoAgente: '.$e->getMessage());
            return \App\Models\User::withoutGlobalScopes()->where('empresa_id', $a->empresa_id)->orderByDesc('admin')->first();
        }
    }

    protected function prefixoLivre(): string
    {
        foreach (['IA', 'AG', 'IA1', 'AG1', 'AG2', 'BOT', 'AI'] as $pfx) {
            if (! \App\Models\User::withoutGlobalScopes()->where('prefixo', $pfx)->exists()) return $pfx;
        }
        return 'IA'.random_int(10, 99);
    }

    /** dd/mm/aaaa -> aaaa-mm-dd (ou null). */
    protected function dataIso(?string $s): ?string
    {
        $s = trim((string) $s);
        if ($s === '') return null;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) return $m[3].'-'.$m[2].'-'.$m[1];
        try { return \Carbon\Carbon::parse($s)->toDateString(); } catch (\Throwable $e) { return null; }
    }
}
