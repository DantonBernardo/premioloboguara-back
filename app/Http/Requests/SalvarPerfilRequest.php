<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->ativo === true;
    }

    public function rules(): array
    {
        return ['nome' => ['required', 'string', 'max:150']];
    }

    public function messages(): array
    {
        return ['nome.required' => 'Informe seu nome.', 'nome.max' => 'O nome deve ter no máximo 150 caracteres.'];
    }
}
