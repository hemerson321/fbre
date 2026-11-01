<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// confere pagamentos PIX pendentes e avisa o cliente
Schedule::command('pagamentos:verificar')->everyMinute()->withoutOverlapping();

// avisa o cliente da encomenda ~1h antes do barco chegar no destino (rastreio por embarcação)
Schedule::command('encomendas:avisar-chegada')->everyFiveMinutes()->withoutOverlapping();
