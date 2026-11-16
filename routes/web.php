<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProjetoController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\RelatorioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvaliacaoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ConfiguracaoController;
use Illuminate\Support\Facades\Route;

// API consumida pelo front-end React (mesmo domínio, sessão por cookie + CSRF).
Route::prefix('api')->group(function () {
    Route::get('/csrf', [AuthController::class, 'csrf']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/configuracoes/perfil', [ConfiguracaoController::class, 'update'])->middleware('auth');

    Route::middleware('perfil:avaliador')->prefix('avaliador')->group(function () {
        Route::get('/categorias', CategoriaController::class);
        Route::get('/projetos', [AvaliacaoController::class, 'index']);
        Route::get('/projetos/por-codigo/{chave}/{categoria}/{subcategoria}', [AvaliacaoController::class, 'showPorCodigo'])
            ->where(['chave' => '[0-9]{8}', 'categoria' => '[1-9][0-9]*', 'subcategoria' => '[1-9][0-9]*']);
        Route::get('/projetos/{projeto}', [AvaliacaoController::class, 'show']);
        Route::put('/projetos/{projeto}/avaliacao', [AvaliacaoController::class, 'update']);
    });

    Route::middleware('perfil:admin')->prefix('admin')->group(function () {
        Route::get('/categorias', CategoriaController::class);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/ranking', RankingController::class);
        Route::get('/projetos', [ProjetoController::class, 'index']);
        Route::get('/indicadores', [DashboardController::class, 'indicadores']);
        Route::get('/relatorios', [RelatorioController::class, 'index']);
        Route::get('/relatorios/pdf', [RelatorioController::class, 'pdf']);
    });
});
