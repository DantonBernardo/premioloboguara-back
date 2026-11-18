<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Aceite = SIM na coluna H da lista final, consultada em 17/11/2026. */
class UsersSeeder extends Seeder
{
    private const AVALIADORES = [
        ['name' => 'Andrea Alves Vieira', 'email' => 'deiav28@gmail.com', 'contato' => '44 8406-2122', 'organizacao' => 'Paranavaí', 'cpf' => '00726984973'],
        ['name' => 'Renato Maçaneiro', 'email' => 'rmacaneiro@gmail.com.br', 'contato' => '41 9632-9305', 'organizacao' => 'Fomento Paraná', 'cpf' => '56736827949'],
        ['name' => 'Carlos Alberto Facco', 'email' => 'carlos.facco@ideashub.com.br', 'contato' => '44 988023127', 'organizacao' => 'Campo Mourão / HUB Ideas', 'cpf' => '62321307900'],
        ['name' => 'Gustavo Comeli', 'email' => 'gustavo.comeli@hubgovtechpr.org.br', 'contato' => '41 9739-5705', 'organizacao' => 'Hub Gov Tech', 'cpf' => '02421385946'],
        ['name' => 'Aline Malherdi', 'email' => 'alinemalherbi@escola.pr.gov.br', 'contato' => '42 9949-5038', 'organizacao' => 'Secretaria de Estado', 'cpf' => '07096469923'],
        ['name' => 'Eduardo Akira Azuma', 'email' => 'eaazuma@gmail.com', 'contato' => '44 9902-9228', 'organizacao' => 'Campo Mourão / Prefeitura de Campo Mourão', 'cpf' => '02619879922'],
        ['name' => 'Henrique Domakoski', 'email' => 'hedomakoski@curitiba.pr.gov.br', 'contato' => '419963-3187', 'organizacao' => 'Diretor Técnico da Agência de Inovação de Curitiba', 'cpf' => null],
        ['name' => 'Rosiclei Caldato Dalagnol', 'email' => 'secretaria@patobranco.tec.br', 'contato' => '46 9911-8824', 'organizacao' => 'Secretaria de Ciência Tecnologia e Inovação de Pato Branco', 'cpf' => '02501027906'],
        ['name' => 'Rafael Luz', 'email' => 'rafael.luz@guardiaoescolar.com', 'contato' => '21 99195-7062', 'organizacao' => 'Palestrante Educação', 'cpf' => '09496215793'],
        ['name' => 'Cesar Antonio Bitencourt', 'email' => 'cezar@bittsmart.com.br', 'contato' => '351 911 096 045', 'organizacao' => null, 'cpf' => '76422097972'],
        ['name' => 'Thiago Marcelino', 'email' => 'thiagomarcelino@inova.pr.gov.br', 'contato' => '41 9103-0045', 'organizacao' => 'Diretor de Inovação SEIA', 'cpf' => '03254904907'],
        ['name' => 'Marcos Pelegrina', 'email' => 'marcospelegrina@gmail.com', 'contato' => '48 9184-7787', 'organizacao' => 'SETI', 'cpf' => '02720021865'],
        ['name' => 'Bruna Nadal', 'email' => 'planejamento@irati.pr.gov.br', 'contato' => '42 98841-2230', 'organizacao' => 'Secretária de Planejamento e Inovação de Irati', 'cpf' => null],
        ['name' => 'Silvestre Labiak Junior', 'email' => 'slabiakjr@gmail.com', 'contato' => '41 9229-0007', 'organizacao' => 'UTFPR / Curitiba', 'cpf' => '74857240963'],
        ['name' => 'Shana Gonçalves de Oliveira', 'email' => 'shanaoliveira80@gmail.com', 'contato' => '41 9656-0325', 'organizacao' => 'UTFPR / Curitiba', 'cpf' => '02983046940'],
        ['name' => 'Mariana Carvalho', 'email' => 'adm.marianacarvalho@gmail.com', 'contato' => '45 9969-9859', 'organizacao' => 'Palestrante / Toledo', 'cpf' => '06400656944'],
        ['name' => 'Junior Baggio', 'email' => 'baggio@azapping.com.br', 'contato' => '46 9102-2556', 'organizacao' => 'Pato Branco', 'cpf' => '88162109900'],
        ['name' => 'Angelo Lenart Homen', 'email' => 'angelolenarte@gmail.com', 'contato' => '42 9143-3572', 'organizacao' => 'Itaú Unibanco, Cargo: Sr. Quantitative Analyst - Treasury', 'cpf' => '06218372960'],
        ['name' => 'Patricia Salvação', 'email' => 'pati.salvacao@gmail.com', 'contato' => '41 8775-9940', 'organizacao' => '(Doutora PucPR)', 'cpf' => '07475143996'],
        ['name' => 'Daniel Leipnitz', 'email' => 'danielleipnitz@gmail.com', 'contato' => '48 91326666', 'organizacao' => 'TedX', 'cpf' => '93223285920'],
        ['name' => 'Marina de Souza Bastos', 'email' => 'mbastos@pr.sebrae.com.br', 'contato' => '44 9145-8327', 'organizacao' => 'Consultora Sebrae PR', 'cpf' => '04403279970'],
        ['name' => 'Elizandro Ferreira (KAXA)', 'email' => 'eferreira@pr.sebrae.com.br', 'contato' => '46 8813-8773', 'organizacao' => 'Consultor Sebrae /pr', 'cpf' => null],
        ['name' => 'Fabiola Cristhine Costa', 'email' => 'fccosta@pr.sebrae.com.br', 'contato' => '42 9923-1232', 'organizacao' => 'Consultora Sebrae/pr', 'cpf' => '92593380934'],
        ['name' => 'Michael Douglas Camilo', 'email' => 'mcamilo@pr.sebrae.com.br', 'contato' => '41 9125-5229', 'organizacao' => 'Gerente Regional centro Sebrae/pr', 'cpf' => '08485164946'],
        ['name' => 'Leonel Rodrigues', 'email' => 'lerodrigues@pr.sebrae.com.br', 'contato' => '45 9916-9616', 'organizacao' => 'Consultor Sebrae/pr', 'cpf' => '00779935004'],
        ['name' => 'Elisangela Rosa', 'email' => 'erosa@pr.sebrae.com.br', 'contato' => '45 9155-2300', 'organizacao' => 'Coordenadora de Lideranças Sebrae/pr', 'cpf' => '78751888904'],
        ['name' => 'Thaís Cristina da Costa', 'email' => 'tcosta@pr.sebrae.com.br', 'contato' => null, 'organizacao' => 'Consultora Sebrae/pr', 'cpf' => '05795359912'],
        ['name' => 'Gustavo Oliveira Acosta', 'email' => 'gacosta@pr.sebrae.com.br', 'contato' => '53 8157-5229', 'organizacao' => 'Consultor Sebrae/pr', 'cpf' => '01997061074'],
        ['name' => 'Paulo Alvim', 'email' => 'pcrcalvim@gmail.com', 'contato' => '61 8302-0278', 'organizacao' => null, 'cpf' => '17937418168'],
        ['name' => 'Salette Silveira Azevedo', 'email' => 'salettesilveiraazevedo@gmail.com', 'contato' => '41 9893-5707', 'organizacao' => null, 'cpf' => '52247520944'],
    ];

    public function run(): void
    {
        $criados = DB::transaction(function (): int {
            $senhas = json_decode(file_get_contents(__DIR__.'/senhas-iniciais-avaliadores.json'), true, flags: JSON_THROW_ON_ERROR);
            $aceitos = [];
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
                $nomeAnterior = $dados['name'] === 'Angelo Lenart Homen' ? 'Angelo Lenart Homem' : $dados['name'];
                $usuario ??= User::where('perfil', User::PERFIL_AVALIADOR)->whereIn('name', [$dados['name'], $nomeAnterior])->first();

                if ($usuario === null) {
                    $criado = new User($dados + [
                        'perfil' => User::PERFIL_AVALIADOR,
                        'ativo' => true,
                    ]);
                    $criado->setRawAttributes($criado->getAttributes() + ['password' => $senhas[$dados['email']]]);
                    $criado->save();
                    $aceitos[] = $criado->id;
                    $total++;

                    continue;
                }

                $aceitos[] = $usuario->id;
                if ($usuario->perfil !== User::PERFIL_AVALIADOR) {
                    continue;
                }
                foreach (['email', 'contato', 'organizacao', 'cpf'] as $campo) {
                    if ($usuario->{$campo} !== $dados[$campo]) {
                        $usuario->{$campo} = $dados[$campo];
                    }
                }
                $usuario->ativo = true;
                if ($usuario->isDirty()) {
                    $usuario->save();
                }
            }

            User::where('perfil', User::PERFIL_AVALIADOR)->whereNotIn('id', $aceitos)->update(['ativo' => false]);

            return $total;
        });

        $this->command?->info($criados.' avaliadores criados; '.(count(self::AVALIADORES) - $criados).' cadastros existentes preservados.');
    }
}
