<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Projeto;
use App\Services\Relatorios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RankingController extends Controller
{
    public function __invoke(Request $request, Relatorios $relatorios): JsonResponse
    {
        $filtros = $request->validate([
            'categoria' => ['nullable', 'integer', 'exists:categorias,codigo'],
            'andamento' => ['nullable', Rule::in(['aguardando', 'em_processo', 'finalizados'])],
            'busca' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([10, 15])],
        ]);
        $dados = [
            'projetos' => $relatorios->projetosAnalisados(isset($filtros['categoria']) ? (int) $filtros['categoria'] : null)->all(),
            'categorias' => Categoria::orderBy('codigo')->get(['codigo', 'nome'])->toArray(),
        ];
        $ordemInscricao = Projeto::orderBy('created_at')->orderBy('id')->pluck('id')->flip();
        $ordenados = collect($dados['projetos'])->sort(function (array $a, array $b) use ($ordemInscricao): int {
            return [$b['nota_final'] ?? -1, $ordemInscricao[$a['id']]] <=> [$a['nota_final'] ?? -1, $ordemInscricao[$b['id']]];
        })->values();
        $ordenados = $ordenados->map(function (array $projeto, int $indice): array {
            $situacao = match (true) {
                $projeto['status'] === 'inabilitada' => 'inabilitada',
                $projeto['avaliacoes_recebidas'] === 0 => 'sem_avaliacao',
                $projeto['avaliacoes_recebidas'] < 2 => 'em_avaliacao',
                default => $projeto['status'],
            };

            return array_replace($projeto, [
                'posicao' => $projeto['nota_final'] === null ? null : $indice + 1,
                'status' => $situacao, 'status_rotulo' => Relatorios::STATUS[$situacao],
            ]);
        });
        $resumo = [
            'total' => $ordenados->count(), 'com_nota' => $ordenados->whereNotNull('nota_final')->count(),
            'finalizados' => $ordenados->where('avaliacoes_recebidas', 2)->count(),
            'maior_media' => $ordenados->first()['nota_final'] ?? null,
        ];
        if (! empty($filtros['andamento'])) {
            $ordenados = $ordenados->filter(fn (array $p): bool => match ($filtros['andamento']) {
                'aguardando' => $p['avaliacoes_recebidas'] === 0,
                'em_processo' => $p['avaliacoes_recebidas'] === 1,
                'finalizados' => $p['avaliacoes_recebidas'] === 2,
            });
        }
        if (! empty($filtros['busca'])) {
            $termo = Str::lower($filtros['busca']);
            $ordenados = $ordenados->filter(fn (array $p): bool => str_contains(Str::lower($p['nome'].' '.$p['codigo'].' '.$p['subcategoria'].' '.$p['categoria']), $termo));
        }
        $destaques = collect($dados['categorias'])
            ->when(isset($filtros['categoria']), fn ($categorias) => $categorias->where('codigo', (int) $filtros['categoria']))
            ->map(function (array $categoria) use ($ordenados): array {
                $avaliados = $ordenados->where('categoria_codigo', $categoria['codigo'])->whereNotNull('nota_final');
                $lider = $avaliados->first();

                return [
                    'categoria_codigo' => $categoria['codigo'], 'categoria' => $categoria['nome'],
                    'projeto' => $lider,
                    'empatados' => $lider === null ? 0 : $avaliados->where('nota_final', $lider['nota_final'])->count(),
                ];
            })->values();
        $pagina = (int) ($filtros['page'] ?? 1);
        $tamanho = (int) ($filtros['por_pagina'] ?? 15);
        $paginacao = new LengthAwarePaginator($ordenados->forPage($pagina, $tamanho)->values(), $ordenados->count(), $tamanho, $pagina, ['path' => $request->url()]);

        return response()->json([
            'atualizado_em' => now()->toIso8601String(), 'resumo' => $resumo,
            'categorias' => $dados['categorias'],
            'destaques' => $destaques,
            'projetos' => $paginacao->withQueryString(),
        ]);
    }
}
