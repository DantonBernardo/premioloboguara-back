<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Relatórios do painel admin, seguindo a Ficha de Avaliação do Edital nº 02/2026 (Art. 15º):
 *  - Nota de cada avaliação = MÍN(10,0; C1 + C2 + C3 + C4 + Bônus)
 *  - Nota final do projeto = média das avaliações recebidas
 *  - Habilitada: nota final ≥ 7,00 e nenhuma nota 0,0 em critério de peso 3,0 da categoria
 *
 * Formato esperado de cada item em projetos.notas:
 *   {"avaliador_id": 2, "c1": 2.5, "c2": 3.0, "c3": 1.5, "c4": 2.0, "bonus": 0}
 */
class Relatorios
{
    public const NOTA_CORTE = 7.0;

    public const NOTA_MAXIMA = 10.0;

    public const STATUS = [
        'habilitada' => 'Habilitada',
        'inabilitada' => 'Inabilitada',
        'em_avaliacao' => 'Em avaliação',
        'sem_avaliacao' => 'Sem avaliação',
    ];

    public function gerar(int|array|null $categoria = null, string|array|null $status = null): array
    {
        $situacoes = (array) $status;
        $projetos = $this->projetosAnalisados($categoria)
            ->when($situacoes !== [], fn (Collection $projetos) => $projetos->whereIn('status', $situacoes)->values());
        $avaliadores = User::query()
            ->where('perfil', User::PERFIL_AVALIADOR)
            ->where('ativo', true)
            ->orderBy('name')
            ->get(['id', 'name', 'ativo']);

        return [
            'gerado_em' => now()->toIso8601String(),
            'categorias' => Categoria::query()->orderBy('codigo')->get(['codigo', 'nome'])->toArray(),
            'nota_corte' => self::NOTA_CORTE,
            'filtros' => ['categoria' => $categoria, 'status' => $status],
            'totais' => $this->totais($projetos),
            'por_status' => $this->porStatus($projetos),
            'por_categoria' => $this->porCategoria($projetos),
            'distribuicao_notas' => collect([
                ['rotulo' => '0 a 4,99', 'min' => 0, 'max' => 5],
                ['rotulo' => '5 a 6,99', 'min' => 5, 'max' => 7],
                ['rotulo' => '7 a 8,49', 'min' => 7, 'max' => 8.5],
                ['rotulo' => '8,5 a 10', 'min' => 8.5, 'max' => 10.01],
            ])->map(fn (array $faixa): array => [
                'rotulo' => $faixa['rotulo'],
                'total' => $projetos->filter(fn (array $projeto): bool => $projeto['nota_final'] !== null && $projeto['nota_final'] >= $faixa['min'] && $projeto['nota_final'] < $faixa['max'])->count(),
            ])->all(),
            'ranking' => $projetos
                ->filter(fn (array $p) => $p['nota_final'] !== null)
                ->sortByDesc('nota_final')
                ->values()
                ->map(fn (array $p, int $i) => ['posicao' => $i + 1] + $p)
                ->all(),
            'projetos' => $projetos->all(),
            'avaliadores' => $this->progressoAvaliadores($projetos, $avaliadores),
        ];
    }

    public function projetosAnalisados(int|array|null $categoria = null): Collection
    {
        $categorias = (array) $categoria;

        return Projeto::query()->with('subcategoria.categoria')
            ->when($categorias !== [], fn ($query) => $query->whereHas('subcategoria.categoria', fn ($query) => $query->whereIn('codigo', $categorias)))
            ->orderBy('id')->get()->map(fn (Projeto $projeto): array => $this->analisarProjeto($projeto));
    }

    private function analisarProjeto(Projeto $projeto): array
    {
        $notas = collect($projeto->notas ?? [])->filter(fn ($n) => is_array($n) && isset($n['avaliador_id']));
        $designados = collect($projeto->avaliador_ids ?? [])->map(fn ($id) => (int) $id)->unique();

        $calculadas = app(Avaliacao::class)->normalizar($projeto->subcategoria->categoria->codigo, $notas->values()->all());
        $notaFinal = $projeto->nota_final;
        $zeroEmPeso3 = collect($calculadas)->contains('zero_eliminatorio', true);

        $status = match (true) {
            $zeroEmPeso3 => 'inabilitada',
            $notas->isEmpty() => 'sem_avaliacao',
            $notas->count() < config('avaliacao.limite_avaliacoes') => 'em_avaliacao',
            $notaFinal >= self::NOTA_CORTE && ! $zeroEmPeso3 => 'habilitada',
            default => 'inabilitada',
        };

        return [
            'id' => $projeto->id,
            'codigo' => $projeto->codigo,
            'nome' => $projeto->nome,
            'categoria' => $projeto->subcategoria->categoria->nome,
            'categoria_codigo' => $projeto->subcategoria->categoria->codigo,
            'subcategoria' => $projeto->subcategoria->nome,
            'avaliacoes_recebidas' => $notas->count(),
            'avaliacoes_previstas' => max($designados->count(), $notas->count()),
            'nota_final' => $notaFinal,
            'status' => $status,
            'status_rotulo' => self::STATUS[$status],
        ];
    }

    private function totais(Collection $projetos): array
    {
        $previstas = $projetos->sum('avaliacoes_previstas');
        $recebidas = $projetos->sum('avaliacoes_recebidas');
        $comNota = $projetos->whereNotNull('nota_final');

        return [
            'projetos' => $projetos->count(),
            'avaliacoes_previstas' => $previstas,
            'avaliacoes_recebidas' => $recebidas,
            'percentual_concluido' => $previstas > 0 ? round($recebidas / $previstas * 100, 1) : 0.0,
            'media_geral' => $comNota->isEmpty() ? null : round($comNota->avg('nota_final'), 2),
            'habilitadas' => $projetos->where('status', 'habilitada')->count(),
        ];
    }

    private function porStatus(Collection $projetos): array
    {
        return collect(self::STATUS)
            ->map(fn (string $rotulo, string $chave) => [
                'status' => $chave,
                'rotulo' => $rotulo,
                'total' => $projetos->where('status', $chave)->count(),
            ])
            ->values()
            ->all();
    }

    private function porCategoria(Collection $projetos): array
    {
        return $projetos
            ->groupBy('categoria')
            ->map(function (Collection $grupo, string $categoria) {
                $comNota = $grupo->whereNotNull('nota_final');

                return [
                    'codigo' => $grupo->first()['categoria_codigo'],
                    'categoria' => $categoria,
                    'total' => $grupo->count(),
                    'habilitadas' => $grupo->where('status', 'habilitada')->count(),
                    'media' => $comNota->isEmpty() ? null : round($comNota->avg('nota_final'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function progressoAvaliadores(Collection $projetos, Collection $avaliadores): array
    {
        $todos = Projeto::query()->whereIn('id', $projetos->pluck('id'))->get(['id', 'notas', 'avaliador_ids']);

        return $avaliadores->map(function (User $a) use ($todos) {
            $designados = $todos->filter(fn (Projeto $p) => in_array($a->id, array_map('intval', $p->avaliador_ids ?? []), true))->count();
            $feitas = $todos->filter(
                fn (Projeto $p) => collect($p->notas ?? [])->contains(fn ($n) => (int) ($n['avaliador_id'] ?? 0) === $a->id)
            )->count();

            return [
                'id' => $a->id,
                'nome' => $a->name,
                'ativo' => $a->ativo,
                'designados' => max($designados, $feitas),
                'concluidas' => $feitas,
                'pendentes' => max(0, $designados - $feitas),
            ];
        })->values()->all();
    }
}
