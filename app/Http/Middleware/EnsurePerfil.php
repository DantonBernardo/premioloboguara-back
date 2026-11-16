<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso nas rotas: ->middleware('perfil:admin') */
class EnsurePerfil
{
    public function handle(Request $request, Closure $next, string $perfil): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->ativo) {
            return response()->json(['erro' => 'Não autenticado.'], 401);
        }

        if ($usuario->perfil !== $perfil) {
            return response()->json(['erro' => 'Acesso negado.'], 403);
        }

        return $next($request);
    }
}
