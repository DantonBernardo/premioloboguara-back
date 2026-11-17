<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Projeto;
use App\Models\User;
use Database\Seeders\ProjetoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginacaoAdminTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): void
    {
        $this->actingAs(User::factory()->create(['perfil' => User::PERFIL_ADMIN, 'ativo' => true]));
    }

    private function notas(int $quantidade): array
    {
        return array_map(fn ($id): array => ['avaliador_id' => $id, 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2], $quantidade ? range(1, $quantidade) : []);
    }

    public function test_listagem_entrega_apenas_a_pagina_e_nao_envia_o_json_das_notas(): void
    {
        $this->autenticar();
        Projeto::factory()->count(25)->create();
        $primeira = $this->getJson('/api/admin/projetos')->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)->assertJsonPath('last_page', 3)->assertJsonPath('current_page', 1)
            ->assertJsonMissingPath('data.0.notas')->json('data');
        $segunda = $this->getJson('/api/admin/projetos?page=2')->assertOk()->assertJsonCount(10, 'data')->json('data');
        $this->assertSame([], array_values(array_intersect(array_column($primeira, 'id'), array_column($segunda, 'id'))));
        $this->assertGreaterThan($segunda[0]['id'], $primeira[0]['id']);
        $this->getJson('/api/admin/projetos?page=3')->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('from', 21)->assertJsonPath('to', 25);
        $this->getJson('/api/admin/projetos?por_pagina=25')->assertJsonCount(25, 'data');
    }

    public function test_busca_e_categoria_sao_aplicadas_no_banco_antes_da_paginacao(): void
    {
        $this->autenticar();
        Projeto::factory()->count(15)->create();
        $sub = Categoria::where('codigo', 2)->sole()->subcategorias()->firstOrFail();
        $alvo = Projeto::factory()->create(['nome' => 'Radar de conhecimento', 'subcategoria_id' => $sub->id]);
        $this->getJson('/api/admin/projetos?busca=RADAR&categoria=2')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $alvo->id)->assertJsonPath('data.0.categoria_codigo', 2);
        $this->getJson('/api/admin/projetos?busca=RADAR&categoria=3')->assertJsonPath('total', 0);
        $this->getJson('/api/admin/projetos?'.http_build_query(['busca' => $alvo->codigo]))->assertJsonPath('total', 1);
        $this->getJson('/api/admin/projetos?busca=Infantil')->assertJsonPath('total', 1);
    }

    public function test_busca_trata_percentual_literal_e_nao_interpreta_sql(): void
    {
        $this->autenticar();
        Projeto::factory()->create(['nome' => 'Energia 100%']);
        Projeto::factory()->create(['nome' => 'Energia 100X']);
        $this->getJson('/api/admin/projetos?'.http_build_query(['busca' => '%']))->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/admin/projetos?'.http_build_query(['busca' => "' OR 1=1 --"]))->assertOk()->assertJsonPath('total', 0);
        foreach (['por_pagina=999', 'page=-1', 'categoria=999', 'busca[]=texto'] as $query) {
            $this->getJson('/api/admin/projetos?'.$query)->assertUnprocessable();
        }
    }

    public function test_panorama_conta_as_notas_e_nao_a_media_ou_designacao(): void
    {
        $this->autenticar();
        foreach ([0, 1, 2, 3] as $quantidade) {
            Projeto::factory()->create(['notas' => $this->notas($quantidade)]);
        }
        $this->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('andamento.aguardando', 1)->assertJsonPath('andamento.em_processo', 2)
            ->assertJsonPath('andamento.finalizados', 1)->assertJsonPath('resumo.projetos', 4)
            ->assertJsonMissingPath('projetos')->assertJsonMissingPath('avaliadores');
    }

    public function test_seed_real_inicia_sem_notas_e_preserva_avaliacoes_editadas(): void
    {
        $this->autenticar();
        $this->seed(ProjetoSeeder::class);
        $this->getJson('/api/admin/dashboard')->assertJsonPath('andamento.aguardando', 147)
            ->assertJsonPath('andamento.em_processo', 0)->assertJsonPath('andamento.finalizados', 0);
        $projeto = Projeto::orderBy('id')->skip(2)->firstOrFail();
        $codigo = $projeto->codigo;
        $avaliacoes = $this->notas(2);
        foreach ($avaliacoes as $i => &$nota) {
            $nota['avaliador_id'] = $projeto->avaliador_ids[$i];
        }
        unset($nota);
        $projeto->update(['notas' => $avaliacoes]);
        $notas = $projeto->notas;
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 147);
        $this->assertSame($codigo, $projeto->fresh()->codigo);
        $this->assertSame($notas, $projeto->fresh()->notas);
    }

    public function test_tabela_do_relatorio_e_paginada_e_busca_nao_altera_os_graficos(): void
    {
        $this->autenticar();
        Projeto::factory()->count(25)->create();
        $alvo = Projeto::factory()->create(['nome' => 'Radar premiado', 'notas' => $this->notas(3)]);
        $this->getJson('/api/admin/relatorios')->assertOk()->assertJsonCount(10, 'projetos.data')
            ->assertJsonPath('projetos.total', 26)->assertJsonPath('projetos.data.0.id', $alvo->id)
            ->assertJsonPath('projetos.data.0.posicao', 1)->assertJsonMissingPath('ranking');
        $this->getJson('/api/admin/relatorios?busca=RADAR')->assertOk()->assertJsonCount(1, 'projetos.data')
            ->assertJsonPath('projetos.total', 1)->assertJsonPath('totais.projetos', 26)
            ->assertJsonPath('totais.avaliados', 1)->assertJsonPath('totais.maior_media', 8);
        $this->getJson('/api/admin/relatorios?page=3')->assertJsonCount(6, 'projetos.data');
        $this->getJson('/api/admin/relatorios?por_pagina=10000')->assertUnprocessable();
    }

    public function test_andamento_filtra_classificacao_sem_alterar_totais(): void
    {
        $this->autenticar();
        foreach ([0, 1, 2, 3] as $quantidade) {
            Projeto::factory()->create(['notas' => $this->notas($quantidade)]);
        }
        foreach (['aguardando' => 1, 'em_processo' => 2, 'finalizados' => 1] as $andamento => $total) {
            $this->getJson('/api/admin/relatorios?andamento='.$andamento)->assertOk()
                ->assertJsonPath('projetos.total', $total)->assertJsonPath('totais.projetos', 4);
        }
        $this->getJson('/api/admin/relatorios?andamento=incorreto')->assertUnprocessable();
        $this->getJson('/api/admin/relatorios?andamento=finalizados&busca=inexistente')->assertJsonPath('projetos.total', 0);
    }

    public function test_filtros_multiplos_usam_ou_no_grupo_e_e_entre_grupos(): void
    {
        $this->autenticar();
        foreach ([1, 2, 3, 4] as $codigo) {
            $sub = Categoria::where('codigo', $codigo)->sole()->subcategorias()->firstOrFail();
            Projeto::factory()->create([
                'nome' => 'Projeto da categoria '.$codigo, 'subcategoria_id' => $sub->id,
                'avaliador_ids' => [1, 2, 3],
                'notas' => $this->notas(match ($codigo) {
                    2 => 1, 3 => 3, default => 0
                }),
            ]);
        }
        $query = http_build_query(['categorias' => [2, 3], 'situacoes' => ['em_avaliacao', 'habilitada']]);
        $this->getJson('/api/admin/relatorios?'.$query)->assertOk()->assertJsonPath('totais.projetos', 2)
            ->assertJsonPath('projetos.total', 2)->assertJsonCount(2, 'por_categoria');
        $this->getJson('/api/admin/relatorios?'.$query.'&'.http_build_query(['andamentos' => ['aguardando', 'em_processo']]))
            ->assertOk()->assertJsonPath('projetos.total', 1)->assertJsonPath('totais.projetos', 2)
            ->assertJsonPath('projetos.data.0.categoria_codigo', 2);
        $this->getJson('/api/admin/relatorios?'.http_build_query(['categorias' => [1, 2], 'andamentos' => ['aguardando', 'em_processo']]))
            ->assertOk()->assertJsonPath('projetos.total', 2);
        $this->getJson('/api/admin/relatorios?'.http_build_query(['situacoes' => ['sem_avaliacao', 'em_avaliacao']]))
            ->assertJsonPath('projetos.total', 3)->assertJsonPath('totais.projetos', 3);
        $this->getJson('/api/admin/relatorios')->assertJsonPath('projetos.total', 4);
        $this->get('/api/admin/relatorios/pdf?'.$query)->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_filtros_multiplos_rejeitam_opcoes_invalidas_repetidas_e_listas_malformadas(): void
    {
        $this->autenticar();
        foreach ([
            ['categorias' => [999]], ['categorias' => [1, 1]], ['categorias' => '1,2'],
            ['categorias' => ['chave' => 1]], ['situacoes' => ['inexistente']],
            ['situacoes' => ['habilitada', 'habilitada']], ['andamentos' => ['inexistente']],
            ['andamentos' => ['aguardando', 'aguardando']], ['andamentos' => 'aguardando'],
        ] as $filtros) {
            $this->getJson('/api/admin/relatorios?'.http_build_query($filtros))->assertUnprocessable();
        }
        $this->getJson('/api/admin/relatorios')->assertOk()->assertJsonPath('totais.projetos', 0);
    }

    public function test_rota_paginada_exige_administrador_ativo(): void
    {
        $this->getJson('/api/admin/projetos')->assertUnauthorized();
        $usuario = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->actingAs($usuario)->getJson('/api/admin/projetos')->assertForbidden();
        $usuario->update(['perfil' => User::PERFIL_ADMIN, 'ativo' => false]);
        $this->getJson('/api/admin/projetos')->assertUnauthorized();
    }
}
