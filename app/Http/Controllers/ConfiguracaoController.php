<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarPerfilRequest;
use Illuminate\Http\JsonResponse;

class ConfiguracaoController extends Controller
{
    public function update(SalvarPerfilRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $usuario->update(['name' => $request->validated('nome')]);

        return response()->json(['usuario' => $usuario->toPublicArray()]);
    }
}
