<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\EncomendaController;
use App\Http\Controllers\ImpressaoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\CadastroController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\RelatorioController;
use Illuminate\Support\Facades\Route;

// ---------------- Autenticação ----------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::view('/bloqueado', 'auth.bloqueado')->middleware('auth')->name('bloqueado');

Route::middleware('auth')->group(function () {
    Route::get('/', [EncomendaController::class, 'inicio'])->name('inicio');

    // ---------------- Encomendas ----------------
    Route::get('/encomendas', [EncomendaController::class, 'index'])->name('encomendas.index')->middleware('perm:filtro');
    Route::get('/encomendas/nova', [EncomendaController::class, 'create'])->name('encomendas.create')->middleware('perm:cadastra');
    Route::post('/encomendas', [EncomendaController::class, 'store'])->name('encomendas.store')->middleware('perm:cadastra');
    Route::get('/encomendas/{encomenda}', [EncomendaController::class, 'show'])->name('encomendas.show');
    Route::post('/encomendas/{encomenda}/pagar', [EncomendaController::class, 'pagar'])->name('encomendas.pagar')->middleware('perm:baixa');
    Route::post('/encomendas/{encomenda}/entregar', [EncomendaController::class, 'entregar'])->name('encomendas.entregar')->middleware('perm:baixa');
    Route::post('/encomendas/{encomenda}/cancelar', [EncomendaController::class, 'cancelar'])->name('encomendas.cancelar')->middleware('perm:admin');
    Route::get('/pessoas/buscar', [EncomendaController::class, 'buscarPessoa'])->name('pessoas.buscar');

    // ---------------- Impressão ----------------
    Route::middleware('perm:imprimir')->group(function () {
        Route::get('/imprimir/comprovante/{encomenda}', [ImpressaoController::class, 'comprovante'])->name('imprimir.comprovante');
        Route::get('/imprimir/etiqueta/{encomenda}', [ImpressaoController::class, 'etiqueta'])->name('imprimir.etiqueta');
        Route::get('/imprimir/protocolo', [ImpressaoController::class, 'protocolo'])->name('imprimir.protocolo')->middleware('perm:filtro');
        Route::get('/imprimir/listagem', [ImpressaoController::class, 'listagem'])->name('imprimir.listagem')->middleware('perm:filtro');
        Route::post('/imprimir/direto/{encomenda}/{tipo}', [ImpressaoController::class, 'direto'])->name('imprimir.direto');
    });

    // ---------------- Caixa / relatórios ----------------
    Route::get('/caixa', [CaixaController::class, 'index'])->name('caixa.index')->middleware('perm:caixa');
    Route::get('/meu-relatorio', [RelatorioController::class, 'meu'])->name('relatorio.meu')->middleware('perm:relatorio');

    // ---------------- Admin da empresa ----------------
    Route::prefix('admin')->middleware('perm:admin')->name('admin.')->group(function () {
        Route::get('/empresa', [EmpresaController::class, 'editar'])->name('empresa');
        Route::post('/empresa', [EmpresaController::class, 'salvar']);
        Route::resource('usuarios', UsuarioController::class)->except(['show']);
        Route::get('/cadastros', [CadastroController::class, 'index'])->name('cadastros');
        Route::post('/cadastros/{tipo}', [CadastroController::class, 'store'])->name('cadastros.store');
        Route::delete('/cadastros/{tipo}/{id}', [CadastroController::class, 'destroy'])->name('cadastros.destroy');
        Route::post('/estacoes', [CadastroController::class, 'novaEstacao'])->name('estacoes.store');
    });

    // ---------------- Super-admin (GERATEC) ----------------
    Route::prefix('super')->middleware('perm:super')->name('super.')->group(function () {
        if (config('fbre.single')) { return; }
        Route::get('/empresas', [EmpresaController::class, 'index'])->name('empresas');
        Route::post('/empresas', [EmpresaController::class, 'store'])->name('empresas.store');
        Route::post('/empresas/{empresa}/toggle', [EmpresaController::class, 'toggle'])->name('empresas.toggle');
        Route::post('/empresas/{empresa}/entrar', [EmpresaController::class, 'entrar'])->name('empresas.entrar');
        Route::post('/empresas/sair', [EmpresaController::class, 'sair'])->name('empresas.sair');
    });
});

// Tela de login personalizada por empresa: fbre.com.br/{slug}  (fica por último para não engolir outras rotas)
Route::get('/{slug}', [AuthController::class, 'formEmpresa'])->where('slug', '[a-z0-9-]+')->middleware('guest')->name('login.empresa');
