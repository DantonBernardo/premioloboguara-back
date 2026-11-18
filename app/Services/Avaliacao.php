<?php

namespace App\Services;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Avaliacao
{
    public function criterios(int|string $categoria): array
    {
        $regras = is_numeric($categoria)
            ? collect(config('avaliacao.categorias'))->firstWhere('codigo', (int) $categoria)
            : (config('avaliacao.categorias')[$categoria] ?? null);
        if ($regras === null) {
            throw ValidationException::withMessages(['categoria' => 'Categoria de avaliação inválida.']);
        }

        return $regras;
    }

    public function regras(int|string $categoria): array
    {
        $config = $this->criterios($categoria);
        $regras = [];
        foreach ($config['criterios'] as $codigo => $criterio) {
            $regras[$codigo] = ['required', 'numeric', 'min:0', 'max:'.$criterio['maximo']];
        }
        $regras['bonus'] = ['sometimes', 'required', 'numeric', 'min:0', 'max:'.$config['bonus_maximo']];

        return $regras;
    }

    public function calcular(int|string $categoria, array $notas): array
    {
        $valores = Validator::make($notas, $this->regras($categoria))->validate();
        $config = $this->criterios($categoria);
        $zeroEliminatorio = false;
        foreach ($config['criterios'] as $codigo => $criterio) {
            $valores[$codigo] = (float) $valores[$codigo];
            $zeroEliminatorio = $zeroEliminatorio || ($criterio['maximo'] === 3 && $valores[$codigo] === 0.0);
        }
        $valores['bonus'] = (float) ($valores['bonus'] ?? 0);
        $total = min((float) config('avaliacao.nota_maxima'), round(array_sum($valores), 10));

        return $valores + [
            'total' => $total,
            'zero_eliminatorio' => $zeroEliminatorio,
            'habilitada' => ! $zeroEliminatorio && $total >= config('avaliacao.nota_corte'),
        ];
    }

    public function normalizar(int|string $categoria, array $notas): array
    {
        Validator::make(['notas' => $notas], [
            'notas' => ['array', 'list', 'max:'.config('avaliacao.limite_avaliacoes')],
            'notas.*' => ['required', 'array'],
            'notas.*.avaliador_id' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();

        return array_map(function (array $nota) use ($categoria): array {
            return array_merge($nota, $this->calcular($categoria, $nota), ['avaliador_id' => (int) $nota['avaliador_id']]);
        }, $notas);
    }

    public function media(array $notas): ?float
    {
        return $notas === [] ? null : round(array_sum(array_column($notas, 'total')) / count($notas), 2);
    }

    public function salvar(Projeto $projeto, User $avaliador, array $dados): Projeto
    {
        return DB::transaction(function () use ($projeto, $avaliador, $dados): Projeto {
            $atual = Projeto::query()->lockForUpdate()->findOrFail($projeto->id);
            abort_unless($avaliador->ativo && $avaliador->perfil === User::PERFIL_AVALIADOR
                && in_array($avaliador->id, array_map('intval', $atual->avaliador_ids ?? []), true), 403);

            $notas = $atual->notas ?? [];
            $indice = collect($notas)->search(fn (array $nota): bool => (int) $nota['avaliador_id'] === $avaliador->id);
            if ($indice !== false) {
                throw ValidationException::withMessages([
                    'avaliacao' => 'Esta avaliação já foi publicada. Para alterações na nota após a publicação, contate a SCTI pelo WhatsApp +55 42 3142-1883.',
                ]);
            }
            $nova = $this->calcular($atual->subcategoria->categoria->codigo, $dados) + [
                'avaliador_id' => $avaliador->id,
                'enviada_em' => now()->toISOString(),
                'justificativa_geral' => $dados['justificativa_geral'] ?? null,
                'justificativas' => array_filter($dados['justificativas'] ?? [], fn ($texto): bool => $texto !== null && $texto !== ''),
            ];

            if (count($notas) >= config('avaliacao.limite_avaliacoes')) {
                throw ValidationException::withMessages(['notas' => 'O projeto já recebeu o máximo de duas avaliações.']);
            }
            $notas[] = $nova;

            $atual->notas = $notas;
            $atual->save();
            DB::table('avaliacao_rascunhos')->where('projeto_id', $atual->id)->where('avaliador_id', $avaliador->id)->delete();

            return $atual;
        }, 3);
    }
}
