<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CriarAdmin extends Command
{
    protected $signature = 'admin:criar {--nome=} {--email=}';

    protected $description = 'Cria ou atualiza um administrador (a senha é pedida de forma oculta).';

    public function handle(): int
    {
        $nome = $this->option('nome') ?: $this->ask('Nome');
        $email = Str::lower(trim($this->option('email') ?: $this->ask('E-mail')));
        $senha = $this->secret('Senha (mínimo '.User::SENHA_MINIMA.' caracteres)');

        $validator = Validator::make(
            ['nome' => $nome, 'email' => $email, 'senha' => $senha],
            [
                'nome' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:255'],
                'senha' => ['required', 'string', 'min:'.User::SENHA_MINIMA],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $nome, 'password' => $senha, 'perfil' => User::PERFIL_ADMIN, 'ativo' => true],
        );

        $this->info("Administrador salvo: {$email}");

        return self::SUCCESS;
    }
}
