<?php

namespace Database\Seeders;

use App\Models\Projeto;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de EXEMPLO para desenvolvimento (não usar em produção).
 * php artisan db:seed --class=DemoSeeder
 * Logins: admin@dev.local e avaliador1..4@dev.local, senha "senha123".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Este seeder APAGA todos os projetos. Só roda em ambiente local, nunca em produção.
        if (! app()->environment('local')) {
            $this->command?->error('DemoSeeder bloqueado: só pode rodar com APP_ENV=local.');

            return;
        }

        User::updateOrCreate(['email' => 'admin@dev.local'], [
            'name' => 'Admin Dev', 'password' => 'senha123', 'perfil' => User::PERFIL_ADMIN,
        ]);

        $nomes = ['Ana Ribeiro', 'Bruno Kowalski', 'Carla Mendes', 'Diego Prestes'];
        $avaliadores = collect($nomes)->map(fn ($nome, $i) => User::updateOrCreate(
            ['email' => 'avaliador'.($i + 1).'@dev.local'],
            ['name' => $nome, 'password' => 'senha123', 'perfil' => User::PERFIL_AVALIADOR, 'ativo' => $i !== 3],
        ));
        $ids = $avaliadores->pluck('id')->all();
        $this->call(CategoriaSeeder::class);

        $projetos = [
            ['Horta escolar com sensores de umidade', 'Professor Inovador', 'Ensino Fundamental II', [[2.8, 2.5, 1.8, 1.9], [2.6, 2.7, 1.7, 1.8]]],
            ['Robótica com sucata eletrônica', 'Professor Inovador', 'Ensino Médio', [[2.4, 2.2, 1.6, 1.5], [2.5, 2.4, 1.5, 1.7]]],
            ['Gamificação no ensino de frações', 'Professor Inovador', 'Ensino Fundamental I', [[2.0, 1.8, 1.2, 1.4]]],
            ['Monitoramento hídrico por IoT', 'Projeto Inovador', 'Empreendedorismo Inovador', [[2.9, 2.8, 1.9, 1.9, 0.5], [2.7, 2.9, 1.8, 1.8, 0.5]]],
            ['App de coleta seletiva do bairro', 'Projeto Inovador', 'Empreendedorismo Social', [[1.5, 0.0, 1.5, 1.6], [1.9, 1.2, 1.4, 1.5]]],
            ['Bioplástico de fécula de mandioca', 'Pesquisador Inovador', 'Pesquisa Aplicada', [[2.7, 1.9, 2.6, 1.7], [2.8, 1.8, 2.5, 1.9]]],
            ['Mapa de calor da dengue em Guarapuava', 'Pesquisador Inovador', 'Pesquisa Aplicada', [[2.2, 1.6, 2.3, 1.6]]],
            ['Mulheres na programação', 'Mulher Inovadora', 'Transformação Digital Liderada por Mulheres', [[2.6, 2.8, 1.7, 1.8], [2.7, 2.6, 1.9, 1.7]]],
            ['Laboratório de química acessível', 'Mulher Inovadora', 'Fomento à Inovação com Protagonismo Feminino', []],
        ];

        Projeto::query()->delete();
        foreach ($projetos as $i => [$nome, $categoria, $sub, $avaliacoes]) {
            $designados = [$ids[$i % 4], $ids[($i + 1) % 4]];
            $notas = [];
            foreach ($avaliacoes as $j => $n) {
                $notas[] = [
                    'avaliador_id' => $designados[$j],
                    'c1' => $n[0], 'c2' => $n[1], 'c3' => $n[2], 'c4' => $n[3], 'bonus' => $n[4] ?? 0,
                ];
            }
            Projeto::create([
                'nome' => $nome,
                'subcategoria_id' => Subcategoria::whereHas('categoria', fn ($query) => $query->where('nome', $categoria))->where('nome', $sub)->sole()->id,
                'notas' => $notas, 'avaliador_ids' => $designados,
            ]);
        }
    }
}
