<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Aplica a troca de senha do administrador somente quando chamada explicitamente. */
class AcessoAdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::where('email', 'scti@guarapuava.pr.gov.br')
                ->where('perfil', User::PERFIL_ADMIN)->sole();
            $admin->setRawAttributes(array_replace($admin->getAttributes(), [
                'password' => '$2y$12$n5tjdt/20vgcHDCNPOchteRMnfDxysDY346Iea4uzmSBshXvnPp8y',
            ]));
            $admin->remember_token = null;
            $admin->save();
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        });
        $this->command?->info('Senha do administrador atualizada e sessões anteriores encerradas.');
    }
}
