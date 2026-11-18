<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Aplica explicitamente o lote de senhas individuais entregue em 17/11/2026. */
class AcessosAvaliadoresSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UsersSeeder::class);
        $senhas = json_decode(file_get_contents(__DIR__.'/senhas-iniciais-avaliadores.json'), true, flags: JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($senhas): void {
            foreach ($senhas as $email => $hash) {
                $usuario = User::where('email', $email)->where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->sole();
                $usuario->setRawAttributes(array_replace($usuario->getAttributes(), ['password' => $hash]));
                $usuario->remember_token = null;
                $usuario->save();
                DB::table('sessions')->where('user_id', $usuario->id)->delete();
            }
        });
        $this->command?->info(count($senhas).' acessos individuais aplicados.');
    }
}
