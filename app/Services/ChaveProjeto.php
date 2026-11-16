<?php

namespace App\Services;

use App\Models\Projeto;
use RuntimeException;

class ChaveProjeto
{
    public function gerar(): string
    {
        for ($tentativa = 0; $tentativa < 20; $tentativa++) {
            $chave = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            if (! Projeto::query()->where('chave', $chave)->exists()) {
                return $chave;
            }
        }

        throw new RuntimeException('Não foi possível gerar uma chave única para o projeto.');
    }
}
