<?php

namespace App\Http\Requests;

use App\Models\Projeto;
use App\Services\Avaliacao;
use Illuminate\Foundation\Http\FormRequest;

class SalvarAvaliacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->id, array_map('intval', $this->route('projeto')->avaliador_ids ?? []), true);
    }

    public function rules(Avaliacao $avaliacao): array
    {
        /** @var Projeto $projeto */
        $projeto = $this->route('projeto');
        $regras = $avaliacao->regras($projeto->subcategoria->categoria->codigo);
        $regras['justificativas'] = ['sometimes', 'nullable', 'array:c1,c2,c3,c4,bonus'];
        foreach (['c1', 'c2', 'c3', 'c4'] as $codigo) {
            $regras['justificativas.'.$codigo] = ['nullable', 'string', 'max:10000'];
        }
        $regras['justificativas.bonus'] = ['nullable', 'string', 'max:10000'];
        foreach (['categoria', 'categoria_id', 'subcategoria', 'subcategoria_id', 'chave', 'codigo', 'avaliador_id', 'nota_final', 'total', 'habilitada', 'notas'] as $campo) {
            $regras[$campo] = ['prohibited'];
        }

        return $regras;
    }
}
