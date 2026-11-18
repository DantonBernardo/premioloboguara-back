<?php

namespace Tests\Feature;

use App\Models\Projeto;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_authenticates_but_cannot_read_or_write_real_data(): void
    {
        $this->seed(DemoUserSeeder::class);
        $projeto = Projeto::factory()->create();
        $this->postJson('/api/auth/login', ['email' => 'demo@premioloboguara.local', 'senha' => 'DemoLobo26!'])
            ->assertOk()->assertJsonPath('usuario.perfil', 'demo')->assertJsonPath('usuario.precisa_aceitar_termos', false);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('usuario.perfil', 'demo');
        $this->getJson('/api/csrf')->assertNoContent();
        foreach (['/api/admin/dashboard', '/api/admin/indicadores', '/api/admin/relatorios', '/api/admin/relatorios/pdf', '/api/admin/ranking', '/api/admin/equipe/pdf', '/api/admin/projetos', '/api/admin/projetos/'.$projeto->id, '/api/admin/projetos/'.$projeto->id.'/pdf', '/api/avaliador/categorias', '/api/avaliador/termos', '/api/avaliador/projetos', '/api/avaliador/projetos/'.$projeto->id] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->patchJson('/api/configuracoes/perfil', ['nome' => 'Alterado'])->assertForbidden();
        $this->postJson('/api/avaliador/termos/aceite', ['aceito' => true])->assertForbidden();
        $this->putJson('/api/avaliador/projetos/'.$projeto->id.'/avaliacao', ['c1' => 3, 'c2' => 3, 'c3' => 2, 'c4' => 2])->assertForbidden();
        $this->assertSame([], $projeto->fresh()->notas);
        $this->assertDatabaseHas('users', ['email' => 'demo@premioloboguara.local', 'name' => 'Avaliador de demonstração', 'termos_aceitos_em' => null]);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_demo_seeder_is_idempotent_and_does_not_affect_admin_data(): void
    {
        $admin = User::factory()->create(['perfil' => User::PERFIL_ADMIN, 'ativo' => true]);
        $avaliador = User::factory()->create(['perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        Projeto::factory()->create(['avaliador_ids' => [$avaliador->id]]);
        $antes = $this->actingAs($admin)->getJson('/api/admin/relatorios')->assertOk()->json();
        $this->seed(DemoUserSeeder::class);
        $hash = User::where('perfil', User::PERFIL_DEMO)->sole()->password;
        $this->seed(DemoUserSeeder::class);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('projetos', 1);
        $this->assertSame($hash, User::where('perfil', User::PERFIL_DEMO)->sole()->password);
        $this->getJson('/api/admin/indicadores')->assertExactJson(['projetos' => 1, 'avaliadores' => 1]);
        $depois = $this->getJson('/api/admin/relatorios')->assertOk()->json();
        $this->assertSame($antes['totais'], $depois['totais']);
        $this->assertSame($antes['avaliadores'], $depois['avaliadores']);
        $this->getJson('/api/admin/avaliadores/'.User::where('perfil', User::PERFIL_DEMO)->sole()->id)->assertNotFound();
    }
}
