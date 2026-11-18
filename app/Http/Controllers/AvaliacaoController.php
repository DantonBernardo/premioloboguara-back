<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarAvaliacaoRequest;
use App\Models\Categoria;
use App\Models\Projeto;
use App\Services\Avaliacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AvaliacaoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'categoria' => ['nullable', 'integer', 'exists:categorias,codigo'],
            'status' => ['nullable', Rule::in(['pendentes', 'avaliados'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $id = $request->user()->id;
        $base = Projeto::query()->where(function ($query) use ($id): void {
            $query->whereJsonContains('avaliador_ids', $id)->orWhereJsonContains('avaliador_ids', (string) $id);
        });
        $temNota = DB::getDriverName() === 'pgsql'
            ? "EXISTS (SELECT 1 FROM jsonb_array_elements(projetos.notas::jsonb) n WHERE (n->>'avaliador_id')::bigint = ?)"
            : "EXISTS (SELECT 1 FROM json_each(projetos.notas) n WHERE CAST(json_extract(n.value, '$.avaliador_id') AS INTEGER) = ?)";
        $total = (clone $base)->count();
        $avaliados = (clone $base)->whereRaw($temNota, [$id])->count();
        $consulta = $base->with('subcategoria.categoria')->select('projetos.*')->addSelect([
            'rascunho_em' => DB::table('avaliacao_rascunhos')->select('updated_at')
                ->whereColumn('projeto_id', 'projetos.id')->where('avaliador_id', $id)->limit(1),
        ]);
        if (! empty($filtros['status'])) {
            $consulta->whereRaw(($filtros['status'] === 'pendentes' ? 'NOT ' : '').$temNota, [$id]);
        }
        if (isset($filtros['categoria'])) {
            $consulta->whereHas('subcategoria.categoria', fn ($query) => $query->where('codigo', $filtros['categoria']));
        }
        if (! empty($filtros['busca'])) {
            $termo = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($filtros['busca'])).'%';
            $consulta->where(function ($query) use ($termo): void {
                $query->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereRaw("LOWER(codigo) LIKE ? ESCAPE '!'", [$termo])
                    ->orWhereHas('subcategoria', fn ($sub) => $sub->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$termo]));
            });
        }
        if (($filtros['status'] ?? null) === 'avaliados') {
            $dataAvaliacao = DB::getDriverName() === 'pgsql'
                ? "COALESCE((SELECT NULLIF(n->>'enviada_em', '')::timestamptz FROM jsonb_array_elements(projetos.notas::jsonb) n WHERE (n->>'avaliador_id')::bigint = ? LIMIT 1), projetos.updated_at)"
                : "julianday(COALESCE((SELECT NULLIF(json_extract(n.value, '$.enviada_em'), '') FROM json_each(projetos.notas) n WHERE CAST(json_extract(n.value, '$.avaliador_id') AS INTEGER) = ? LIMIT 1), projetos.updated_at))";
            $consulta->orderByRaw($dataAvaliacao.' DESC', [$id]);
        }
        $projetos = $consulta->orderByDesc('id')->paginate(10)->withQueryString()->through(function (Projeto $projeto) use ($id): array {
            $nota = collect($projeto->notas ?? [])->firstWhere('avaliador_id', $id);

            return [
                'id' => $projeto->id, 'codigo' => $projeto->codigo, 'nome' => $projeto->nome,
                'categoria' => $projeto->subcategoria->categoria->nome,
                'categoria_codigo' => $projeto->subcategoria->categoria->codigo,
                'subcategoria' => $projeto->subcategoria->nome,
                'avaliada' => $nota !== null, 'minha_nota' => $nota['total'] ?? null,
                'minha_habilitacao' => $nota['habilitada'] ?? null,
                'rascunho_em' => $projeto->rascunho_em,
            ];
        });

        return response()->json([
            'resumo' => ['designados' => $total, 'avaliados' => $avaliados, 'pendentes' => $total - $avaliados],
            'categorias' => Categoria::orderBy('codigo')->get(['codigo', 'nome']),
            'projetos' => $projetos,
        ]);
    }

    public function showPorCodigo(Request $request, string $chave, string $categoria, string $subcategoria, Avaliacao $avaliacao): JsonResponse
    {
        $projeto = Projeto::query()->where('codigo', $chave.'/'.$categoria.'/'.$subcategoria)->firstOrFail();

        return $this->show($request, $projeto, $avaliacao);
    }

    public function show(Request $request, Projeto $projeto, Avaliacao $avaliacao): JsonResponse
    {
        abort_unless(in_array($request->user()->id, array_map('intval', $projeto->avaliador_ids ?? []), true), 403);
        $rascunho = DB::table('avaliacao_rascunhos')->where('projeto_id', $projeto->id)->where('avaliador_id', $request->user()->id)->first();

        return response()->json([
            'projeto' => $projeto->only(['id', 'codigo', 'nome', 'subcategoria_id', 'documento_url']) + [
                'categoria' => $projeto->subcategoria->categoria->nome,
                'subcategoria' => $projeto->subcategoria->nome,
            ],
            'regras' => $avaliacao->criterios($projeto->subcategoria->categoria->codigo),
            'nota_maxima' => config('avaliacao.nota_maxima'),
            'nota_corte' => config('avaliacao.nota_corte'),
            'avaliacao' => collect($projeto->notas ?? [])->firstWhere('avaliador_id', $request->user()->id),
            'rascunho' => $rascunho ? json_decode($rascunho->dados, true, 512, JSON_THROW_ON_ERROR) + ['salvo_em' => $rascunho->updated_at] : null,
        ]);
    }

    public function update(SalvarAvaliacaoRequest $request, Projeto $projeto, Avaliacao $avaliacao): JsonResponse
    {
        $salvo = $avaliacao->salvar($projeto, $request->user(), $request->validated());

        return response()->json([
            'projeto_id' => $salvo->id,
            'codigo' => $salvo->codigo,
            'avaliacao' => collect($salvo->notas)->firstWhere('avaliador_id', $request->user()->id),
            'avaliacoes_recebidas' => count($salvo->notas),
        ]);
    }
}
