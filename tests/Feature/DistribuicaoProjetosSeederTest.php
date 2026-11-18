<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\Subcategoria;
use App\Models\User;
use Database\Seeders\ProjetoSeeder;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistribuicaoProjetosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_projetos_reais_com_grupos_da_planilha_e_sem_notas(): void
    {
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 171);
        $fonte = json_decode(file_get_contents(database_path('seeders/projetos-reais.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($fonte['projetos'] as $linha) {
            $projeto = Projeto::where('nome', $linha['nome'])->whereHas('subcategoria', fn ($q) => $q->where('codigo', $linha['subcategoria'])->whereHas('categoria', fn ($c) => $c->where('codigo', $linha['categoria'])))->sole();
            $nomes = User::whereIn('id', $projeto->avaliador_ids)->pluck('name')->sort()->values()->all();
            $esperados = collect($fonte['grupos'][$linha['grupo']])->sort()->values()->all();
            $this->assertSame($esperados, $nomes);
            $this->assertSame([], $projeto->notas);
            $this->assertNull($projeto->nota_final);
            $this->assertSame($linha['categoria'], $projeto->subcategoria->categoria->codigo);
            $this->assertSame($linha['subcategoria'], $projeto->subcategoria->codigo);
        }
        $this->assertFalse(collect($fonte['grupos'])->flatten()->contains('Leticia Wolf'));
        $this->assertTrue(collect($fonte['grupos'])->flatten()->contains('Henrique Domakoski'));
        $andrea = User::where('name', 'Andrea Alves Vieira')->sole();
        $this->actingAs($andrea)->postJson('/api/avaliador/termos/aceite', [
            'versao' => config('termos_avaliador.versao'), 'leitura_concluida' => true, 'aceito' => true,
        ])->assertOk();
        $this->actingAs($andrea->refresh())->getJson('/api/avaliador/projetos')->assertOk()->assertJsonPath('projetos.total', collect($fonte['projetos'])->filter(fn ($p) => in_array('Andrea Alves Vieira', $fonte['grupos'][$p['grupo']], true))->count());
    }

    public function test_resolve_variacao_do_nome_angelo_sem_recriar_cadastro(): void
    {
        $this->seed(UsersSeeder::class);
        $angelo = User::where('name', 'Angelo Lenart Homen')->sole();
        $angelo->update(['name' => 'Angelo Lenart Homem']);
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 171);
        $this->assertGreaterThan(0, Projeto::whereJsonContains('avaliador_ids', $angelo->id)->count());
    }

    public function test_reexecutar_preserva_ids_codigos_e_avaliacoes_reais(): void
    {
        $this->seed(ProjetoSeeder::class);
        $projeto = Projeto::firstOrFail();
        $projeto->update(['notas' => [['avaliador_id' => $projeto->avaliador_ids[0], 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2, 'justificativa_geral' => 'Avaliação real.']]]);
        $estado = Projeto::orderBy('id')->get(['id', 'codigo', 'notas', 'avaliador_ids'])->toArray();
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 171);
        $this->assertSame($estado, Projeto::orderBy('id')->get(['id', 'codigo', 'notas', 'avaliador_ids'])->toArray());
    }

    public function test_todos_os_aceitos_participam_com_carga_equilibrada_e_especialista_em_cada_dupla(): void
    {
        $this->seed(ProjetoSeeder::class);
        $fonte = json_decode(file_get_contents(database_path('seeders/projetos-reais.json')), true, flags: JSON_THROW_ON_ERROR);
        $usuarios = User::where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->get();
        $projetos = Projeto::with('subcategoria.categoria')->get();
        $cargas = $projetos->pluck('avaliador_ids')->flatten()->countBy();
        $this->assertCount(30, $usuarios);
        $this->assertCount(30, $cargas);
        $this->assertSame(342, $cargas->sum());
        $this->assertSame(11, $cargas->min());
        $this->assertSame(12, $cargas->max());
        foreach ($usuarios as $usuario) {
            $this->assertGreaterThanOrEqual(11, $cargas[$usuario->id]);
        }
        foreach (['Thaís Cristina da Costa', 'Salette Silveira Azevedo', 'Paulo Alvim'] as $nome) {
            $this->assertSame(11, $cargas[$usuarios->firstWhere('name', $nome)->id]);
        }
        foreach ($projetos as $projeto) {
            $this->assertCount(2, array_unique($projeto->avaliador_ids));
            $especialista = $usuarios->whereIn('id', $projeto->avaliador_ids)->contains(
                fn (User $usuario): bool => ($fonte['areas_avaliadores'][$usuario->name] ?? null) === $projeto->subcategoria->categoria->codigo,
            );
            $this->assertTrue($especialista, $projeto->nome);
        }
    }

    public function test_redistribuicao_nao_remove_avaliador_com_nota_publicada(): void
    {
        $this->seed(UsersSeeder::class);
        $usuario = User::where('name', 'Gustavo Comeli')->sole();
        $andrea = User::where('name', 'Andrea Alves Vieira')->sole();
        $fonte = json_decode(file_get_contents(database_path('seeders/projetos-reais.json')), true, flags: JSON_THROW_ON_ERROR);
        $linha = collect($fonte['projetos'])->firstWhere('nome', 'PLANTON - ESCALAS DE PLANTÃO');
        $subcategoria = Subcategoria::where('codigo', $linha['subcategoria'])->whereHas('categoria', fn ($query) => $query->where('codigo', $linha['categoria']))->sole();
        $projeto = Projeto::factory()->create([
            'nome' => $linha['nome'], 'subcategoria_id' => $subcategoria->id,
            'avaliador_ids' => [$andrea->id, $usuario->id],
            'notas' => [['avaliador_id' => $usuario->id, 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2]],
        ]);
        $estado = $projeto->fresh()->getAttributes();
        try {
            $this->seed(ProjetoSeeder::class);
            $this->fail('Não deve remover um avaliador com nota registrada.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('avaliador com nota registrada', $exception->getMessage());
        }
        $this->assertSame($estado, $projeto->fresh()->getAttributes());
        $this->assertDatabaseCount('projetos', 1);
    }
}
