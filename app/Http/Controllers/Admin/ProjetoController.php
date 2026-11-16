<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjetoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'categoria' => ['nullable', 'integer', 'exists:categorias,codigo'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
        ]);
        $tamanho = DB::getDriverName() === 'pgsql' ? 'jsonb_array_length(projetos.notas)' : 'json_array_length(projetos.notas)';
        $consulta = DB::table('projetos')
            ->join('subcategorias', 'subcategorias.id', '=', 'projetos.subcategoria_id')
            ->join('categorias', 'categorias.id', '=', 'subcategorias.categoria_id')
            ->select('projetos.id', 'projetos.codigo', 'projetos.nome', 'projetos.nota_final', 'categorias.codigo as categoria_codigo', 'categorias.nome as categoria', 'subcategorias.nome as subcategoria')
            ->selectRaw("{$tamanho} AS avaliacoes_recebidas");
        if (isset($filtros['categoria'])) {
            $consulta->where('categorias.codigo', $filtros['categoria']);
        }
        if (! empty($filtros['busca'])) {
            $termo = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filtros['busca'])).'%';
            $consulta->where(function ($consulta) use ($termo): void {
                foreach (['projetos.nome', 'projetos.codigo', 'subcategorias.nome', 'categorias.nome'] as $coluna) {
                    $consulta->orWhereRaw("LOWER({$coluna}) LIKE ? ESCAPE '!'", [$termo]);
                }
            });
        }
        $pagina = $consulta->orderByDesc('projetos.id')->paginate($filtros['por_pagina'] ?? 10)->withQueryString();
        $pagina->through(fn ($projeto): array => [
            'id' => $projeto->id, 'codigo' => $projeto->codigo, 'nome' => $projeto->nome,
            'categoria_codigo' => (int) $projeto->categoria_codigo, 'categoria' => $projeto->categoria,
            'subcategoria' => $projeto->subcategoria,
            'nota_final' => $projeto->nota_final === null ? null : (float) $projeto->nota_final,
            'avaliacoes_recebidas' => (int) $projeto->avaliacoes_recebidas,
        ]);

        return response()->json($pagina);
    }
}
