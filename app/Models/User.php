<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'contato', 'organizacao', 'password', 'perfil', 'ativo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PERFIL_ADMIN = 'admin';

    public const PERFIL_AVALIADOR = 'avaliador';

    public const PERFIS = [self::PERFIL_ADMIN, self::PERFIL_AVALIADOR];

    /** Regra única de senha do sistema: mínimo de 8 caracteres, sem exigência de símbolos. */
    public const SENHA_MINIMA = 8;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_login' => 'datetime',
            'ativo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->perfil === self::PERFIL_ADMIN;
    }

    /** Dados seguros para enviar ao front-end. */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->name,
            'email' => $this->email,
            'contato' => $this->contato,
            'organizacao' => $this->organizacao,
            'perfil' => $this->perfil,
        ];
    }
}
