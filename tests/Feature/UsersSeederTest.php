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
        $this->assertDatabaseCount('users', 31);
    }

    public function test_importa_somente_os_trinta_aceitos_da_lista_final(): void
    {
        $this->seed(UsersSeeder::class);
        $this->assertDatabaseCount('users', 31);
        $this->assertSame(0, User::whereNull('email')->count());
        $this->assertSame(3, User::whereNull('organizacao')->count());
        $this->assertSame(30, User::where('perfil', User::PERFIL_AVALIADOR)->where('ativo', true)->count());
        $this->assertDatabaseHas('users', ['name' => 'Gustavo Comeli']);
        $this->assertSame(3, User::where('perfil', User::PERFIL_AVALIADOR)->whereNull('cpf')->count());
        $this->assertDatabaseHas('users', ['name' => 'Elisangela Rosa', 'email' => 'erosa@pr.sebrae.com.br']);
        $this->assertDatabaseHas('users', ['name' => 'Cesar Antonio Bitencourt']);
        foreach (['Thales Tito', 'Alan Alex Debus', 'Emerson Ribeiro Lourenço', 'Roger Medke'] as $nome) {
            $this->assertDatabaseMissing('users', ['name' => $nome]);
        }
        $this->assertSame(31, User::distinct()->count('password'));
        $this->assertSame(31, User::distinct()->count('password'));
        $this->assertTrue(Hash::isHashed(User::firstOrFail()->password));
        $comCpf = User::whereNotNull('cpf')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9]{11}$/', $comCpf->cpf);
        $this->assertArrayNotHasKey('cpf', $comCpf->toArray());
        $this->assertArrayNotHasKey('cpf', $comCpf->toPublicArray());
    }

    public function test_reexecutar_preserva_senhas_email_preenchido_e_organizacao_corrigida(): void
    {
        $this->seed(UsersSeeder::class);
        $usuario = User::where('name', 'Renato Maçaneiro')->sole();
        $usuario->update(['email' => 'renato@example.test', 'password' => 'UmaSenhaIndividual123', 'organizacao' => 'Organização atualizada', 'ativo' => false]);
        $senha = $usuario->password;
        $this->seed(UsersSeeder::class);
        $this->assertDatabaseCount('users', 31);
        $this->assertSame($senha, $usuario->fresh()->password);
        $this->assertSame('rmacaneiro@gmail.com.br', $usuario->fresh()->email);
        $this->assertSame('Fomento Paraná', $usuario->fresh()->organizacao);
        $this->assertTrue($usuario->fresh()->ativo);
    }

    public function test_conta_existente_por_email_preserva_perfil_nome_e_senha(): void
    {
        $usuario = User::factory()->create(['name' => 'Andrea cadastrada', 'email' => 'deiav28@gmail.com', 'perfil' => User::PERFIL_ADMIN]);
        $senha = $usuario->password;
        $this->seed(UsersSeeder::class);
        $this->assertDatabaseCount('users', 31);
        $this->assertSame(User::PERFIL_ADMIN, $usuario->fresh()->perfil);
        $this->assertSame('Andrea cadastrada', $usuario->fresh()->name);
        $this->assertSame($senha, $usuario->fresh()->password);
        $this->assertNull($usuario->fresh()->contato);
        $this->assertNull($usuario->fresh()->organizacao);
    }

    public function test_login_aceita_email_ou_contato_formatado_e_recusa_duplicados_e_inativos(): void
    {
        $usuario = User::factory()->create(['email' => 'avaliador@example.test', 'contato' => '(42) 99999-1234', 'password' => 'Senha@Teste12', 'ativo' => true]);
        $this->postJson('/api/auth/login', ['email' => 'AVALIADOR@example.test', 'senha' => 'Senha@Teste12'])->assertOk();
        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', ['email' => '42999991234', 'senha' => 'Senha@Teste12'])->assertOk();
        $this->postJson('/api/auth/logout');
        $usuario->update(['ativo' => false]);
        $this->postJson('/api/auth/login', ['email' => '(42) 99999-1234', 'senha' => 'Senha@Teste12'])->assertUnauthorized();
        $usuario->update(['ativo' => true]);
        User::factory()->create(['contato' => '42 99999-1234', 'ativo' => true]);
        $this->postJson('/api/auth/login', ['email' => '42999991234', 'senha' => 'Senha@Teste12'])->assertUnauthorized();
    }

    public function test_seed_desativa_fora_da_lista_e_preserva_id_e_notas(): void
    {
        $fora = User::factory()->create(['name' => 'Leticia Wolf', 'perfil' => User::PERFIL_AVALIADOR, 'ativo' => true]);
        $this->seed(UsersSeeder::class);
        $this->assertFalse($fora->fresh()->ativo);
        $this->assertSame($fora->id, $fora->fresh()->id);
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
