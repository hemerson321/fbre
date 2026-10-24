<?php

namespace App\Console\Commands;

use App\Services\ConfirmaPagamento;
use Illuminate\Console\Command;

class VerificarPagamentos extends Command
{
    protected $signature = 'pagamentos:verificar';
    protected $description = 'Confirma cobranças PIX pagas no Mercado Pago e avisa o cliente';

    public function handle(ConfirmaPagamento $svc): int
    {
        $n = $svc->verificarPendentes();
        $this->info("Pagamentos confirmados: {$n}");
        return self::SUCCESS;
    }
}
