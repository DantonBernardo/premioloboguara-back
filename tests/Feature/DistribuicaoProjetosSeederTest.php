<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\User;
use Database\Seeders\ProjetoSeeder;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistribuicaoProjetosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_duzentos_projetos_tem_trios_unicos_ativos_e_carga_balanceada(): void
    {
        $this->seed(UsersSeeder::class);
        $inativo = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => false]);
        $this->seed(ProjetoSeeder::class);
        $this->assertDatabaseCount('projetos', 200);
        $ativos = User::where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->pluck('id')->all();
        $cargas = array_fill_keys($ativos, 0);
        $trios = [];
        $estagios = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
        foreach (Projeto::all() as $projeto) {
            $ids = $projeto->avaliador_ids;
            $this->assertCount(3, array_unique($ids));
            $this->assertNotContains($inativo->id, $ids);
            foreach ($ids as $id) {
                $this->assertContains($id, $ativos);
                $cargas[$id]++;
            }
            foreach ($projeto->notas as $nota) {
                $this->assertContains($nota['avaliador_id'], $ids);
            }
            sort($ids);
            $trios[] = implode('-', $ids);
            $estagios[count($projeto->notas)]++;
        }
        $this->assertCount(200, array_unique($trios));
        $this->assertSame(600, array_sum($cargas));
        $this->assertLessThanOrEqual(1, max($cargas) - min($cargas));
        $this->assertSame(67, $estagios[0]);
        $this->assertSame(67, $estagios[1] + $estagios[2]);
        $this->assertSame(66, $estagios[3]);
        $andrea = User::where('name', 'Andrea Alves Vieira')->sole();
        $this->actingAs($andrea);
        foreach ([1, 2, 3, 4] as $categoria) {
            $resposta = $this->getJson('/api/avaliador/projetos?status=pendentes&categoria='.$categoria)->assertOk();
            $this->assertGreaterThanOrEqual(2, $resposta->json('projetos.total'));
        }
        $estado = Projeto::orderBy('id')->get(['id', 'codigo', 'avaliador_ids', 'notas'])->toArray();
        $this->seed(ProjetoSeeder::class);
        $this->assertSame($estado, Projeto::orderBy('id')->get(['id', 'codigo', 'avaliador_ids', 'notas'])->toArray());
    }

    public function test_seed_redistribui_os_trinta_exemplos_antigos_preservando_identificadores_e_notas_manuais(): void
    {
        $this->seed(ProjetoSeeder::class);
        $primeiros = Projeto::orderBy('id')->limit(30)->get();
        $codigos = $primeiros->pluck('codigo', 'id')->all();
        $legado = User::where('perfil', User::PERFIL_AVALIADOR)->orderBy('id')->limit(3)->pluck('id')->all();
        foreach ($primeiros as $projeto) {
            $notas = $projeto->notas;
            foreach ($notas as $indice => &$nota) {
                $nota['avaliador_id'] = $legado[$indice];
            }
            unset($nota);
            $projeto->update(['avaliador_ids' => $legado, 'notas' => $notas]);
        }
        $manual = $primeiros[0];
        $manual->update(['notas' => [['avaliador_id' => $legado[0], 'c1' => 2, 'c2' => 2, 'c3' => 2, 'c4' => 2, 'justificativas' => ['c1' => 'Análise manual']]]]);
        $notasManuais = $manual->fresh()->notas;
        $this->seed(ProjetoSeeder::class);
        $this->assertSame($notasManuais, $manual->fresh()->notas);
        $this->assertSame($legado, $manual->fresh()->avaliador_ids);
        $this->assertSame($codigos, Projeto::whereIn('id', array_keys($codigos))->orderBy('id')->pluck('codigo', 'id')->all());
        $this->assertCount(200, Projeto::all()->map(function (Projeto $p): string {
            $ids = $p->avaliador_ids;
            sort($ids);

            return implode('-', $ids);
        })->unique()->all());
    }
}
