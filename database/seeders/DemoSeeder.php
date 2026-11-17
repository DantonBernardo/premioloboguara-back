<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Mantém compatibilidade com o comando antigo sem recriar dados fictícios. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProjetoSeeder::class);
    }
}
