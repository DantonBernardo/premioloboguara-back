<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Projeto;
use App\Models\Subcategoria;
use App\Models\User;
use App\Services\Avaliacao;
use App\Services\ChaveProjeto;
use App\Services\Relatorios;
use Database\Seeders\CategoriaSeeder;
use Database\Seeders\ProjetoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class CodigoProjetoTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_tem_codigos_fixos_e_subcategorias_por_categoria(): void
    {
        $this->assertDatabaseCount('categorias', 4);
        $this->assertDatabaseCount('subcategorias', 16);
        $this->assertSame([3, 6, 4, 3], Categoria::orderBy('codigo')->withCount('subcategorias')->get()->pluck('subcategorias_count')->all());
        $this->seed(CategoriaSeeder::class);
        $this->assertDatabaseCount('categorias', 4);
        $this->assertDatabaseCount('subcategorias', 16);
        $this->assertFalse(Schema::hasColumn('projetos', 'categoria'));
        $this->assertFalse(Schema::hasColumn('projetos', 'categoria_id'));
        $this->assertFalse(Schema::hasColumn('projetos', 'subcategoria'));
    }

    public function test_codigo_e_chave_sao_gerados_e_persistidos_no_formato_correto(): void
    {
        foreach (Subcategoria::with('categoria')->get() as $subcategoria) {
            $projeto = Projeto::factory()->create(['subcategoria_id' => $subcategoria->id])->fresh();
            $this->assertMatchesRegularExpression('/^[0-9]{8}$/', $projeto->chave);
            $this->assertSame($projeto->chave.'/'.$subcategoria->categoria->codigo.'/'.$subcategoria->codigo, $projeto->codigo);
        }
        $this->assertSame(16, Projeto::distinct()->count('chave'));
    }

    public function test_colisao_de_chave_gera_outra_sem_perder_o_projeto(): void
    {
        $this->mock(ChaveProjeto::class, function (MockInterface $mock): void {
            $mock->shouldReceive('gerar')->times(3)->andReturn('00000001', '00000001', '00000002');
        });
        $primeiro = Projeto::factory()->create();
        $segundo = Projeto::factory()->create();
        $this->assertSame('00000001/3/1', $primeiro->codigo);
        $this->assertSame('00000002/3/1', $segundo->codigo);
        $this->assertDatabaseCount('projetos', 2);
    }

    public function test_banco_impede_chave_duplicada_mesmo_em_outra_subcategoria(): void
    {
        $projeto = Projeto::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('projetos')->insert([
            'nome' => 'Duplicado', 'subcategoria_id' => $projeto->subcategoria_id,
            'chave' => $projeto->chave, 'codigo' => $projeto->chave.'/3/2', 'notas' => '[]', 'avaliador_ids' => '[]',
        ]);
    }

    public function test_reclassificar_sem_notas_preserva_a_chave_e_atualiza_o_sufixo(): void
    {
        $projeto = Projeto::factory()->create();
        $chave = $projeto->chave;
        $subcategoria = Subcategoria::whereHas('categoria', fn ($q) => $q->where('codigo', 2))->where('codigo', 4)->sole();
        $projeto->update(['subcategoria_id' => $subcategoria->id]);
        $this->assertSame($chave.'/2/4', $projeto->fresh()->codigo);
        $projeto->update(['nome' => 'Título corrigido']);
        $this->assertSame($chave.'/2/4', $projeto->fresh()->codigo);
    }

    public function test_reclassificar_com_notas_e_proibido_mesmo_tentando_limpar_o_array(): void
    {
        $projeto = Projeto::factory()->create(['notas' => [[
            'avaliador_id' => 1, 'c1' => 3, 'c2' => 3, 'c3' => 2, 'c4' => 2,
        ]]]);
        $this->expectException(ValidationException::class);
        $projeto->update(['subcategoria_id' => Subcategoria::where('id', '!=', $projeto->subcategoria_id)->firstOrFail()->id, 'notas' => []]);
    }

    public function test_codigo_nao_pode_ser_alterado_manualmente(): void
    {
        $projeto = Projeto::factory()->create();
        $this->expectException(ValidationException::class);
        $projeto->codigo = '12345678/1/1';
        $projeto->save();
    }

    public function test_chave_nao_pode_ser_alterada_manualmente(): void
    {
        $projeto = Projeto::factory()->create();
        $this->expectException(ValidationException::class);
        $projeto->chave = '12345678';
        $projeto->save();
    }

    public function test_subcategoria_inexistente_e_rejeitada(): void
    {
        $this->expectException(ValidationException::class);
        Projeto::factory()->create(['subcategoria_id' => 999999]);
    }

    public function test_exclusao_de_subcategoria_em_uso_e_bloqueada_pelo_banco(): void
    {
        $projeto = Projeto::factory()->create();
        $this->expectException(QueryException::class);
        $projeto->subcategoria->delete();
    }

    public function test_codigo_da_categoria_e_permanente(): void
    {
        $this->expectException(ValidationException::class);
        Categoria::where('codigo', 3)->sole()->update(['codigo' => 99]);
    }

    public function test_subcategoria_nao_pode_ser_movida_para_outra_categoria(): void
    {
        $subcategoria = Subcategoria::firstOrFail();
        $this->expectException(ValidationException::class);
        $subcategoria->update(['categoria_id' => Categoria::where('id', '!=', $subcategoria->categoria_id)->firstOrFail()->id]);
    }

    public function test_renomear_categoria_nao_altera_regras_de_pontuacao(): void
    {
        $categoria = Categoria::where('codigo', 1)->sole();
        $categoria->update(['nome' => 'Pesquisa científica']);
        $projeto = Projeto::factory()->create([
            'subcategoria_id' => $categoria->subcategorias()->firstOrFail()->id,
            'notas' => [['avaliador_id' => 1, 'c1' => 3, 'c2' => 0, 'c3' => 3, 'c4' => 2]],
        ]);
        $this->assertEquals(8, $projeto->nota_final);
        $this->assertTrue($projeto->notas[0]['habilitada']);
        $this->assertSame('habilitada', app(Relatorios::class)->gerar()['projetos'][0]['status']);
        $this->assertSame(2, app(Avaliacao::class)->criterios(1)['criterios']['c2']['maximo']);
    }

    public function test_consulta_por_codigo_respeita_designacao_e_catalogo_exige_login(): void
    {
        $usuario = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $projeto = Projeto::factory()->create(['avaliador_ids' => [$usuario->id]]);
        $url = '/api/avaliador/projetos/por-codigo/'.$projeto->codigo;
        $this->getJson('/api/avaliador/categorias')->assertUnauthorized();
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($usuario)->getJson('/api/avaliador/categorias')->assertOk()->assertJsonCount(4)->assertJsonPath('2.codigo', 3);
        $this->getJson($url)->assertOk()->assertJsonPath('projeto.codigo', $projeto->codigo)->assertJsonPath('projeto.categoria', 'Projeto Inovador');
        $outro = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->actingAs($outro)->getJson($url)->assertForbidden();
        $this->getJson('/api/avaliador/projetos/por-codigo/'.$projeto->chave.'/1/1')->assertNotFound();
    }

    public function test_seed_de_projetos_reais_preserva_codigos_e_avaliacoes_ao_reexecutar(): void
    {
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 147);
        $this->assertSame(147, Projeto::distinct()->count('chave'));
        foreach (Projeto::with('subcategoria.categoria')->get() as $item) {
            $this->assertMatchesRegularExpression('/^[0-9]{8}$/', $item->chave);
            $this->assertSame($item->chave.'/'.$item->subcategoria->categoria->codigo.'/'.$item->subcategoria->codigo, $item->codigo);
        }
        $codigos = Projeto::orderBy('id')->pluck('codigo', 'id')->all();
        $projeto = Projeto::firstOrFail();
        $projeto->update(['notas' => [['avaliador_id' => $projeto->avaliador_ids[0], 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2]]]);
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 147);
        $this->assertSame($codigos, Projeto::orderBy('id')->pluck('codigo', 'id')->all());
        $this->assertCount(1, $projeto->fresh()->notas);
        $this->assertEquals(8, $projeto->fresh()->nota_final);
    }

    public function test_migracao_preserva_ids_notas_e_media_existentes(): void
    {
        $projeto = Projeto::factory()->create(['notas' => [['avaliador_id' => 1, 'c1' => 3, 'c2' => 2, 'c3' => 2, 'c4' => 2]]]);
        $id = $projeto->id;
        $notas = $projeto->notas;
        $migracao = require database_path('migrations/2026_11_14_184207_normalize_categorias_and_add_codigo_to_projetos.php');
        $migracao->down();
        $this->assertSame('Projeto Inovador', DB::table('projetos')->where('id', $id)->value('categoria'));
        $migracao->up();
        $migrado = Projeto::findOrFail($id);
        $this->assertSame($notas, $migrado->notas);
        $this->assertEquals(9, $migrado->nota_final);
        $this->assertMatchesRegularExpression('/^[0-9]{8}\/3\/1$/', $migrado->codigo);
        $this->assertDatabaseCount('projetos', 1);
    }
}
