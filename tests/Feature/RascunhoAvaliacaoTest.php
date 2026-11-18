<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RascunhoAvaliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function avaliador(): User
    {
        return User::factory()->comTermosAceitos()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
    }

    public function test_partial_draft_persists_without_publishing_and_is_private_to_each_evaluator(): void
    {
        $um = $this->avaliador();
        $dois = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$um->id, $dois->id]]);
        $url = '/api/avaliador/projetos/'.$projeto->id;
        $dados = ['notas' => ['c1' => 0, 'c2' => null], 'etapa' => 0, 'justificativas' => ['c1' => 'Ainda analisando.'], 'justificativa_geral' => 'Análise em andamento.'];
        $this->actingAs($um)->putJson($url.'/rascunho', $dados)->assertOk()->assertJsonPath('rascunho.notas.c1', 0)->assertJsonStructure(['rascunho' => ['salvo_em']]);
        $this->getJson($url)->assertOk()->assertJsonPath('avaliacao', null)->assertJsonPath('rascunho.notas.c2', null)->assertJsonPath('rascunho.justificativa_geral', 'Análise em andamento.');
        $this->getJson('/api/avaliador/projetos?status=pendentes')->assertJsonPath('resumo.avaliados', 0)->assertJsonPath('projetos.data.0.rascunho_em', fn ($data): bool => $data !== null);
        $this->actingAs($dois)->getJson($url)->assertOk()->assertJsonPath('rascunho', null);
        $this->getJson('/api/avaliador/projetos')->assertJsonPath('projetos.data.0.rascunho_em', null);
        $this->putJson($url.'/rascunho', ['notas' => ['c2' => 2], 'etapa' => 1])->assertOk();
        $this->actingAs($um)->putJson($url.'/rascunho', ['notas' => ['c1' => 2], 'etapa' => 2])->assertOk();
        $this->assertDatabaseCount('avaliacao_rascunhos', 2);
        $this->assertSame([], $projeto->fresh()->notas);
        $this->assertNull($projeto->fresh()->nota_final);
        $admin = User::factory()->create(['perfil' => User::PERFIL_ADMIN, 'ativo' => true]);
        $this->actingAs($admin)->getJson('/api/admin/relatorios')->assertOk()->assertJsonPath('totais.avaliacoes_recebidas', 0)->assertJsonPath('totais.media_geral', null);
    }

    public function test_draft_requires_assignment_active_evaluator_and_accepted_terms(): void
    {
        $dono = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$dono->id]]);
        $url = '/api/avaliador/projetos/'.$projeto->id.'/rascunho';
        $dados = ['notas' => ['c1' => 1], 'etapa' => 0];
        $this->putJson($url, $dados)->assertUnauthorized();
        $this->actingAs($this->avaliador())->putJson($url, $dados)->assertForbidden();
        $dono->forceFill(['termos_aceitos_em' => null])->save();
        $this->actingAs($dono)->putJson($url, $dados)->assertForbidden();
        $demo = User::factory()->create(['perfil' => User::PERFIL_DEMO, 'ativo' => true]);
        $this->actingAs($demo)->putJson($url, $dados)->assertForbidden();
        $this->assertDatabaseCount('avaliacao_rascunhos', 0);
    }

    public function test_validates_ranges_but_allows_unfinished_justifications_in_drafts(): void
    {
        $usuario = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        $url = '/api/avaliador/projetos/'.$projeto->id.'/rascunho';
        $this->actingAs($usuario)->putJson($url, ['notas' => ['c1' => 4], 'etapa' => 0])->assertUnprocessable();
        $this->putJson($url, ['notas' => ['c2' => -1], 'etapa' => 0])->assertUnprocessable();
        $this->putJson($url, ['notas' => ['bonus' => 1], 'etapa' => 0])->assertUnprocessable();
        $this->putJson($url, ['notas' => ['c1' => 1], 'etapa' => 99])->assertUnprocessable();
        $this->putJson($url, ['notas' => ['c1' => 0], 'etapa' => 0])->assertOk();
        $this->assertDatabaseCount('avaliacao_rascunhos', 1);
    }

    public function test_publish_keeps_validation_clears_only_own_draft_and_prevents_later_drafts(): void
    {
        $um = $this->avaliador();
        $dois = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$um->id, $dois->id]]);
        $url = '/api/avaliador/projetos/'.$projeto->id;
        $this->actingAs($dois)->putJson($url.'/rascunho', ['notas' => ['c1' => 1], 'etapa' => 0])->assertOk();
        $this->actingAs($um)->putJson($url.'/rascunho', ['notas' => ['c1' => 0], 'etapa' => 0])->assertOk();
        $nota = ['c1' => 0, 'c2' => 3, 'c3' => 2, 'c4' => 2];
        $this->putJson($url.'/avaliacao', $nota)->assertUnprocessable();
        $this->assertDatabaseCount('avaliacao_rascunhos', 2);
        $this->putJson($url.'/avaliacao', $nota + ['justificativas' => ['c1' => 'Sem evidências.']])->assertOk()->assertJsonPath('avaliacao.total', 7)->assertJsonPath('avaliacao.habilitada', false);
        $this->assertDatabaseMissing('avaliacao_rascunhos', ['avaliador_id' => $um->id]);
        $this->assertDatabaseHas('avaliacao_rascunhos', ['avaliador_id' => $dois->id]);
        $this->putJson($url.'/rascunho', ['notas' => ['c1' => 2], 'etapa' => 0])->assertConflict();
        $this->assertSame(7.0, $projeto->fresh()->nota_final);
        $this->assertSame(1, DB::table('avaliacao_rascunhos')->count());
    }
}
