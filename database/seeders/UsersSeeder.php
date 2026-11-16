<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Aceite = Sim na planilha Controle de Avaliadores do Prêmio, consultada em 14/11/2026. */
class UsersSeeder extends Seeder
{
    private const AVALIADORES = [
        ['name' => 'Andrea Alves Vieira', 'email' => 'deiav28@gmail.com', 'contato' => '44 8406-2122', 'organizacao' => 'Paranavaí'],
        ['name' => 'Renato Maçaneiro', 'email' => null, 'contato' => '41 9632-9305', 'organizacao' => 'Fomento Paraná'],
        ['name' => 'Carlos Alberto Facco', 'email' => null, 'contato' => '21 97993-3740', 'organizacao' => 'Campo Mourão / HUB Ideas'],
        ['name' => 'Gustavo Comeli', 'email' => null, 'contato' => '41 9739-5705', 'organizacao' => 'Hub Gov Tech'],
        ['name' => 'Aline Malherdi', 'email' => null, 'contato' => '42 9949-5038', 'organizacao' => 'Secretaria de Estado'],
        ['name' => 'Eduardo Akira Azuma', 'email' => 'eaazuma@gmail.com', 'contato' => '44 9902-9228', 'organizacao' => 'Campo Mourão / Prefeitura de Campo Mourão'],
        ['name' => 'Leticia Wolf', 'email' => null, 'contato' => '41 9957-8358', 'organizacao' => 'Presidente da Agência de Inovação de Curitiba'],
        ['name' => 'Rosiclei Caldato Dalagnol', 'email' => null, 'contato' => '46 9911-8824', 'organizacao' => 'Secretaria de Ciência Tecnologia e Inovação de Pato Branco'],
        ['name' => 'Rafael Luz', 'email' => 'rafael.luz@guardiaoescolar.com', 'contato' => '21 99195-7062', 'organizacao' => 'Palestrante Educação'],
        ['name' => 'Cesar Antonio Bitencourt', 'email' => 'cezar@bittsmart.com.br', 'contato' => '351 911 096 045', 'organizacao' => null],
        ['name' => 'Thiago Marcelino', 'email' => null, 'contato' => '41 9103-0045', 'organizacao' => 'Diretor de Inovação SEIA'],
        ['name' => 'Marcos Pelegrina', 'email' => 'marcospelegrina@gmail.com', 'contato' => '48 9184-7787', 'organizacao' => 'SETI'],
        ['name' => 'Bruna Nadal', 'email' => 'planejamento@irati.pr.gov.br', 'contato' => '42 98841-2230', 'organizacao' => 'Secretária de Planejamento e Inovação de Irati'],
        ['name' => 'Junior Baggio', 'email' => 'baggio@azapping.com.br', 'contato' => '46 9102-2556', 'organizacao' => 'Pato Branco'],
        ['name' => 'Angelo Lenart Homem', 'email' => 'angelolenarte@gmail.com', 'contato' => '42 9143-3572', 'organizacao' => 'Caixa Econômica Federal Faria Lima'],
        ['name' => 'Patricia Salvação', 'email' => 'pati.salvacao@gmail.com', 'contato' => '41 8775-9940', 'organizacao' => '(Doutora PucPR)'],
        ['name' => 'Daniel Leipnitz', 'email' => 'danielleipnitz@gmail.com', 'contato' => '48 91326666', 'organizacao' => 'TedX'],
        ['name' => 'Elizandro Ferreira (KAXA)', 'email' => 'eferreira@pr.sebrae.com.br', 'contato' => '46 8813-8773', 'organizacao' => 'Consultor Sebrae /pr'],
        ['name' => 'Leonel Rodrigues', 'email' => 'lerodrigues@pr.sebrae.com.br', 'contato' => '45 9916-9616', 'organizacao' => 'Consultor Sebrae/pr'],
        ['name' => 'Elisangela Rosa', 'email' => 'erosa@pr.sebrae.com.br', 'contato' => '45 9155-2300', 'organizacao' => 'Coordenadora de Lideranças Sebrae/pr'],
        ['name' => 'Gustavo Oliveira Acosta', 'email' => 'gacosta@pr.sebrae.com.br', 'contato' => '53 8157-5229', 'organizacao' => 'Consultor Sebrae/pr'],
        ['name' => 'Paulo Alvim', 'email' => null, 'contato' => '61 8302-0278', 'organizacao' => null],
    ];

    public function run(): void
    {
        $criados = DB::transaction(function (): int {
            User::firstOrCreate(
                ['email' => 'scti@guarapuava.pr.gov.br'],
                [
                    'name' => 'SCTI',
                    'password' => 'senha123',
                    'perfil' => User::PERFIL_ADMIN,
                    'ativo' => true,
                    'organizacao' => 'Secretaria Municipal de Ciência, Tecnologia e Inovação',
                ],
            );

            $total = 0;
            foreach (self::AVALIADORES as $dados) {
                $usuario = $dados['email'] !== null ? User::where('email', $dados['email'])->first() : null;
                $usuario ??= User::where('name', $dados['name'])->where('contato', $dados['contato'])->first();

                if ($usuario === null) {
                    User::create($dados + [
                        'password' => 'senha123',
                        'perfil' => User::PERFIL_AVALIADOR,
                        'ativo' => true,
                    ]);
                    $total++;

                    continue;
                }

                foreach (['email', 'contato', 'organizacao'] as $campo) {
                    if ($usuario->{$campo} === null && $dados[$campo] !== null) {
                        $usuario->{$campo} = $dados[$campo];
                    }
                }
                if ($usuario->isDirty()) {
                    $usuario->save();
                }
            }

            return $total;
        });

        $this->command?->info($criados.' avaliadores criados; '.(count(self::AVALIADORES) - $criados).' cadastros existentes preservados.');
    }
}
