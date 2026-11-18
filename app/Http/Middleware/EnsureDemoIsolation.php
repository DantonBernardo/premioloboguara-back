<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoIsolation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->perfil === User::PERFIL_DEMO && $request->is('api/*')) {
            $permitidas = [
                'GET api/csrf',
                'GET api/auth/me',
                'POST api/auth/login',
                'POST api/auth/logout',
            ];
            if (! in_array($request->method().' '.$request->path(), $permitidas, true)) {
                return response()->json(['erro' => 'A conta de demonstração não pode acessar ou alterar dados reais.'], 403);
            }
        }

        return $next($request);
    }
}
