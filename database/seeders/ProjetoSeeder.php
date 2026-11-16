<?php

namespace Database\Seeders;

use App\Models\Projeto;
use App\Models\Subcategoria;
use App\Models\User;
use App\Services\Avaliacao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjetoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('ProjetoSeeder permitido apenas em ambiente local ou de testes.');
        }

        $this->call(CategoriaSeeder::class);
        if (User::where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->count() < 12
            || ! User::where('name', 'Andrea Alves Vieira')->where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->exists()) {
            $this->call(UsersSeeder::class);
        }
        $avaliadores = User::where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->orderBy('id')->pluck('id')->all();
        if (count($avaliadores) < 12) {
            throw new \RuntimeException('São necessários pelo menos doze avaliadores ativos para formar 200 trios diferentes.');
        }

        $andreaId = User::where('name', 'Andrea Alves Vieira')->where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->sole()->id;

        $projetos = [
            ['Biofiltro de resíduos agrícolas para tratamento de água', 'Pesquisador Inovador', 'Pesquisa Aplicada'],
            ['Modelagem climática para a agricultura de Guarapuava', 'Pesquisador Inovador', 'Pesquisa Teórica'],
            ['Detecção de pragas com visão computacional', 'Pesquisador Inovador', 'TCC e Iniciação Científica'],
            ['Embalagem biodegradável de fibras de erva-mate', 'Pesquisador Inovador', 'Pesquisa Aplicada'],
            ['Otimização matemática das rotas de transporte público', 'Pesquisador Inovador', 'Pesquisa Teórica'],
            ['Análise da qualidade do ar em escolas municipais', 'Pesquisador Inovador', 'TCC e Iniciação Científica'],
            ['Reaproveitamento de cinzas na produção de concreto', 'Pesquisador Inovador', 'Pesquisa Aplicada'],
            ['Mapeamento da biodiversidade dos parques urbanos', 'Pesquisador Inovador', 'TCC e Iniciação Científica'],
            ['Contação de histórias com realidade aumentada', 'Professor Inovador', 'Educação Infantil'],
            ['Matemática com jogos de tabuleiro acessíveis', 'Professor Inovador', 'Ensino Fundamental I'],
            ['Laboratório maker de energia renovável', 'Professor Inovador', 'Ensino Fundamental II'],
            ['Ciência cidadã no monitoramento de rios', 'Professor Inovador', 'Ensino Médio'],
            ['Simulador virtual para formação em enfermagem', 'Professor Inovador', 'Ensino Superior'],
            ['Aprendizagem baseada em desafios da indústria local', 'Professor Inovador', 'Pós-Graduação (Lato Sensu / Stricto Sensu)'],
            ['Alfabetização multissensorial com materiais reutilizados', 'Professor Inovador', 'Ensino Fundamental I'],
            ['Programação criativa para estudantes do campo', 'Professor Inovador', 'Ensino Fundamental II'],
            ['Plataforma de comercialização da agricultura familiar', 'Projeto Inovador', 'Empreendedorismo Inovador'],
            ['Rede comunitária de combate ao desperdício alimentar', 'Projeto Inovador', 'Empreendedorismo Social'],
            ['Atendimento municipal com triagem digital acessível', 'Projeto Inovador', 'Transformação Digital'],
            ['Hub de prototipagem para pequenos negócios', 'Projeto Inovador', 'Fomento à Inovação'],
            ['Sensoriamento inteligente da iluminação pública', 'Projeto Inovador', 'Transformação Digital'],
            ['Sistema de rastreabilidade de produtos de erva-mate', 'Projeto Inovador', 'Empreendedorismo Inovador'],
            ['Banco comunitário de equipamentos assistivos', 'Projeto Inovador', 'Empreendedorismo Social'],
            ['Cooperativa feminina de cosméticos sustentáveis', 'Mulher Inovadora', 'Empreendedorismo Inovador Feminino'],
            ['Plataforma de mentoria para mulheres em tecnologia', 'Mulher Inovadora', 'Transformação Digital Liderada por Mulheres'],
            ['Rede de pesquisadoras e empreendedoras de Guarapuava', 'Mulher Inovadora', 'Fomento à Inovação com Protagonismo Feminino'],
            ['Gestão digital para produtoras rurais', 'Mulher Inovadora', 'Transformação Digital Liderada por Mulheres'],
            ['Negócio de reciclagem têxtil com liderança feminina', 'Mulher Inovadora', 'Empreendedorismo Inovador Feminino'],
            ['Incubadora de soluções desenvolvidas por mulheres', 'Mulher Inovadora', 'Fomento à Inovação com Protagonismo Feminino'],
            ['Aplicativo de autonomia financeira para mulheres', 'Mulher Inovadora', 'Transformação Digital Liderada por Mulheres'],
        ];

        $bases = $projetos;
        foreach (['Piloto urbano', 'Aplicação no campo', 'Rede de bairros', 'Expansão regional'] as $variante) {
            foreach ($bases as [$nome, $categoria, $subcategoria]) {
                $projetos[] = [$nome.' - '.$variante, $categoria, $subcategoria];
            }
        }

        for ($i = 0; $i < 50; $i++) {
            [$nome, $categoria, $subcategoria] = $bases[$i % count($bases)];
            $variante = $i < 30 ? 'Conexão comunitária' : 'Tecnologia inclusiva';
            $projetos[] = [$nome.' - '.$variante, $categoria, $subcategoria];
        }

        $resultado = DB::transaction(function () use ($projetos, $avaliadores, $andreaId): array {
            $criados = 0;
            $preservados = 0;
            $pendentes = [];
            $cargas = array_fill_keys($avaliadores, 0);
            $reservados = [];
            foreach ($projetos as $indice => [$nome, $categoria, $subcategoria]) {
                $subcategoriaId = Subcategoria::whereHas('categoria', fn ($query) => $query->where('nome', $categoria))
                    ->where('nome', $subcategoria)->sole()->id;
                $projeto = Projeto::firstOrCreate(
                    ['nome' => $nome, 'subcategoria_id' => $subcategoriaId],
                    ['notas' => [], 'avaliador_ids' => []],
                );
                $criados += (int) $projeto->wasRecentlyCreated;
                $trioAnterior = $projeto->avaliador_ids ?? [];
                $notas = $projeto->notas ?? [];
                $exemploOriginal = count($trioAnterior) === 3
                    && $notas == app(Avaliacao::class)->normalizar($categoria, $this->notasExemplo($indice, $categoria, $trioAnterior));
                if ($notas !== [] && ! $exemploOriginal) {
                    $preservados++;
                    $reservados[$this->chaveTrio($trioAnterior)] = true;
                    foreach ($trioAnterior as $id) {
                        if (isset($cargas[$id])) {
                            $cargas[$id]++;
                        }
                    }

                    continue;
                }
                $pendentes[] = [$projeto, $indice, $categoria];
            }

            $cotasAndrea = [];
            $porCategoriaAndrea = [];
            foreach ([true, false] as $somenteNovos) {
                foreach ($pendentes as [$projeto, $indice, $categoria]) {
                    if (($somenteNovos && $indice < 150) || $indice % 3 !== 0
                        || isset($cotasAndrea[$projeto->id]) || ($porCategoriaAndrea[$categoria] ?? 0) >= 2) {
                        continue;
                    }
                    $cotasAndrea[$projeto->id] = true;
                    $porCategoriaAndrea[$categoria] = ($porCategoriaAndrea[$categoria] ?? 0) + 1;
                }
            }
            if (count($cotasAndrea) < 8) {
                throw new \RuntimeException('Não há exemplos pendentes suficientes para designar dois projetos por categoria à Andrea sem alterar avaliações manuais.');
            }
            usort($pendentes, fn (array $a, array $b): int => [isset($cotasAndrea[$b[0]->id]), $a[1]] <=> [isset($cotasAndrea[$a[0]->id]), $b[1]]);

            $trios = [];
            $quantidade = count($avaliadores);
            for ($a = 0; $a < $quantidade - 2; $a++) {
                for ($b = $a + 1; $b < $quantidade - 1; $b++) {
                    for ($c = $b + 1; $c < $quantidade; $c++) {
                        $trio = [$avaliadores[$a], $avaliadores[$b], $avaliadores[$c]];
                        if (! isset($reservados[$this->chaveTrio($trio)])) {
                            $trios[] = $trio;
                        }
                    }
                }
            }
            if (count($trios) < count($pendentes)) {
                throw new \RuntimeException('Não há trios únicos suficientes para distribuir os projetos da seed.');
            }
            foreach ($pendentes as [$projeto, $indice, $categoria]) {
                $melhor = null;
                $menorPeso = null;
                foreach ($trios as $chave => $trio) {
                    if (isset($cotasAndrea[$projeto->id]) && ! in_array($andreaId, $trio, true)) {
                        continue;
                    }
                    $carga = array_map(fn (int $id): int => $cargas[$id], $trio);
                    $peso = [array_sum($carga), max($carga)];
                    if ($menorPeso === null || $peso < $menorPeso) {
                        $menorPeso = $peso;
                        $melhor = $chave;
                    }
                }
                $trio = $trios[$melhor];
                unset($trios[$melhor]);
                foreach ($trio as $id) {
                    $cargas[$id]++;
                }
                $projeto->update(['notas' => $this->notasExemplo($indice, $categoria, $trio), 'avaliador_ids' => $trio]);
            }

            return ['criados' => $criados, 'preservados' => $preservados];
        });

        $this->command?->info($resultado['criados'].' projetos criados; '.$resultado['preservados'].' projetos com avaliações manuais preservados.');
        $this->command?->info('200 exemplos: 56 Pesquisador, 56 Professor, 46 Projeto e 42 Mulher Inovadora.');
        $this->command?->info('Andrea: pelo menos dois projetos sem avaliação por categoria.');
        $this->command?->info('Trios distintos e carga balanceada entre os avaliadores ativos.');
        $this->command?->info('Distribuição inicial: 67 aguardando avaliação, 67 em processo e 66 finalizados.');
    }

    private function chaveTrio(array $ids): string
    {
        sort($ids, SORT_NUMERIC);

        return implode('-', $ids);
    }

    private function notasExemplo(int $indice, string $categoria, array $avaliadores): array
    {
        $quantidade = match ($indice % 3) {
            0 => 0,
            1 => (intdiv($indice, 3) % 2) + 1,
            default => 3,
        };
        $notas = [];
        $regras = app(Avaliacao::class)->criterios($categoria);
        for ($j = 0; $j < $quantidade; $j++) {
            $nota = ['avaliador_id' => $avaliadores[$j], 'bonus' => $regras['bonus_maximo'] > 0 && $indice % 2 === 0 ? 0.5 : 0];
            foreach ($regras['criterios'] as $codigo => $criterio) {
                $nota[$codigo] = round($criterio['maximo'] * min(1, [0.42, 0.62, 0.74, 0.86, 0.96][$indice % 5] + ($j - 1) * 0.03), 2);
                $nota['justificativas'][$codigo] = 'Avaliação de exemplo para demonstração do sistema.';
            }
            if ($nota['bonus'] > 0) {
                $nota['justificativas']['bonus'] = 'Parceria de exemplo para demonstração do bônus.';
            }
            $notas[] = $nota;
        }

        return $notas;
    }
}
