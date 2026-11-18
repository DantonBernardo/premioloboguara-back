<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\Subcategoria;
use App\Models\User;
use App\Services\Avaliacao;
use App\Services\Relatorios;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvaliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function classificacao(string $categoria): int
    {
        return Subcategoria::whereHas('categoria', fn ($query) => $query->where('nome', $categoria))->orderBy('codigo')->firstOrFail()->id;
    }

    private function dados(array $alteracoes = []): array
    {
        return array_replace([
            'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2, 'bonus' => 0,
            'justificativa_geral' => 'Avaliação fundamentada nas evidências apresentadas.',
            'justificativas' => array_fill_keys(['c1', 'c2', 'c3', 'c4'], 'Evidências verificadas no Anexo I.'),
        ], $alteracoes);
    }

    private function avaliador(): User
    {
        return User::factory()->comTermosAceitos()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
    }

    private function enviar(Projeto $projeto, User $usuario, array $dados): TestResponse
    {
        return $this->actingAs($usuario)->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', $dados);
    }

    public static function categorias(): array
    {
        return [
            ['Pesquisador Inovador', [3, 2, 3, 2]],
            ['Professor Inovador', [3, 3, 2, 2]],
            ['Projeto Inovador', [3, 3, 2, 2]],
            ['Mulher Inovadora', [3, 3, 2, 2]],
        ];
    }

    #[DataProvider('categorias')]
    public function test_maximos_e_criterios_por_categoria(string $categoria, array $maximos): void
    {
        $usuario = $this->avaliador();
        $projeto = Projeto::factory()->create(['subcategoria_id' => $this->classificacao($categoria), 'avaliador_ids' => [$usuario->id]]);
        $dados = $this->dados(array_combine(['c1', 'c2', 'c3', 'c4'], $maximos));
        $this->actingAs($usuario)->getJson('/api/avaliador/projetos/'.$projeto->id)
            ->assertOk()->assertJsonPath('regras.criterios.c2.maximo', $maximos[1]);
        $this->enviar($projeto, $usuario, $dados)->assertOk()->assertJsonPath('avaliacao.total', 10);
        foreach (['c1', 'c2', 'c3', 'c4'] as $i => $codigo) {
            $this->enviar($projeto, $usuario, array_replace($dados, [$codigo => $maximos[$i] + 0.1]))
                ->assertUnprocessable()->assertJsonValidationErrors($codigo);
        }
        $this->assertEquals(10, $projeto->fresh()->nota_final);
    }

    public function test_bonus_exclusivo_e_teto_de_dez(): void
    {
        $usuario = $this->avaliador();
        foreach (array_keys(config('avaliacao.categorias')) as $categoria) {
            $projeto = Projeto::factory()->create(['subcategoria_id' => $this->classificacao($categoria), 'avaliador_ids' => [$usuario->id]]);
            $dados = $this->dados(['bonus' => 0.5]);
            $dados['justificativas']['bonus'] = 'Parceria formal comprovada.';
            $resposta = $this->enviar($projeto, $usuario, $dados);
            if ($categoria === 'Projeto Inovador') {
                $resposta->assertOk()->assertJsonPath('avaliacao.total', 8.5);
                $dados['c1'] = $dados['c2'] = 3;
                $projeto = Projeto::factory()->create(['subcategoria_id' => $this->classificacao($categoria), 'avaliador_ids' => [$usuario->id]]);
                $this->enviar($projeto, $usuario, $dados)->assertOk()->assertJsonPath('avaliacao.total', 10);
                $dados['bonus'] = 0.6;
                $this->enviar($projeto, $usuario, $dados)->assertUnprocessable()->assertJsonValidationErrors('bonus');
            } else {
                $resposta->assertUnprocessable()->assertJsonValidationErrors('bonus');
            }
        }
    }

    public function test_media_de_uma_duas_e_bloqueio_de_alteracao_apos_publicacao(): void
    {
        $usuarios = collect(range(1, 4))->map(fn () => $this->avaliador());
        $projeto = Projeto::factory()->create(['avaliador_ids' => $usuarios->pluck('id')->all()]);
        $this->assertNull($projeto->fresh()->nota_final);
        foreach ([8, 9] as $indice => $total) {
            $this->enviar($projeto, $usuarios[$indice], $this->dados(['c1' => $indice === 0 ? 2 : 3, 'c2' => $indice === 2 ? 3 : 2]))->assertOk();
            $this->assertEquals([8, 8.5][$indice], $projeto->fresh()->nota_final);
        }
        $this->enviar($projeto, $usuarios[2], $this->dados())->assertUnprocessable()->assertJsonValidationErrors('notas');
        $this->assertCount(2, $projeto->fresh()->notas);
        $this->enviar($projeto, $usuarios[0], $this->dados(['c1' => 3, 'c2' => 3]))->assertUnprocessable()->assertJsonValidationErrors('avaliacao');
        $this->assertCount(2, $projeto->fresh()->notas);
        $this->assertEquals(8.5, $projeto->fresh()->nota_final);
        $this->assertEquals(8.5, app(Relatorios::class)->gerar()['projetos'][0]['nota_final']);
    }

    public function test_zero_eliminatorio_do_pesquisador_esta_em_c1_e_c3(): void
    {
        $servico = app(Avaliacao::class);
        $permitida = $servico->calcular('Pesquisador Inovador', ['c1' => 3, 'c2' => 0, 'c3' => 3, 'c4' => 2]);
        $this->assertTrue($permitida['habilitada']);
        $eliminada = $servico->calcular('Pesquisador Inovador', ['c1' => 3, 'c2' => 2, 'c3' => 0, 'c4' => 2]);
        $this->assertFalse($eliminada['habilitada']);
        $this->assertSame(7.0, $eliminada['total']);
        $projeto = Projeto::factory()->create(['subcategoria_id' => $this->classificacao('Pesquisador Inovador'), 'notas' => [$eliminada + ['avaliador_id' => 1]]]);
        $this->assertSame('inabilitada', app(Relatorios::class)->gerar()['projetos'][0]['status']);
        $projeto->update(['notas' => [$permitida + ['avaliador_id' => 1], $permitida + ['avaliador_id' => 2]]]);
        $this->assertSame('habilitada', app(Relatorios::class)->gerar()['projetos'][0]['status']);
    }

    #[DataProvider('categorias')]
    public function test_corte_e_zero_em_cada_criterio(string $categoria, array $maximos): void
    {
        $servico = app(Avaliacao::class);
        $base = array_combine(['c1', 'c2', 'c3', 'c4'], $maximos);
        foreach (array_keys($base) as $i => $codigo) {
            $resultado = $servico->calcular($categoria, array_replace($base, [$codigo => 0]));
            $this->assertSame($maximos[$i] === 3, $resultado['zero_eliminatorio']);
            $this->assertSame($maximos[$i] !== 3, $resultado['habilitada']);
        }
        $this->assertTrue($servico->calcular($categoria, ['c1' => 2, 'c2' => 2, 'c3' => 1, 'c4' => 2])['habilitada']);
        $this->assertFalse($servico->calcular($categoria, ['c1' => 2, 'c2' => 2, 'c3' => 1, 'c4' => 1.99])['habilitada']);
    }

    public function test_rejeita_notas_incompletas_negativas_e_textos(): void
    {
        $usuario = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        foreach ([['c1' => -0.1], ['c2' => 'texto'], ['c3' => null], ['bonus' => null]] as $alteracoes) {
            $this->enviar($projeto, $usuario, $this->dados($alteracoes))->assertUnprocessable();
        }
        $dados = $this->dados();
        unset($dados['c4']);
        $this->enviar($projeto, $usuario, $dados)->assertUnprocessable()->assertJsonValidationErrors('c4');
        $this->assertSame([], $projeto->fresh()->notas);
        $this->assertNull($projeto->fresh()->nota_final);
    }

    public function test_justificativas_sao_opcionais_exceto_para_zero_eliminatorio(): void
    {
        $usuario = $this->avaliador();
        foreach ([[], null, ['c1' => null, 'bonus' => '']] as $justificativas) {
            $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
            $dados = $this->dados(['bonus' => 0.5, 'justificativas' => $justificativas]);
            $this->enviar($projeto, $usuario, $dados)->assertOk()->assertJsonPath('avaliacao.total', 8.5)
                ->assertJsonPath('avaliacao.justificativas', []);
        }
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        $dados = $this->dados(['c1' => 0]);
        unset($dados['justificativas']);
        $this->enviar($projeto, $usuario, $dados)->assertUnprocessable()->assertJsonValidationErrors('justificativas.c1');
        $dados['justificativas'] = ['c1' => 'Não apresentou evidências para o critério.'];
        $this->enviar($projeto, $usuario, $dados)->assertOk()->assertJsonPath('avaliacao.zero_eliminatorio', true)->assertJsonPath('avaliacao.habilitada', false);
        $this->enviar($projeto, $usuario, $this->dados(['justificativas' => ['c1' => str_repeat('a', 10001)]]))
            ->assertUnprocessable()->assertJsonValidationErrors('justificativas.c1');
    }

    public function test_totais_extremos_exigem_justificativa_e_cinco_nao_exige(): void
    {
        $usuario = $this->avaliador();
        foreach ([[1, 1, 1, 1], [3, 3, 2, 2]] as $valores) {
            $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
            $dados = $this->dados(array_combine(['c1', 'c2', 'c3', 'c4'], $valores));
            unset($dados['justificativa_geral']);
            $this->enviar($projeto, $usuario, $dados)->assertUnprocessable()->assertJsonValidationErrors('justificativa_geral');
            $dados['justificativa_geral'] = '   ';
            $this->enviar($projeto, $usuario, $dados)->assertUnprocessable()->assertJsonValidationErrors('justificativa_geral');
            $dados['justificativa_geral'] = 'Evidências justificam a nota atribuída.';
            $this->enviar($projeto, $usuario, $dados)->assertOk()->assertJsonPath('avaliacao.justificativa_geral', $dados['justificativa_geral']);
            $this->actingAs($usuario)->getJson('/api/avaliador/projetos/'.$projeto->id)->assertJsonPath('avaliacao.justificativa_geral', $dados['justificativa_geral']);
        }
        $dados = $this->dados(['c1' => 2, 'c2' => 1, 'c3' => 1, 'c4' => 1]);
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        unset($dados['justificativa_geral'], $dados['justificativas']);
        $this->enviar($projeto, $usuario, $dados)->assertOk()->assertJsonPath('avaliacao.total', 5);
    }

    public function test_nomes_descricoes_e_pesos_sao_identicos_a_aba_criterios_e_rubricas(): void
    {
        $fonte = json_decode(file_get_contents(base_path('tests/Fixtures/criterios-avaliacao.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(20, $fonte);
        foreach ($fonte as $linha) {
            $regras = app(Avaliacao::class)->criterios($linha['categoria']);
            if ($linha['codigo'] === 'bonus') {
                $this->assertSame($linha['nome'], $regras['bonus_nome']);
                $this->assertSame($linha['descricao'], $regras['bonus_descricao']);
                $this->assertEquals($linha['maximo'], $regras['bonus_maximo']);
            } else {
                $criterio = $regras['criterios'][$linha['codigo']];
                $this->assertSame($linha['nome'], $criterio['nome']);
                $this->assertSame($linha['descricao'], $criterio['descricao']);
                $this->assertEquals($linha['maximo'], $criterio['maximo']);
            }
        }
    }

    public function test_identidade_categoria_e_resultados_nao_podem_ser_forjados(): void
    {
        $usuario = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        foreach (['categoria' => 'Professor Inovador', 'avaliador_id' => 999, 'nota_final' => 10, 'total' => 10, 'habilitada' => true, 'notas' => [10]] as $campo => $valor) {
            $this->enviar($projeto, $usuario, $this->dados([$campo => $valor]))->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
    }

    public function test_exige_autenticacao_perfil_ativo_e_designacao(): void
    {
        $projeto = Projeto::factory()->create();
        $url = '/api/avaliador/projetos/'.$projeto->id;
        $this->getJson($url)->assertUnauthorized();
        $this->putJson($url.'/avaliacao', $this->dados())->assertUnauthorized();
        $usuario = $this->avaliador();
        $this->enviar($projeto, $usuario, $this->dados())->assertForbidden();
        $this->actingAs($usuario)->getJson($url)->assertForbidden();
        $usuario->update(['ativo' => false]);
        $this->enviar($projeto, $usuario, $this->dados())->assertUnauthorized();
        $usuario->update(['ativo' => true, 'perfil' => User::PERFIL_ADMIN]);
        $this->enviar($projeto, $usuario, $this->dados())->assertForbidden();
    }

    public function test_modelo_recalcula_e_nao_confia_em_totais_salvos(): void
    {
        $projeto = Projeto::factory()->create(['notas' => [$this->dados(['avaliador_id' => 1, 'total' => 1000, 'habilitada' => false])]]);
        $this->assertEquals(8, $projeto->fresh()->nota_final);
        $this->assertEquals(8, $projeto->fresh()->notas[0]['total']);
        $projeto->nota_final = 100;
        $projeto->save();
        $this->assertEquals(8, $projeto->fresh()->nota_final);
        $projeto->update(['notas' => []]);
        $this->assertNull($projeto->fresh()->nota_final);
    }

    public function test_modelo_rejeita_terceira_avaliacoes(): void
    {
        $this->expectException(ValidationException::class);
        Projeto::factory()->create(['notas' => array_map(fn ($id) => $this->dados(['avaliador_id' => $id]), range(1, 3))]);
    }

    public function test_modelo_rejeita_avaliador_duplicado(): void
    {
        $this->expectException(ValidationException::class);
        Projeto::factory()->create(['notas' => [$this->dados(['avaliador_id' => 1]), $this->dados(['avaliador_id' => 1])]]);
    }

    public function test_banco_rejeita_terceira_avaliacoes_mesmo_sem_eloquent(): void
    {
        $projeto = Projeto::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('projetos')->where('id', $projeto->id)->update(['notas' => json_encode([[], [], []])]);
    }

    public function test_migracao_reduz_tres_notas_para_duas_e_recalcula_media(): void
    {
        $migracao = require database_path('migrations/2026_11_17_223806_limit_project_evaluations_to_two.php');
        $migracao->down();
        config(['avaliacao.limite_avaliacoes' => 3]);
        $projeto = Projeto::factory()->create(['avaliador_ids' => [1, 2, 3], 'notas' => [
            $this->dados(['avaliador_id' => 1]),
            $this->dados(['avaliador_id' => 2, 'c1' => 3]),
            $this->dados(['avaliador_id' => 3, 'c1' => 3, 'c2' => 3]),
        ]]);
        config(['avaliacao.limite_avaliacoes' => 2]);
        $migracao->up();
        $this->assertCount(2, $projeto->fresh()->notas);
        $this->assertSame([1, 2], $projeto->fresh()->avaliador_ids);
        $this->assertSame(8.5, $projeto->fresh()->nota_final);
    }

    public function test_categoria_desconhecida_nao_usa_regras_de_outra_categoria(): void
    {
        $this->expectException(ValidationException::class);
        app(Avaliacao::class)->calcular('Categoria inexistente', $this->dados());
    }

    public function test_migracao_preserva_avaliacoes_e_preenche_media_existente(): void
    {
        $migracao = require database_path('migrations/2026_11_14_174531_add_nota_final_to_projetos_table.php');
        $normalizacao = require database_path('migrations/2026_11_14_184207_normalize_categorias_and_add_codigo_to_projetos.php');
        $normalizacao->down();
        $migracao->down();
        $id = DB::table('projetos')->insertGetId([
            'nome' => 'Projeto existente', 'categoria' => 'Projeto Inovador', 'subcategoria' => 'Empreendedorismo Inovador',
            'notas' => json_encode([
                $this->dados(['avaliador_id' => 1]),
                $this->dados(['avaliador_id' => 2, 'c1' => 3]),
            ]),
            'avaliador_ids' => '[1,2]',
        ]);
        $migracao->up();
        $normalizacao->up();
        $projeto = Projeto::findOrFail($id);
        $this->assertEquals(8.5, $projeto->nota_final);
        $this->assertCount(2, $projeto->notas);
        $this->assertSame('Evidências verificadas no Anexo I.', $projeto->notas[0]['justificativas']['c1']);
    }

    public function test_migracao_nao_trunca_avaliacoes_existentes_invalidas(): void
    {
        $migracao = require database_path('migrations/2026_11_14_174531_add_nota_final_to_projetos_table.php');
        $normalizacao = require database_path('migrations/2026_11_14_184207_normalize_categorias_and_add_codigo_to_projetos.php');
        $normalizacao->down();
        $migracao->down();
        $id = DB::table('projetos')->insertGetId([
            'nome' => 'Projeto inválido', 'categoria' => 'Projeto Inovador', 'subcategoria' => 'Empreendedorismo Inovador',
            'notas' => json_encode(array_map(fn ($id) => $this->dados(['avaliador_id' => $id]), range(1, 4))),
            'avaliador_ids' => '[]',
        ]);
        try {
            $migracao->up();
            $this->fail('A migração deveria rejeitar quatro avaliações existentes.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('projeto '.$id, $exception->getMessage());
            $this->assertCount(4, json_decode(DB::table('projetos')->where('id', $id)->value('notas'), true));
        } finally {
            DB::table('projetos')->where('id', $id)->delete();
            $migracao->up();
            $normalizacao->up();
        }
    }

    public function test_nao_expoe_notas_de_outros_avaliadores(): void
    {
        $usuario = $this->avaliador();
        $outro = $this->avaliador();
        $projeto = Projeto::factory()->create([
            'avaliador_ids' => [$usuario->id, $outro->id],
            'notas' => [$this->dados(['avaliador_id' => $outro->id])],
        ]);
        $this->actingAs($usuario)->getJson('/api/avaliador/projetos/'.$projeto->id)
            ->assertOk()->assertJsonPath('avaliacao', null)->assertJsonMissingPath('projeto.notas');
    }
}
