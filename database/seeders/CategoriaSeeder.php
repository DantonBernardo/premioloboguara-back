<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (config('categorias') as $codigo => $dados) {
                DB::table('categorias')->insertOrIgnore([
                    'codigo' => $codigo, 'nome' => $dados['nome'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $categoriaId = DB::table('categorias')->where('codigo', $codigo)->value('id');
                foreach ($dados['subcategorias'] as $subcodigo => $nome) {
                    DB::table('subcategorias')->insertOrIgnore([
                        'categoria_id' => $categoriaId, 'codigo' => $subcodigo, 'nome' => $nome,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
