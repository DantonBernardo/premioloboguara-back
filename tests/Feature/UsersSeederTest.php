<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UsersSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_e_criado_e_sua_senha_nao_e_redefinida_ao_reexecutar(): void
    {
        $this->seed(UsersSeeder::class);
        $admin = User::where('email', 'scti@guarapuava.pr.gov.br')->sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->ativo);
        $this->assertTrue(Hash::check('senha123', $admin->password));
        $admin->update(['password' => 'SenhaNovaIndividual123']);
        $senha = $admin->password;
        $this->seed(UsersSeeder::class);
        $this->assertSame($senha, $admin->fresh()->password);
        $this->assertDatabaseCount('users', 23);
    }

    public function test_importa_somente_os_vinte_e_dois_aceitos_com_campos_vazios_como_null(): void
    {
        $this->seed(UsersSeeder::class);
        $this->assertDatabaseCount('users', 23);
        $this->assertSame(8, User::whereNull('email')->count());
        $this->assertSame(2, User::whereNull('organizacao')->count());
        $this->assertSame(22, User::where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->count());
        $this->assertDatabaseHas('users', ['name' => 'Gustavo Comeli', 'email' => null, 'organizacao' => 'Hub Gov Tech']);
        $this->assertDatabaseHas('users', ['name' => 'Elisangela Rosa', 'email' => 'erosa@pr.sebrae.com.br']);
        $this->assertDatabaseHas('users', ['name' => 'Cesar Antonio Bitencourt', 'contato' => '351 911 096 045', 'organizacao' => null]);
        foreach (['Thales Tito', 'Marina de Souza Bastos', 'Alan Alex Debus', 'Emerson Ribeiro Lourenço', 'Roger Medke'] as $nome) {
            $this->assertDatabaseMissing('users', ['name' => $nome]);
        }
        $this->assertSame(23, User::distinct()->count('password'));
        foreach (User::all() as $usuario) {
            $this->assertTrue(Hash::check('senha123', $usuario->password));
        }
        $this->assertTrue(Hash::isHashed(User::firstOrFail()->password));
    }

    public function test_reexecutar_preserva_senhas_email_preenchido_e_organizacao_corrigida(): void
    {
        $this->seed(UsersSeeder::class);
        $usuario = User::where('name', 'Renato Maçaneiro')->sole();
        $usuario->update(['email' => 'renato@example.test', 'password' => 'UmaSenhaIndividual123', 'organizacao' => 'Organização atualizada', 'ativo' => false]);
        $senha = $usuario->password;
        $this->seed(UsersSeeder::class);
        $this->assertDatabaseCount('users', 23);
        $this->assertSame($senha, $usuario->fresh()->password);
        $this->assertSame('renato@example.test', $usuario->fresh()->email);
        $this->assertSame('Organização atualizada', $usuario->fresh()->organizacao);
        $this->assertFalse($usuario->fresh()->ativo);
    }

    public function test_conta_existente_por_email_preserva_perfil_nome_e_senha(): void
    {
        $usuario = User::factory()->create(['name' => 'Andrea cadastrada', 'email' => 'deiav28@gmail.com', 'perfil' => User::PERFIL_ADMIN]);
        $senha = $usuario->password;
        $this->seed(UsersSeeder::class);
        $this->assertDatabaseCount('users', 23);
        $this->assertSame(User::PERFIL_ADMIN, $usuario->fresh()->perfil);
        $this->assertSame('Andrea cadastrada', $usuario->fresh()->name);
        $this->assertSame($senha, $usuario->fresh()->password);
        $this->assertSame('44 8406-2122', $usuario->fresh()->contato);
        $this->assertSame('Paranavaí', $usuario->fresh()->organizacao);
    }

    public function test_email_continua_unico_quando_informado(): void
    {
        User::factory()->create(['email' => 'unico@example.test']);
        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'unico@example.test']);
    }

    public function test_email_nulo_nao_pode_ser_usado_para_login(): void
    {
        User::factory()->create(['email' => null, 'password' => 'SenhaTeste123']);
        $this->postJson('/api/auth/login', ['email' => null, 'senha' => 'SenhaTeste123'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_perfil_retorna_contato_organizacao_e_email_nulo(): void
    {
        $usuario = User::factory()->create(['name' => 'Avaliador sem email', 'email' => null, 'perfil' => User::PERFIL_AVALIADOR, 'ativo' => true, 'contato' => '42 99999-0000', 'organizacao' => 'Universidade']);
        $this->actingAs($usuario)->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('usuario.email', null)
            ->assertJsonPath('usuario.contato', '42 99999-0000')
            ->assertJsonPath('usuario.organizacao', 'Universidade');
    }

    public function test_reverter_migracao_nao_inventa_email_para_usuarios_sem_email(): void
    {
        User::factory()->create(['email' => null]);
        $migracao = require database_path('migrations/2026_11_14_211608_add_contato_organizacao_and_nullable_email_to_users_table.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Preencha os e-mails');
        $migracao->down();
    }
}
