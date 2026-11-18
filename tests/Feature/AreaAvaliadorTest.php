<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaAvaliadorTest extends TestCase
{
    use RefreshDatabase;

    private function avaliador(): User
    {
        return User::factory()->comTermosAceitos()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
    }

    private function nota(int $id): array
    {
        return ['avaliador_id' => $id, 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2, 'justificativas' => ['c1' => 'Evidência um', 'c2' => 'Evidência dois', 'c3' => 'Evidência três', 'c4' => 'Evidência quatro']];
    }

    public function test_lista_e_paginada_restrita_as_designacoes_e_sem_notas_de_terceiros(): void
    {
        $usuario = $this->avaliador();
        $outro = $this->avaliador();
        Projeto::factory()->count(12)->create(['avaliador_ids' => [$usuario->id]]);
        $avaliado = Projeto::factory()->create(['avaliador_ids' => [$usuario->id, $outro->id], 'notas' => [$this->nota($usuario->id), $this->nota($outro->id)]]);
        Projeto::factory()->create(['avaliador_ids' => [$outro->id]]);
        $resposta = $this->actingAs($usuario)->getJson('/api/avaliador/projetos')->assertOk()
            ->assertJsonPath('resumo.designados', 13)->assertJsonPath('resumo.avaliados', 1)->assertJsonPath('resumo.pendentes', 12)
            ->assertJsonPath('projetos.total', 13)->assertJsonCount(10, 'projetos.data')
            ->assertJsonPath('projetos.data.0.id', $avaliado->id)->assertJsonPath('projetos.data.0.minha_nota', 8)
            ->assertJsonMissingPath('projetos.data.0.notas')->assertJsonMissingPath('projetos.data.0.nota_final');
        $this->assertStringNotContainsString('justificativas', $resposta->getContent());
        $this->getJson('/api/avaliador/projetos?page=2')->assertJsonCount(3, 'projetos.data');
        $this->getJson('/api/avaliador/projetos?status=pendentes')->assertJsonPath('projetos.total', 12);
        $this->getJson('/api/avaliador/projetos?status=avaliados')->assertJsonPath('projetos.total', 1);
    }

    public function test_filtros_buscam_no_servidor_e_consideram_apenas_a_propria_avaliacao(): void
    {
        $usuario = $this->avaliador();
        $outro = $this->avaliador();
        $sub = Categoria::where('codigo', 2)->sole()->subcategorias()->firstOrFail();
        $alvo = Projeto::factory()->create(['nome' => 'Radar 100%', 'subcategoria_id' => $sub->id, 'avaliador_ids' => [(string) $usuario->id], 'notas' => [$this->nota($outro->id)]]);
        $this->actingAs($usuario)->getJson('/api/avaliador/projetos?busca=RADAR&categoria=2&status=pendentes')->assertOk()->assertJsonPath('projetos.total', 1)->assertJsonPath('projetos.data.0.avaliada', false);
        $this->getJson('/api/avaliador/projetos?busca=RADAR&status=avaliados')->assertJsonPath('projetos.total', 0);
        $this->getJson('/api/avaliador/projetos?'.http_build_query(['busca' => '%']))->assertJsonPath('projetos.total', 1);
        $this->getJson('/api/avaliador/projetos?'.http_build_query(['busca' => $alvo->codigo]))->assertJsonPath('projetos.total', 1);
        $this->getJson('/api/avaliador/projetos?categoria=1')->assertJsonPath('projetos.total', 0);
        foreach (['status=inexistente', 'page=0', 'categoria=999', 'busca[]=x'] as $query) {
            $this->getJson('/api/avaliador/projetos?'.$query)->assertUnprocessable();
        }
    }

    public function test_envio_publica_a_propria_nota_e_reenvio_nao_altera_avaliacoes_nem_media(): void
    {
        $usuario = $this->avaliador();
        $outro = $this->avaliador();
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id, $outro->id], 'notas' => [$this->nota($outro->id)]]);
        $this->actingAs($usuario)->getJson('/api/avaliador/projetos/'.$projeto->id)->assertOk()->assertJsonPath('avaliacao', null)->assertJsonMissingPath('projeto.notas')->assertJsonMissingPath('projeto.nota_final');
        $dados = $this->nota($usuario->id);
        unset($dados['avaliador_id']);
        $this->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', $dados)->assertOk()->assertJsonPath('avaliacao.total', 8);
        $estado = $projeto->fresh()->getAttributes();
        $dados['c1'] = 3;
        $dados['justificativa_geral'] = 'Tentativa de alterar uma avaliação publicada.';
        $this->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', $dados)->assertUnprocessable()->assertJsonValidationErrors('avaliacao');
        $this->assertSame($estado, $projeto->fresh()->getAttributes());
        $this->assertCount(2, $projeto->fresh()->notas);
        $this->assertSame(8.0, $projeto->fresh()->nota_final);
        $this->getJson('/api/avaliador/projetos?status=avaliados')->assertJsonPath('projetos.data.0.minha_nota', 8);
    }

    public function test_endpoints_exigem_avaliador_ativo_e_designado(): void
    {
        $this->getJson('/api/avaliador/projetos')->assertUnauthorized();
        $usuario = $this->avaliador();
        $projeto = Projeto::factory()->create();
        $this->actingAs($usuario)->getJson('/api/avaliador/projetos/'.$projeto->id)->assertForbidden();
        $this->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', [])->assertForbidden();
        $usuario->update(['perfil' => User::PERFIL_ADMIN]);
        $this->getJson('/api/avaliador/projetos')->assertForbidden();
        $usuario->update(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => false]);
        $this->getJson('/api/avaliador/projetos')->assertUnauthorized();
    }
}
