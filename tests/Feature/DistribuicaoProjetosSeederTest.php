<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\User;
use Database\Seeders\ProjetoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistribuicaoProjetosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_projetos_reais_com_grupos_da_planilha_e_sem_notas(): void
    {
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 147);
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
        $this->assertNotContains('II', array_keys($fonte['grupos']));
        $andrea = User::where('name', 'Andrea Alves Vieira')->sole();
        $this->actingAs($andrea)->getJson('/api/avaliador/projetos')->assertOk()->assertJsonPath('projetos.total', 25);
    }

    public function test_reexecutar_preserva_ids_codigos_e_avaliacoes_reais(): void
    {
        $this->seed(ProjetoSeeder::class);
        $projeto = Projeto::firstOrFail();
        $projeto->update(['notas' => [['avaliador_id' => $projeto->avaliador_ids[0], 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2, 'justificativa_geral' => 'Avaliação real.']]]);
        $estado = Projeto::orderBy('id')->get(['id', 'codigo', 'notas', 'avaliador_ids'])->toArray();
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 147);
        $this->assertSame($estado, Projeto::orderBy('id')->get(['id', 'codigo', 'notas', 'avaliador_ids'])->toArray());
    }
}
