<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Projeto;
use App\Models\User;
use App\Services\Avaliacao;
use App\Services\Relatorios;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjetoController extends Controller
{
    public function show(Projeto $projeto, Avaliacao $avaliacao): JsonResponse
    {
        return response()->json($this->ficha($projeto, $avaliacao));
    }

    public function pdf(Projeto $projeto, Avaliacao $avaliacao): Response
    {
        return Pdf::loadView('relatorios.ficha-projeto', ['ficha' => $this->ficha($projeto, $avaliacao), 'agora' => now()])
            ->setPaper('a4')->download('ficha-projeto-'.$projeto->id.'.pdf');
    }

    private function ficha(Projeto $projeto, Avaliacao $avaliacao): array
    {
        $categoria = $projeto->subcategoria->categoria;
        $notas = collect($avaliacao->normalizar($categoria->codigo, $projeto->notas ?? []))->keyBy('avaliador_id');
        $ids = collect($projeto->avaliador_ids ?? [])->merge($notas->keys())->map(fn ($id): int => (int) $id)->unique()->values();
        $usuarios = User::whereIn('id', $ids)->get(['id', 'name', 'organizacao'])->keyBy('id');
        $zero = $notas->contains('zero_eliminatorio', true);
        $status = match (true) {
            $zero => 'inabilitada',
            $notas->isEmpty() => 'sem_avaliacao',
            $notas->count() < 3 => 'em_avaliacao',
            $projeto->nota_final >= config('avaliacao.nota_corte') => 'habilitada',
            default => 'inabilitada',
        };

        return [
            'projeto' => [
                'id' => $projeto->id, 'codigo' => $projeto->codigo, 'nome' => $projeto->nome,
                'categoria' => $categoria->nome, 'categoria_codigo' => $categoria->codigo,
                'subcategoria' => $projeto->subcategoria->nome,
                'nota_final' => $projeto->nota_final, 'avaliacoes_recebidas' => $notas->count(),
                'status' => $status, 'status_rotulo' => Relatorios::STATUS[$status],
                'zero_eliminatorio' => $zero, 'criado_em' => $projeto->created_at?->toIso8601String(),
            ],
            'regras' => $avaliacao->criterios($categoria->codigo),
            'avaliadores' => $ids->map(fn (int $id): array => [
                'id' => $id, 'nome' => $usuarios[$id]->name ?? 'Avaliador indisponível',
                'organizacao' => $usuarios[$id]->organizacao ?? null,
                'avaliacao' => $notas->get($id),
            ]),
        ];
    }

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
