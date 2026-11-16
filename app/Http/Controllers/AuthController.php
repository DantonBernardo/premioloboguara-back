<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Inicializa o cookie XSRF-TOKEN usado pelo front-end. */
    public function csrf(): Response
    {
        return response()->noContent();
    }

    public function login(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'senha' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'senha.required' => 'Informe a senha.',
        ]);

        $email = Str::lower(trim($dados['email']));
        $chave = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $segundos = RateLimiter::availableIn($chave);

            return response()->json([
                'erro' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429);
        }

        $ok = Auth::attempt(['email' => $email, 'password' => $dados['senha'], 'ativo' => true]);

        if (! $ok) {
            RateLimiter::hit($chave, 60);

            return response()->json(['erro' => 'E-mail ou senha inválidos.'], 401);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        $usuario = $request->user();
        $usuario->forceFill(['ultimo_login' => now()])->save();

        return response()->json(['usuario' => $usuario->toPublicArray()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->ativo) {
            return response()->json(['erro' => 'Não autenticado.'], 401);
        }

        return response()->json(['usuario' => $usuario->toPublicArray()]);
    }
}
