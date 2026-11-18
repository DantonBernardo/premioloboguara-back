<?php

namespace App\Http\Requests;

use App\Models\Projeto;
use App\Services\Avaliacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalvarAvaliacaoRequest extends FormRequest
{
    private const MINIMO_JUSTIFICATIVA = 20;

    protected function prepareForValidation(): void
    {
        $normalizar = fn (mixed $texto): mixed => is_string($texto)
            ? trim(preg_replace('/[\p{Z}\s]+/u', ' ', preg_replace('/\p{Cf}/u', '', $texto)))
            : $texto;
        $dados = ['justificativa_geral' => $normalizar($this->input('justificativa_geral'))];
        if (is_array($this->input('justificativas'))) {
            $dados['justificativas'] = array_map($normalizar, $this->input('justificativas'));
        }
        $this->merge($dados);
    }

    public function authorize(): bool
    {
        return in_array($this->user()->id, array_map('intval', $this->route('projeto')->avaliador_ids ?? []), true);
    }

    public function rules(Avaliacao $avaliacao): array
    {
        /** @var Projeto $projeto */
        $projeto = $this->route('projeto');
        $categoria = $projeto->subcategoria->categoria->codigo;
        $regras = $avaliacao->regras($categoria);
        $criterios = $avaliacao->criterios($categoria)['criterios'];
        $regras['justificativa_geral'] = ['nullable', 'string', 'max:10000'];
        $regras['justificativas'] = ['sometimes', 'nullable', 'array:c1,c2,c3,c4,bonus'];
        foreach (['c1', 'c2', 'c3', 'c4'] as $codigo) {
            $obrigatoria = $criterios[$codigo]['maximo'] === 3 && is_numeric($this->input($codigo)) && (float) $this->input($codigo) === 0.0;
            $regras['justificativas.'.$codigo] = [Rule::requiredIf($obrigatoria), 'nullable', 'string', 'max:10000'];
            if ($obrigatoria) {
                $regras['justificativas.'.$codigo][] = 'min:'.self::MINIMO_JUSTIFICATIVA;
            }
        }
        $regras['justificativas.bonus'] = ['nullable', 'string', 'max:10000'];
        foreach (['categoria', 'categoria_id', 'subcategoria', 'subcategoria_id', 'chave', 'codigo', 'avaliador_id', 'nota_final', 'total', 'habilitada', 'notas'] as $campo) {
            $regras[$campo] = ['prohibited'];
        }

        return $regras;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $categoria = $this->route('projeto')->subcategoria->categoria->codigo;
            $nota = app(Avaliacao::class)->calcular($categoria, $this->all());
            if (($nota['total'] < 5 || $nota['total'] === 10.0) && mb_strlen((string) $this->input('justificativa_geral')) < self::MINIMO_JUSTIFICATIVA) {
                $validator->errors()->add('justificativa_geral', 'Justifique a nota total inferior a 5 ou igual a 10 com pelo menos '.self::MINIMO_JUSTIFICATIVA.' caracteres. Espaços em excesso não contam.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'justificativas.*.required' => 'Justificativa obrigatória: nota zero em critério de máximo 3 pontos. Escreva pelo menos '.self::MINIMO_JUSTIFICATIVA.' caracteres.',
            'justificativas.*.min' => 'A justificativa deve ter pelo menos :min caracteres. Espaços em excesso não contam.',
        ];
    }
}
