<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\EncomendaController;
use App\Http\Controllers\ImpressaoController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\WhatsappController;
use App\Http\Controllers\Admin\CadastroController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\ViagemController;
use App\Http\Controllers\Admin\RotaController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\PreCadastroController;
use App\Http\Controllers\AcertoController;
use App\Http\Controllers\CapturaController;
use App\Http\Controllers\PreCadastroPublicController;
use Illuminate\Support\Facades\Route;

// ---------------- Pré-cadastro público (cliente via QR, sem login) ----------------
Route::get('/pre/{slug}', [PreCadastroPublicController::class, 'form'])->where('slug', '[a-z0-9-]+')->name('pre.form');
Route::post('/pre/{slug}', [PreCadastroPublicController::class, 'store'])->where('slug', '[a-z0-9-]+')->name('pre.store');
Route::get('/pre/{slug}/ok/{codigo}', [PreCadastroPublicController::class, 'ok'])->where('slug', '[a-z0-9-]+')->name('pre.ok');

// ---------------- Captura por foto (celular lê RG, sem login) ----------------
Route::get('/c', [CapturaController::class, 'mobile'])->name('captura.mobile');
Route::post('/c/enviar', [CapturaController::class, 'enviar'])->name('captura.enviar');

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
    Route::get('/encomendas', [EncomendaController::class, 'index'])->name('encomendas.index')->middleware('perm:encomenda.ver');
    Route::get('/encomendas/nova', [EncomendaController::class, 'create'])->name('encomendas.create')->middleware('perm:cadastra');
    Route::post('/encomendas', [EncomendaController::class, 'store'])->name('encomendas.store')->middleware('perm:cadastra');
    Route::get('/encomendas/{encomenda}', [EncomendaController::class, 'show'])->name('encomendas.show');
    Route::post('/encomendas/{encomenda}/pagar', [EncomendaController::class, 'pagar'])->name('encomendas.pagar')->middleware('perm:baixa');
    Route::post('/encomendas/{encomenda}/entregar', [EncomendaController::class, 'entregar'])->name('encomendas.entregar')->middleware('perm:baixa');
    Route::post('/encomendas/{encomenda}/cancelar', [EncomendaController::class, 'cancelar'])->name('encomendas.cancelar')->middleware('perm:admin');
    Route::get('/pessoas/buscar', [EncomendaController::class, 'buscarPessoa'])->name('pessoas.buscar');

    // ---------- PASSAGEM ----------
    Route::get('/passagens', [\App\Http\Controllers\PassagemController::class, 'index'])->name('passagens.index')->middleware('perm:passagem.ver');
    Route::get('/passagens/nova', [\App\Http\Controllers\PassagemController::class, 'create'])->name('passagens.create')->middleware('perm:passagem');
    Route::post('/passagens', [\App\Http\Controllers\PassagemController::class, 'store'])->name('passagens.store')->middleware('perm:passagem');
    Route::get('/passagens/assentos', [\App\Http\Controllers\PassagemController::class, 'assentos'])->name('passagens.assentos')->middleware('perm:passagem.ver');
    Route::get('/passagens/minhas-vendas', [\App\Http\Controllers\PassagemController::class, 'minhasVendas'])->name('passagens.minhas-vendas')->middleware('perm:passagem.ver');
    Route::post('/passagens/captura/novo', [CapturaController::class, 'novo'])->name('captura.novo')->middleware('perm:passagem');
    Route::get('/passagens/captura/pendentes', [CapturaController::class, 'pendentes'])->name('captura.pendentes')->middleware('perm:passagem');
    Route::post('/passagens/captura/ocr', [CapturaController::class, 'ocrDireto'])->name('captura.ocr')->middleware('perm:passagem');
    Route::post('/passagens/captura/{captura}/usar', [CapturaController::class, 'usar'])->name('captura.usar')->middleware('perm:passagem');
    // Revendedor: filiados + acerto
    Route::get('/checkin/{passagem}', [\App\Http\Controllers\PassagemController::class, 'checkin'])->name('checkin')->middleware('perm:passagem.baixa');
    Route::get('/meus-filiados', [AcertoController::class, 'meus'])->name('filiados.meus');
    Route::post('/meus-filiados/salvar/{filiado?}', [AcertoController::class, 'salvarFiliado'])->name('filiados.salvar');
    Route::post('/meus-filiados/{filiado}/baixa', [AcertoController::class, 'baixaFiliado'])->name('filiados.baixa');
    Route::get('/passagens/{passagem}/comprovante', [\App\Http\Controllers\PassagemController::class, 'comprovante'])->name('passagens.comprovante')->middleware('perm:passagem.ver');
    Route::get('/passagens/{passagem}', [\App\Http\Controllers\PassagemController::class, 'show'])->name('passagens.show')->middleware('perm:passagem.ver');
    Route::post('/passagens/{passagem}/pagar', [\App\Http\Controllers\PassagemController::class, 'pagar'])->name('passagens.pagar')->middleware('perm:passagem.baixa');
    Route::post('/passagens/{passagem}/embarcar', [\App\Http\Controllers\PassagemController::class, 'embarcar'])->name('passagens.embarcar')->middleware('perm:passagem.baixa');
    Route::post('/passagens/{passagem}/cancelar', [\App\Http\Controllers\PassagemController::class, 'cancelar'])->name('passagens.cancelar')->middleware('perm:admin');

    // ---------- FRETE ----------
    Route::get('/fretes', [\App\Http\Controllers\FreteController::class, 'index'])->name('fretes.index')->middleware('perm:frete.ver');
    Route::get('/fretes/novo', [\App\Http\Controllers\FreteController::class, 'create'])->name('fretes.create')->middleware('perm:frete');
    Route::post('/fretes', [\App\Http\Controllers\FreteController::class, 'store'])->name('fretes.store')->middleware('perm:frete');
    Route::get('/fretes/{frete}', [\App\Http\Controllers\FreteController::class, 'show'])->name('fretes.show')->middleware('perm:frete.ver');
    Route::post('/fretes/{frete}/pagar', [\App\Http\Controllers\FreteController::class, 'pagar'])->name('fretes.pagar')->middleware('perm:frete.baixa');
    Route::post('/fretes/{frete}/entregar', [\App\Http\Controllers\FreteController::class, 'entregar'])->name('fretes.entregar')->middleware('perm:frete.baixa');
    Route::post('/fretes/{frete}/cancelar', [\App\Http\Controllers\FreteController::class, 'cancelar'])->name('fretes.cancelar')->middleware('perm:admin');

    // ---------------- Pré-cadastros (operador) ----------------
    Route::middleware('perm:encomenda.precadastro')->group(function () {
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
    Route::post('/sync/passagem', [SyncController::class, 'passagem'])->name('sync.passagem');
    Route::post('/sync/frete', [SyncController::class, 'frete'])->name('sync.frete');
    Route::post('/sync/baixa', [SyncController::class, 'baixa'])->name('sync.baixa');
    Route::view('/offline', 'offline')->name('offline')->withoutMiddleware('auth');

    // ---------------- Caixa / relatórios ----------------
    Route::get('/caixa', [CaixaController::class, 'index'])->name('caixa.index')->middleware('perm:caixa');
    Route::post('/caixa/avulso', [CaixaController::class, 'avulso'])->name('caixa.avulso')->middleware('perm:avulso');
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
        Route::post('/embarcacoes/{embarcacao}', [CadastroController::class, 'salvarEmbarcacao'])->name('embarcacoes.salvar');
        Route::post('/tipos-passageiro', [CadastroController::class, 'salvarTiposPassageiro'])->name('tipos-passageiro.salvar');
        Route::post('/taxas-embarque', [CadastroController::class, 'salvarTaxasEmbarque'])->name('taxas-embarque.salvar');
        Route::post('/estacoes', [CadastroController::class, 'novaEstacao'])->name('estacoes.store');
        Route::post('/estacoes/{estacao}/renomear', [CadastroController::class, 'renomearEstacao'])->name('estacoes.renomear');
        Route::post('/zerar', [CadastroController::class, 'zerar'])->name('zerar');
        Route::get('/rotas', [RotaController::class, 'index'])->name('rotas');
        Route::post('/rotas', [RotaController::class, 'store'])->name('rotas.store');
        Route::put('/rotas/{rota}', [RotaController::class, 'update'])->name('rotas.update');
        Route::post('/rotas/{rota}/toggle', [RotaController::class, 'toggle'])->name('rotas.toggle');
        Route::get('/acertos', [AcertoController::class, 'index'])->name('acertos');
        Route::post('/acertos/{revendedor}/baixa', [AcertoController::class, 'baixaEmpresa'])->name('acertos.baixa');
        // WhatsApp (conexoes oficiais e nao oficiais)
        Route::get('/whatsapp', [WhatsappController::class, 'index'])->name('whatsapp');
        Route::post('/whatsapp/{conexao?}', [WhatsappController::class, 'salvar'])->name('whatsapp.salvar');
        Route::delete('/whatsapp/{conexao}', [WhatsappController::class, 'excluir'])->name('whatsapp.excluir');
        Route::post('/whatsapp/{conexao}/testar', [WhatsappController::class, 'testar'])->name('whatsapp.testar');
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
