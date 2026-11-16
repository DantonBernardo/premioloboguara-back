<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Projeto;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestaoAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['perfil' => User::PERFIL_ADMIN, 'ativo' => true]);
    }

    public function test_indicadores_usam_contagem_real_e_nao_contam_administradores_como_avaliadores(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create(['perfil' => User::PERFIL_AVALIADOR]);
        User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => false]);
        User::factory()->create(['perfil' => User::PERFIL_ADMIN]);
        Projeto::factory()->count(3)->create();
        $this->actingAs($admin)->getJson('/api/admin/indicadores')->assertOk()->assertExactJson(['projetos' => 3, 'avaliadores' => 3]);
        Projeto::factory()->create();
        $this->getJson('/api/admin/indicadores')->assertJsonPath('projetos', 4);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('resumo.projetos', 4)
            ->assertJsonMissingPath('resumo.admins')->assertJsonMissingPath('resumo.avaliadores_ativos')
            ->assertJsonMissingPath('projetos')->assertJsonMissingPath('avaliadores')
            ->assertJsonPath('por_categoria.2.total', 4);
    }

    public function test_relatorio_retorna_medias_situacoes_e_faixas_reais(): void
    {
        $admin = $this->admin();
        Projeto::factory()->create(['notas' => [['avaliador_id' => 1, 'c1' => 3, 'c2' => 3, 'c3' => 2, 'c4' => 1]]]);
        Projeto::factory()->create(['notas' => [['avaliador_id' => 2, 'c1' => 1, 'c2' => 1, 'c3' => 1, 'c4' => 1]]]);
        Projeto::factory()->create();
        $this->actingAs($admin)->getJson('/api/admin/relatorios')->assertOk()
            ->assertJsonPath('totais.projetos', 3)->assertJsonPath('totais.avaliacoes_recebidas', 2)
            ->assertJsonPath('totais.media_geral', 6.5)->assertJsonPath('totais.habilitadas', 1)
            ->assertJsonPath('distribuicao_notas.0.total', 1)->assertJsonPath('distribuicao_notas.3.total', 1)
            ->assertJsonPath('por_status.3.total', 1)->assertJsonPath('projetos.data.0.nota_final', 9)
            ->assertJsonCount(4, 'categorias');
    }

    public function test_filtros_do_relatorio_afetam_graficos_listas_e_progresso(): void
    {
        $admin = $this->admin();
        $avaliador = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR]);
        $pesquisa = Categoria::where('codigo', 1)->sole()->subcategorias()->firstOrFail();
        Projeto::factory()->create(['subcategoria_id' => $pesquisa->id, 'avaliador_ids' => [$avaliador->id], 'notas' => [['avaliador_id' => $avaliador->id, 'c1' => 3, 'c2' => 2, 'c3' => 3, 'c4' => 1]]]);
        Projeto::factory()->create(['avaliador_ids' => [$avaliador->id]]);
        $this->actingAs($admin)->getJson('/api/admin/relatorios?categoria=1&status=habilitada')->assertOk()
            ->assertJsonPath('totais.projetos', 1)->assertJsonPath('totais.media_geral', 9)
            ->assertJsonPath('avaliadores.0.designados', 1)->assertJsonPath('avaliadores.0.concluidas', 1)
            ->assertJsonPath('avaliadores.0.pendentes', 0)->assertJsonPath('filtros.categoria', 1)
            ->assertJsonPath('por_categoria.0.categoria', 'Pesquisador Inovador');
        $this->getJson('/api/admin/relatorios?categoria=1&status=sem_avaliacao')->assertOk()
            ->assertJsonPath('totais.projetos', 0)->assertJsonPath('totais.media_geral', null);
        $this->getJson('/api/admin/relatorios?categoria=999')->assertUnprocessable();
        $this->getJson('/api/admin/relatorios?status=inexistente')->assertUnprocessable();
    }

    public function test_pdf_e_um_arquivo_valido_com_filtros_e_com_banco_vazio(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/api/admin/relatorios/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        Projeto::factory()->create(['notas' => [['avaliador_id' => 1, 'c1' => 3, 'c2' => 3, 'c3' => 2, 'c4' => 2]]]);
        $response = $this->get('/api/admin/relatorios/pdf?categoria=3&status=habilitada');
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->getJson('/api/admin/relatorios/pdf?categoria=999')->assertUnprocessable();
    }

    public function test_pdf_identifica_avaliadores_concluidos_pendentes_e_sem_designacao(): void
    {
        $admin = $this->admin();
        $fez = User::factory()->create(['name' => 'Avaliadora concluÃ­da', 'perfil' => User::PERFIL_AVALIADOR]);
        $pendente = User::factory()->create(['name' => 'Avaliador pendente', 'perfil' => User::PERFIL_AVALIADOR]);
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$fez->id, $pendente->id], 'notas' => [['avaliador_id' => $fez->id, 'c1' => 3, 'c2' => 3, 'c3' => 2, 'c4' => 2]]]);
        $semDesignacao = Projeto::factory()->create();
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('relatorios.pdf', \Mockery::on(function (array $dados) use ($projeto, $semDesignacao, $fez, $pendente): bool {
            $projetos = collect($dados['r']['projetos'])->keyBy('id');
            $this->assertSame([
                ['nome' => $fez->name, 'concluida' => true],
                ['nome' => $pendente->name, 'concluida' => false],
            ], $projetos[$projeto->id]['avaliadores']);
            $this->assertSame([], $projetos[$semDesignacao->id]['avaliadores']);

            return true;
        }))->andReturn($pdf);
        $this->actingAs($admin)->get('/api/admin/relatorios/pdf')->assertOk();
    }

    public function test_pdf_de_resultados_contem_apenas_resultados_concluidos_do_filtro(): void
    {
        $notas = collect([1, 2, 3])->map(fn (int $id): array => ['avaliador_id' => $id, 'c1' => 3, 'c2' => 3, 'c3' => 2, 'c4' => 2])->all();
        $aprovado = Projeto::factory()->create(['notas' => $notas]);
        Projeto::factory()->create(['notas' => [$notas[0]]]);
        Projeto::factory()->create(['notas' => collect($notas)->map(fn (array $nota): array => array_replace($nota, ['c1' => 0]))->all()]);
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('relatorios.resultados', \Mockery::on(function (array $dados) use ($aprovado): bool {
            $this->assertSame([$aprovado->id], $dados['projetos']->pluck('id')->all());
            $html = view('relatorios.resultados', $dados)->render();
            $this->assertStringContainsString($aprovado->nome, $html);
            $this->assertStringNotContainsString('<img', $html);
            $this->assertArrayNotHasKey('graficos', $dados);

            return true;
        }))->andReturn($pdf);
        $this->actingAs($this->admin())->get('/api/admin/relatorios/pdf?tipo=resultados&status=habilitada')->assertOk();
    }

    public function test_rotas_de_gestao_sao_restritas_a_administradores_ativos(): void
    {
        foreach (['/api/admin/dashboard', '/api/admin/indicadores', '/api/admin/relatorios', '/api/admin/relatorios/pdf'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $avaliador = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->actingAs($avaliador);
        foreach (['/api/admin/dashboard', '/api/admin/indicadores', '/api/admin/relatorios', '/api/admin/relatorios/pdf'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_configuracoes_atualizam_apenas_o_nome_do_usuario_autenticado(): void
    {
        $usuario = $this->admin();
        $outro = User::factory()->create();
        $this->actingAs($usuario)->patchJson('/api/configuracoes/perfil', ['nome' => 'Gestor SECTI', 'id' => $outro->id, 'perfil' => 'avaliador', 'email' => 'outro@example.test'])
            ->assertOk()->assertJsonPath('usuario.nome', 'Gestor SECTI');
        $this->assertSame('Gestor SECTI', $usuario->fresh()->name);
        $this->assertSame(User::PERFIL_ADMIN, $usuario->fresh()->perfil);
        $this->assertSame($usuario->email, $usuario->fresh()->email);
        $this->assertSame($outro->name, $outro->fresh()->name);
        $this->patchJson('/api/configuracoes/perfil', ['nome' => '  '])->assertUnprocessable();
        $this->patchJson('/api/configuracoes/perfil', ['nome' => str_repeat('a', 151)])->assertUnprocessable();
    }

    public function test_avaliador_tambem_pode_atualizar_o_proprio_nome_e_inativo_nao_pode(): void
    {
        $this->patchJson('/api/configuracoes/perfil', ['nome' => 'Nome'])->assertUnauthorized();
        $usuario = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->actingAs($usuario)->patchJson('/api/configuracoes/perfil', ['nome' => 'Avaliador Atualizado'])->assertOk();
        $usuario->update(['ativo' => false]);
        $this->patchJson('/api/configuracoes/perfil', ['nome' => 'Outra alteraÃƒÂ§ÃƒÂ£o'])->assertForbidden();
    }
}
