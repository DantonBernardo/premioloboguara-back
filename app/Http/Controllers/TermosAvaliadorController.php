<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TermosAvaliadorController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(config('termos_avaliador'));
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'versao' => ['required', 'string', Rule::in([config('termos_avaliador.versao')])],
            'leitura_concluida' => ['required', 'accepted'],
            'aceito' => ['required', 'accepted'],
        ]);

        $usuario = DB::transaction(function () use ($request, $dados): User {
            $usuario = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($usuario->precisaAceitarTermos()) {
                $usuario->forceFill([
                    'termos_aceitos_em' => now(),
                    'termos_versao' => $dados['versao'],
                ])->save();
            }

            return $usuario;
        });

        return response()->json(['usuario' => $usuario->toPublicArray()]);
    }
}
