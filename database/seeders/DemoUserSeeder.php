<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demo = User::firstOrNew(['email' => 'demo@premioloboguara.local']);
        if ($demo->exists && $demo->perfil !== User::PERFIL_DEMO) {
            throw new LogicException('O e-mail da demonstração já pertence a outra conta.');
        }
        if (! $demo->exists) {
            $demo->fill([
                'name' => 'Avaliador de demonstração',
                'password' => 'DemoLobo26!',
                'perfil' => User::PERFIL_DEMO,
                'ativo' => true,
                'organizacao' => 'Ambiente de demonstração',
            ])->save();
        }
    }
}
