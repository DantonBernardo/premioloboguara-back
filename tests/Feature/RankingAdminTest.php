<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['perfil' => User::PERFIL_ADMIN, 'ativo' => true]);
    }

    private function nota(int $id, float $c1 = 2): array
    {
        return ['avaliador_id' => $id, 'c1' => $c1, 'c2' => 2, 'c3' => 2, 'c4' => 2];
    }

    public function test_ranking_inclui_todos_com_empates_e_projetos_sem_nota_no_final(): void
    {
        $semNota = Projeto::factory()->create();
        $segundo = Projeto::factory()->create(['notas' => [$this->nota(1)]]);
        $primeiro = Projeto::factory()->create(['notas' => [$this->nota(1, 3)]]);
        $empatado = Projeto::factory()->create(['notas' => [$this->nota(2, 3)], 'created_at' => now()->subDay()]);
        $resposta = $this->actingAs($this->admin())->getJson('/api/admin/ranking')->assertOk()
            ->assertJsonPath('resumo.total', 4)->assertJsonPath('resumo.com_nota', 3)
            ->assertJsonPath('resumo.maior_media', 9)->assertJsonCount(4, 'destaques')
            ->assertJsonPath('projetos.data.0.id', $empatado->id)->assertJsonPath('projetos.data.0.posicao', 1)
            ->assertJsonPath('projetos.data.1.id', $primeiro->id)->assertJsonPath('projetos.data.1.posicao', 2)
            ->assertJsonPath('projetos.data.2.id', $segundo->id)->assertJsonPath('projetos.data.2.posicao', 3)
            ->assertJsonPath('projetos.data.3.id', $semNota->id)->assertJsonPath('projetos.data.3.posicao', null)
            ->assertJsonMissingPath('projetos.data.0.notas');
        $this->assertStringNotContainsString('justificativas', $resposta->getContent());
    }

    public function test_busca_e_filtros_sao_no_backend_preservando_as_posicoes(): void
    {
        $this->actingAs($this->admin());
        Projeto::factory()->create(['notas' => [$this->nota(1, 3)]]);
        $alvo = Projeto::factory()->create(['nome' => 'Radar de ideias', 'notas' => [$this->nota(1)]]);
        Projeto::factory()->count(20)->create();
        $this->getJson('/api/admin/ranking')->assertJsonCount(15, 'projetos.data')->assertJsonPath('projetos.total', 22);
        $this->getJson('/api/admin/ranking?page=2')->assertJsonCount(7, 'projetos.data');
        $this->getJson('/api/admin/ranking?busca=RADAR')->assertJsonPath('projetos.total', 1)
            ->assertJsonPath('projetos.data.0.id', $alvo->id)->assertJsonPath('projetos.data.0.posicao', 2);
        $this->getJson('/api/admin/ranking?andamento=aguardando')->assertJsonPath('projetos.total', 20)->assertJsonCount(4, 'destaques');
        $this->getJson('/api/admin/ranking?andamento=em_processo')->assertJsonPath('projetos.total', 2);
        $this->getJson('/api/admin/ranking?categoria=1')->assertJsonPath('projetos.total', 0);
        foreach (['categoria=999', 'page=0', 'andamento=incorreto', 'busca[]=a'] as $query) {
            $this->getJson('/api/admin/ranking?'.$query)->assertUnprocessable();
        }
    }

    public function test_ranking_pode_ser_carregado_em_lotes_de_dez_sem_repetir_projetos(): void
    {
        $this->actingAs($this->admin());
        Projeto::factory()->count(23)->create(['notas' => [$this->nota(1)]]);
        $primeira = $this->getJson('/api/admin/ranking?categoria=3&por_pagina=10')->assertOk()
            ->assertJsonCount(10, 'projetos.data')->assertJsonPath('projetos.last_page', 3)->json('projetos.data');
        $segunda = $this->getJson('/api/admin/ranking?categoria=3&por_pagina=10&page=2')->assertOk()->assertJsonCount(10, 'projetos.data')->json('projetos.data');
        $this->assertSame([], array_values(array_intersect(array_column($primeira, 'id'), array_column($segunda, 'id'))));
        $this->getJson('/api/admin/ranking?categoria=3&por_pagina=10&page=3')->assertJsonCount(3, 'projetos.data');
        $this->getJson('/api/admin/ranking?por_pagina=999')->assertUnprocessable();
    }

    public function test_nova_avaliacao_atualiza_o_ranking_e_pendentes_nao_sao_habilitados(): void
    {
        $admin = $this->admin();
        $avaliador = User::factory()->comTermosAceitos()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $lider = Projeto::factory()->create(['notas' => [$this->nota(1)]]);
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$avaliador->id]]);
        $this->actingAs($avaliador)->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', ['c1' => 3, 'c2' => 2, 'c3' => 2, 'c4' => 2])->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/ranking')->assertOk()->assertJsonPath('projetos.data.0.id', $projeto->id)
            ->assertJsonPath('projetos.data.0.nota_final', 9)->assertJsonPath('projetos.data.0.status', 'em_avaliacao')
            ->assertJsonPath('projetos.data.1.id', $lider->id);
        $projeto->update(['notas' => [$this->nota($avaliador->id, 3), $this->nota($avaliador->id + 100, 3)]]);
        $this->getJson('/api/admin/ranking')->assertJsonPath('projetos.data.0.status', 'habilitada')->assertJsonPath('resumo.finalizados', 1);
    }

    public function test_cards_incluem_todas_as_categorias_mesmo_sem_notas_e_contam_empates(): void
    {
        $this->actingAs($this->admin());
        Projeto::factory()->count(3)->create(['notas' => [$this->nota(1, 3)]]);
        $this->getJson('/api/admin/ranking')->assertOk()->assertJsonCount(4, 'destaques')
            ->assertJsonPath('destaques.0.categoria_codigo', 1)->assertJsonPath('destaques.0.projeto', null)
            ->assertJsonPath('destaques.1.categoria_codigo', 2)->assertJsonPath('destaques.2.categoria_codigo', 3)
            ->assertJsonPath('destaques.2.projeto.nota_final', 9)->assertJsonPath('destaques.2.empatados', 3)
            ->assertJsonPath('destaques.3.categoria_codigo', 4);
        $this->getJson('/api/admin/ranking?categoria=3')->assertJsonCount(1, 'destaques')
            ->assertJsonPath('destaques.0.categoria_codigo', 3);
        $this->getJson('/api/admin/ranking?andamento=aguardando')->assertJsonCount(4, 'destaques')
            ->assertJsonPath('destaques.2.projeto', null);
    }

    public function test_ranking_exige_administrador_ativo_e_aceita_banco_vazio(): void
    {
        $this->getJson('/api/admin/ranking')->assertUnauthorized();
        $avaliador = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->actingAs($avaliador)->getJson('/api/admin/ranking')->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/admin/ranking')->assertOk()->assertJsonPath('resumo.total', 0)->assertJsonCount(4, 'destaques');
        $admin->update(['ativo' => false]);
        $this->getJson('/api/admin/ranking')->assertUnauthorized();
    }
}
