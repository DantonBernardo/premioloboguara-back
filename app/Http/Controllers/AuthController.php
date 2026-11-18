<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'email' => ['required', 'string', 'max:255'],
            'senha' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o e-mail ou contato.',
            'email.email' => 'Informe um e-mail válido.',
            'senha.required' => 'Informe a senha.',
        ]);

        $login = Str::lower(trim($dados['email']));
        $email = str_contains($login, '@') ? $login : preg_replace('/\D/', '', $login);
        $chave = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $segundos = RateLimiter::availableIn($chave);

            return response()->json([
                'erro' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ], 429);
        }

        $usuario = null;
        if (str_contains($login, '@')) {
            $usuario = User::whereRaw('LOWER(email) = ?', [$login])->where('ativo', true)->first();
        } elseif (strlen($email) >= 8 && preg_match('/^[0-9+().\s-]+$/', $login)) {
            $candidatos = User::where('ativo', true)->whereNotNull('contato')->get()->filter(fn (User $user): bool => preg_replace('/\D/', '', $user->contato) === $email);
            if ($candidatos->count() === 1) {
                $usuario = $candidatos->first();
            }
        }
        $ok = $usuario !== null && Auth::attempt(['id' => $usuario->id, 'password' => $dados['senha'], 'ativo' => true]);

        if (! $ok) {
            RateLimiter::hit($chave, 60);

            return response()->json(['erro' => 'Login ou senha inválidos.'], 401);
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
