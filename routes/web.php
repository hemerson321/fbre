<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\EncomendaController;
use App\Http\Controllers\ImpressaoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\CadastroController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\ViagemController;
use App\Http\Controllers\Admin\RotaController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\PreCadastroController;
use App\Http\Controllers\PreCadastroPublicController;
use Illuminate\Support\Facades\Route;

// ---------------- Pré-cadastro público (cliente via QR, sem login) ----------------
Route::get('/pre/{slug}', [PreCadastroPublicController::class, 'form'])->where('slug', '[a-z0-9-]+')->name('pre.form');
Route::post('/pre/{slug}', [PreCadastroPublicController::class, 'store'])->where('slug', '[a-z0-9-]+')->name('pre.store');
Route::get('/pre/{slug}/ok/{codigo}', [PreCadastroPublicController::class, 'ok'])->where('slug', '[a-z0-9-]+')->name('pre.ok');

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

    // ---------------- Pré-cadastros (operador) ----------------
    Route::middleware('perm:cadastra')->group(function () {
        Route::get('/pre-cadastros', [PreCadastroController::class, 'index'])->name('pre.index');
        Route::get('/pre-cadastros/qr', [PreCadastroController::class, 'qr'])->name('pre.qr');
        Route::post('/pre-cadastros/{pre}/cancelar', [PreCadastroController::class, 'cancelar'])->name('pre.cancelar');
    });

    // ---------------- Impressão ----------------
    Route::middleware('perm:imprimir')->group(function () {
        Route::get('/imprimir/comprovante/{encomenda}', [ImpressaoController::class, 'comprovante'])->name('imprimir.comprovante');
        Route::get('/imprimir/etiqueta/{encomenda}', [ImpressaoController::class, 'etiqueta'])->name('imprimir.etiqueta');
        Route::get('/imprimir/protocolo', [ImpressaoController::class, 'protocolo'])->name('imprimir.protocolo')->middleware('perm:filtro');
        Route::get('/imprimir/listagem', [ImpressaoController::class, 'listagem'])->name('imprimir.listagem')->middleware('perm:filtro');
        Route::post('/imprimir/direto/{encomenda}/{tipo}', [ImpressaoController::class, 'direto'])->name('imprimir.direto');
    });

    // ---------------- Offline / sincronização ----------------
    Route::get('/sync/bootstrap', [SyncController::class, 'bootstrap'])->name('sync.bootstrap');
    Route::post('/sync/encomenda', [SyncController::class, 'encomenda'])->name('sync.encomenda');
    Route::post('/sync/baixa', [SyncController::class, 'baixa'])->name('sync.baixa');
    Route::view('/offline', 'offline')->name('offline')->withoutMiddleware('auth');

    // ---------------- Caixa / relatórios ----------------
    Route::get('/caixa', [CaixaController::class, 'index'])->name('caixa.index')->middleware('perm:caixa');
    Route::get('/meu-relatorio', [RelatorioController::class, 'meu'])->name('relatorio.meu')->middleware('perm:relatorio');

    // ---------------- Viagens (módulo rotas) ----------------
    Route::get('/viagens', [ViagemController::class, 'index'])->name('viagens.index')->middleware('perm:filtro');
    Route::post('/viagens/{viagem}/status', [ViagemController::class, 'status'])->name('viagens.status')->middleware('perm:baixa');

    // ---------------- Admin da empresa ----------------
    Route::prefix('admin')->middleware('perm:admin')->name('admin.')->group(function () {
        Route::get('/empresa', [EmpresaController::class, 'editar'])->name('empresa');
        Route::post('/empresa', [EmpresaController::class, 'salvar']);
        Route::resource('usuarios', UsuarioController::class)->except(['show']);
        Route::get('/cadastros', [CadastroController::class, 'index'])->name('cadastros');
        Route::post('/cadastros/{tipo}', [CadastroController::class, 'store'])->name('cadastros.store');
        Route::delete('/cadastros/{tipo}/{id}', [CadastroController::class, 'destroy'])->name('cadastros.destroy');
        Route::post('/estacoes', [CadastroController::class, 'novaEstacao'])->name('estacoes.store');
        Route::post('/estacoes/{estacao}/renomear', [CadastroController::class, 'renomearEstacao'])->name('estacoes.renomear');
        Route::post('/zerar', [CadastroController::class, 'zerar'])->name('zerar');
        Route::get('/rotas', [RotaController::class, 'index'])->name('rotas');
        Route::post('/rotas', [RotaController::class, 'store'])->name('rotas.store');
        Route::put('/rotas/{rota}', [RotaController::class, 'update'])->name('rotas.update');
        Route::post('/rotas/{rota}/toggle', [RotaController::class, 'toggle'])->name('rotas.toggle');
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
