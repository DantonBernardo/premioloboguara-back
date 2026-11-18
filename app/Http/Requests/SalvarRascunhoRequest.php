<?php

namespace App\Http\Requests;

use App\Services\Avaliacao;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SalvarRascunhoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()->id, array_map('intval', $this->route('projeto')->avaliador_ids ?? []), true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(Avaliacao $avaliacao): array
    {
        $config = $avaliacao->criterios($this->route('projeto')->subcategoria->categoria->codigo);
        $regras = [
            'notas' => ['required', 'array:c1,c2,c3,c4,bonus'],
            'justificativas' => ['sometimes', 'nullable', 'array:c1,c2,c3,c4,bonus'],
            'justificativas.*' => ['nullable', 'string', 'max:10000'],
            'justificativa_geral' => ['nullable', 'string', 'max:10000'],
            'etapa' => ['required', 'integer', 'min:0', 'max:'.(count($config['criterios']) + ($config['bonus_maximo'] > 0 ? 1 : 0))],
        ];
        foreach ($config['criterios'] as $codigo => $criterio) {
            $regras['notas.'.$codigo] = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:'.$criterio['maximo']];
        }
        $regras['notas.bonus'] = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:'.$config['bonus_maximo']];

        return $regras;
    }
}
