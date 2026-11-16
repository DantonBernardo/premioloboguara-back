<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function indicadores(): JsonResponse
    {
        return response()->json($this->contadores());
    }

    private function contadores(): array
    {
        return [
            'projetos' => Projeto::query()->count(),
            'avaliadores' => User::query()->where('perfil', User::PERFIL_AVALIADOR)->count(),
        ];
    }

    public function __invoke(): JsonResponse
    {
        $tamanho = DB::getDriverName() === 'pgsql' ? 'jsonb_array_length(notas)' : 'json_array_length(notas)';
        $andamento = Projeto::query()->selectRaw("SUM(CASE WHEN {$tamanho} = 0 THEN 1 ELSE 0 END) AS aguardando, SUM(CASE WHEN {$tamanho} BETWEEN 1 AND 2 THEN 1 ELSE 0 END) AS em_processo, SUM(CASE WHEN {$tamanho} = 3 THEN 1 ELSE 0 END) AS finalizados")->first();

        return response()->json([
            'atualizado_em' => now()->toIso8601String(),
            'resumo' => $this->contadores(),
            'andamento' => [
                'aguardando' => (int) $andamento->aguardando,
                'em_processo' => (int) $andamento->em_processo,
                'finalizados' => (int) $andamento->finalizados,
            ],
            'por_categoria' => Categoria::query()
                ->leftJoin('subcategorias', 'subcategorias.categoria_id', '=', 'categorias.id')
                ->leftJoin('projetos', 'projetos.subcategoria_id', '=', 'subcategorias.id')
                ->select('categorias.codigo', 'categorias.nome as categoria')
                ->selectRaw('COUNT(projetos.id) AS total')
                ->groupBy('categorias.id', 'categorias.codigo', 'categorias.nome')
                ->orderBy('categorias.codigo')->get()->map(fn ($categoria): array => [
                    'codigo' => $categoria->codigo, 'categoria' => $categoria->categoria, 'total' => (int) $categoria->total,
                ])->all(),
        ]);
    }
}
