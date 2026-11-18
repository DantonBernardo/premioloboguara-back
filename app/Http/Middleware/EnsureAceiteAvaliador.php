<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAceiteAvaliador
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->precisaAceitarTermos()) {
            return response()->json([
                'erro' => 'Leia e confirme os termos do avaliador antes de continuar.',
                'codigo' => 'aceite_termos_necessario',
            ], 403);
        }

        return $next($request);
    }
}
