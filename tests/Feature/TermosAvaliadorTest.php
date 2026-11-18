<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermosAvaliadorTest extends TestCase
{
    use RefreshDatabase;

    private function aceite(): array
    {
        return ['versao' => config('termos_avaliador.versao'), 'leitura_concluida' => true, 'aceito' => true];
    }

    public function test_primeiro_login_exige_aceite_e_bloqueia_a_api_de_avaliacoes(): void
    {
        $usuario = User::factory()->create(['password' => 'SenhaTeste123']);
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        $this->postJson('/api/auth/login', ['email' => $usuario->email, 'senha' => 'SenhaTeste123'])
            ->assertOk()->assertJsonPath('usuario.precisa_aceitar_termos', true);
        $this->getJson('/api/avaliador/termos')->assertOk()
            ->assertJsonPath('secoes.0.titulo', '4. PARECER TÉCNICO CONCLUSIVO DO AVALIADOR (A)')
            ->assertJsonPath('secoes.1.paragrafos.1', '(1) Ausência de Conflito de Interesses: Não possuo relação de parentesco até o 3º grau, vínculo societário, interesse financeiro direto ou subordinação com a equipe proponente (Art. 16º);');
        foreach (['/api/avaliador/projetos', '/api/avaliador/projetos/'.$projeto->id, '/api/avaliador/categorias', '/api/avaliador/projetos/por-codigo/'.$projeto->codigo] as $url) {
            $this->getJson($url)->assertForbidden()->assertJsonPath('codigo', 'aceite_termos_necessario');
        }
        $this->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', ['c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2])->assertForbidden();
        $this->assertSame([], $projeto->fresh()->notas);
    }

    public function test_aceite_exige_confirmacao_leitura_e_versao_atual(): void
    {
        $usuario = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->actingAs($usuario);
        foreach ([[], ['aceito' => false], ['leitura_concluida' => false], ['versao' => 'invalida']] as $alteracoes) {
            $dados = $alteracoes === [] ? [] : array_replace($this->aceite(), $alteracoes);
            $this->postJson('/api/avaliador/termos/aceite', $dados)->assertUnprocessable();
            $this->assertNull($usuario->fresh()->termos_aceitos_em);
        }
    }

    public function test_aceite_fica_no_banco_e_nao_reaparece_nem_altera_data_no_proximo_login(): void
    {
        $usuario = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true, 'password' => 'SenhaTeste123']);
        $this->actingAs($usuario)->postJson('/api/avaliador/termos/aceite', $this->aceite())
            ->assertOk()->assertJsonPath('usuario.precisa_aceitar_termos', false);
        $data = $usuario->fresh()->termos_aceitos_em->toISOString();
        $this->assertSame(config('termos_avaliador.versao'), $usuario->fresh()->termos_versao);
        $this->actingAs($usuario->refresh())->getJson('/api/avaliador/projetos')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $usuario->email, 'senha' => 'SenhaTeste123'])
            ->assertOk()->assertJsonPath('usuario.precisa_aceitar_termos', false)->assertJsonPath('usuario.termos_aceitos_em', $data);
        $this->travel(1)->days();
        $this->postJson('/api/avaliador/termos/aceite', $this->aceite())->assertOk();
        $this->assertSame($data, $usuario->fresh()->termos_aceitos_em->toISOString());
    }

    public function test_administrador_e_isento_e_nao_pode_registrar_aceite_como_avaliador(): void
    {
        $admin = User::factory()->create(['perfil' => User::PERFIL_ADMIN, 'ativo' => true]);
        $this->actingAs($admin)->getJson('/api/auth/me')->assertOk()->assertJsonPath('usuario.precisa_aceitar_termos', false);
        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->getJson('/api/avaliador/termos')->assertForbidden();
        $this->postJson('/api/avaliador/termos/aceite', $this->aceite())->assertForbidden();
        $this->assertNull($admin->fresh()->termos_aceitos_em);
    }

    public function test_termos_nao_sao_acessiveis_por_usuario_inativo_ou_sem_login(): void
    {
        $this->getJson('/api/avaliador/termos')->assertUnauthorized();
        $this->postJson('/api/avaliador/termos/aceite', $this->aceite())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['ativo' => false]))->postJson('/api/avaliador/termos/aceite', $this->aceite())->assertUnauthorized();
    }
}
