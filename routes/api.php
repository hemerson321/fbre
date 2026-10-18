<?php

use App\Http\Controllers\Api\PrintAgentController;
use Illuminate\Support\Facades\Route;

// FBRE Print Agent — autenticado pelo token da estação (header X-Estacao-Token)
Route::prefix('print')->group(function () {
    Route::get('/next', [PrintAgentController::class, 'next']);
    Route::post('/{job}/done', [PrintAgentController::class, 'done']);
    Route::post('/{job}/error', [PrintAgentController::class, 'error']);
    Route::get('/ping', [PrintAgentController::class, 'ping']);
});
