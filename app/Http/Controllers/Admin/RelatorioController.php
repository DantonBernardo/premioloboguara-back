<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Projeto;
use App\Models\User;
use App\Services\GraficosRelatorio;
use App\Services\Relatorios;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RelatorioController extends Controller
{
    public function __construct(private readonly Relatorios $relatorios) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'busca' => ['nullable', 'string', 'max:150'],
            'andamentos' => ['sometimes', 'array', 'list', 'max:3'],
            'andamentos.*' => ['required', 'string', 'distinct', Rule::in(['aguardando', 'em_processo', 'finalizados'])],
            'andamento' => ['nullable', Rule::in(['aguardando', 'em_processo', 'finalizados'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', Rule::in([10, 25, 50])],
        ]);
        $dados = $this->dados($request);
        $dados['totais']['avaliados'] = count($dados['ranking']);
        $dados['totais']['maior_media'] = $dados['ranking'][0]['nota_final'] ?? null;
        $posicoes = collect($dados['ranking'])->pluck('posicao', 'id');
        $projetos = collect($dados['projetos'])->sortByDesc(fn ($projeto) => $projeto['nota_final'] ?? -1)
            ->map(fn ($projeto) => $projeto + ['posicao' => $posicoes[$projeto['id']] ?? null]);
        if (! empty($filtros['busca'])) {
            $termo = Str::lower($filtros['busca']);
            $projetos = $projetos->filter(fn ($projeto) => str_contains(Str::lower($projeto['nome'].' '.$projeto['codigo'].' '.$projeto['subcategoria'].' '.$projeto['categoria']), $termo));
        }
        $andamentos = $filtros['andamentos'] ?? (isset($filtros['andamento']) ? [$filtros['andamento']] : []);
        if ($andamentos !== []) {
            $projetos = $projetos->filter(function (array $projeto) use ($andamentos): bool {
                $andamento = match ($projeto['avaliacoes_recebidas']) {
                    0 => 'aguardando',
                    1, 2 => 'em_processo',
                    default => 'finalizados',
                };

                return in_array($andamento, $andamentos, true);
            });
        }
        $tamanho = (int) ($filtros['por_pagina'] ?? 10);
        $pagina = (int) ($filtros['page'] ?? 1);
        $dados['projetos'] = (new LengthAwarePaginator($projetos->forPage($pagina, $tamanho)->values(), $projetos->count(), $tamanho, $pagina, ['path' => $request->url()]))->withQueryString();
        unset($dados['ranking']);

        return response()->json($dados);
    }

    public function pdf(Request $request): Response
    {
        $request->validate(['tipo' => ['sometimes', Rule::in(['geral', 'resultados'])]]);
        $dados = $this->dados($request);
        if ($request->input('tipo') === 'resultados') {
            $projetos = collect($dados['projetos'])
                ->filter(fn (array $projeto): bool => $projeto['avaliacoes_recebidas'] === 3 && in_array($projeto['status'], ['habilitada', 'inabilitada'], true))
                ->sortByDesc('nota_final')->values();

            return Pdf::loadView('relatorios.resultados', ['projetos' => $projetos, 'agora' => now()])
                ->setPaper('a4')->download('resultados-premio-lobo-guara-'.now()->format('Y-m-d-His').'.pdf');
        }
        $originais = Projeto::whereIn('id', array_column($dados['projetos'], 'id'))->get(['id', 'avaliador_ids', 'notas'])->keyBy('id');
        $ids = $originais->flatMap(fn (Projeto $projeto): array => array_merge($projeto->avaliador_ids ?? [], array_column($projeto->notas ?? [], 'avaliador_id')))->unique();
        $nomes = User::whereIn('id', $ids)->pluck('name', 'id');
        foreach ($dados['projetos'] as &$projeto) {
            $original = $originais[$projeto['id']];
            $concluidos = collect($original->notas ?? [])->pluck('avaliador_id')->map(fn ($id): int => (int) $id);
            $projeto['avaliadores'] = collect($original->avaliador_ids ?? [])->merge($concluidos)->map(fn ($id): int => (int) $id)->unique()->values()
                ->map(fn (int $id): array => ['nome' => $nomes[$id] ?? 'Avaliador indisponível (#'.$id.')', 'concluida' => $concluidos->contains($id)])->all();
        }
        unset($projeto);
        $arquivo = 'relatorio-premio-lobo-guara-'.now()->format('Y-m-d-His').'.pdf';

        return Pdf::loadView('relatorios.pdf', ['r' => $dados, 'agora' => now(), 'graficos' => app(GraficosRelatorio::class)->gerar($dados)])
            ->setPaper('a4')
            ->download($arquivo);
    }

    public function equipePdf(Request $request): Response
    {
        $dados = $this->dados($request);

        return Pdf::loadView('relatorios.equipe', ['avaliadores' => $dados['avaliadores'], 'agora' => now()])
            ->setPaper('a4')->download('relatorio-equipe-'.now()->format('Y-m-d').'.pdf');
    }

    public function avaliador(Request $request, User $avaliador): JsonResponse
    {
        $filtros = $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $dados = $this->fichaAvaliador($avaliador);
        $pagina = (int) ($filtros['page'] ?? 1);
        $projetos = $dados['projetos'];
        $dados['projetos'] = (new LengthAwarePaginator($projetos->forPage($pagina, 10)->values(), $projetos->count(), 10, $pagina, ['path' => $request->url()]))->withQueryString();

        return response()->json($dados);
    }

    public function avaliadorPdf(User $avaliador): Response
    {
        return Pdf::loadView('relatorios.ficha-avaliador', ['ficha' => $this->fichaAvaliador($avaliador), 'agora' => now()])
            ->setPaper('a4')->download('ficha-avaliador-'.$avaliador->id.'.pdf');
    }

    private function fichaAvaliador(User $avaliador): array
    {
        abort_unless($avaliador->perfil === User::PERFIL_AVALIADOR, 404);
        $projetos = Projeto::with('subcategoria.categoria')->where(function ($query) use ($avaliador): void {
            $query->whereJsonContains('avaliador_ids', $avaliador->id)->orWhereJsonContains('avaliador_ids', (string) $avaliador->id);
        })->orderByDesc('id')->get()->map(function (Projeto $projeto) use ($avaliador): array {
            $nota = collect($projeto->notas ?? [])->firstWhere('avaliador_id', $avaliador->id);

            return [
                'id' => $projeto->id, 'nome' => $projeto->nome, 'codigo' => $projeto->codigo,
                'categoria' => $projeto->subcategoria->categoria->nome, 'categoria_codigo' => $projeto->subcategoria->categoria->codigo,
                'subcategoria' => $projeto->subcategoria->nome, 'avaliada' => $nota !== null,
                'minha_nota' => $nota['total'] ?? null, 'minha_habilitacao' => $nota['habilitada'] ?? null,
            ];
        });
        $concluidas = $projetos->where('avaliada', true)->count();

        return [
            'avaliador' => ['id' => $avaliador->id, 'nome' => $avaliador->name, 'email' => $avaliador->email, 'contato' => $avaliador->contato, 'organizacao' => $avaliador->organizacao, 'ativo' => $avaliador->ativo],
            'resumo' => ['designados' => $projetos->count(), 'concluidas' => $concluidas, 'pendentes' => $projetos->count() - $concluidas],
            'projetos' => $projetos,
        ];
    }

    private function dados(Request $request): array
    {
        $filtros = $request->validate([
            'categoria' => ['nullable', 'integer', 'exists:categorias,codigo'],
            'categorias' => ['sometimes', 'array', 'list', 'max:4'],
            'categorias.*' => ['required', 'integer', 'distinct', 'exists:categorias,codigo'],
            'situacoes' => ['sometimes', 'array', 'list', 'max:4'],
            'situacoes.*' => ['required', 'string', 'distinct', Rule::in(array_keys(Relatorios::STATUS))],
            'status' => ['nullable', Rule::in(array_keys(Relatorios::STATUS))],
        ]);

        return $this->relatorios->gerar(
            $filtros['categorias'] ?? (isset($filtros['categoria']) ? (int) $filtros['categoria'] : null),
            $filtros['situacoes'] ?? $filtros['status'] ?? null,
        );
    }
}
