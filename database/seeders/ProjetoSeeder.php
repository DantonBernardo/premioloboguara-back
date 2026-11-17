<?php

namespace Database\Seeders;

use App\Models\Projeto;
use App\Models\Subcategoria;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProjetoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CategoriaSeeder::class, UsersSeeder::class]);
        $fonte = json_decode(file_get_contents(__DIR__.'/projetos-reais.json'), true, flags: JSON_THROW_ON_ERROR);
        $normalizar = fn (string $nome): string => Str::lower(Str::ascii(trim($nome)));
        $usuarios = User::where('perfil', User::PERFIL_AVALIADOR)->get()->keyBy(fn (User $usuario): string => $normalizar($usuario->name));
        $grupos = [];
        foreach ($fonte['grupos'] as $grupo => $nomes) {
            if (count($nomes) > config('avaliacao.limite_avaliacoes')) {
                throw new RuntimeException('O grupo '.$grupo.' possui mais avaliadores que o limite de notas. Defina a composição antes de importar.');
            }
            $grupos[$grupo] = [];
            foreach ($nomes as $nome) {
                $chave = $normalizar($nome === 'Angelo Lenart Homem' ? 'Angelo Lenart Homen' : $nome);
                $usuario = $usuarios->get($chave);
                if ($usuario === null || ! $usuario->ativo) {
                    throw new RuntimeException('Avaliador não encontrado ou inativo: '.$nome);
                }
                $grupos[$grupo][] = $usuario->id;
            }
        }
        $preparados = collect($fonte['projetos'])->map(function (array $linha) use ($grupos): array {
            $subcategoria = Subcategoria::whereHas('categoria', fn ($query) => $query->where('codigo', $linha['categoria']))
                ->where('codigo', $linha['subcategoria'])->sole();

            return ['nome' => $linha['nome'], 'subcategoria_id' => $subcategoria->id, 'avaliador_ids' => $grupos[$linha['grupo']]];
        });
        DB::transaction(function () use ($preparados): void {
            foreach ($preparados as $dados) {
                $projeto = Projeto::firstOrNew(['nome' => $dados['nome'], 'subcategoria_id' => $dados['subcategoria_id']]);
                if (! $projeto->exists) {
                    $projeto->notas = [];
                }
                $avaliaram = array_map('intval', array_column($projeto->notas ?? [], 'avaliador_id'));
                if (array_diff($avaliaram, $dados['avaliador_ids']) !== []) {
                    throw new RuntimeException('A distribuição excluiria um avaliador com nota registrada no projeto '.$projeto->id.'.');
                }
                $projeto->avaliador_ids = $dados['avaliador_ids'];
                $projeto->save();
            }
        });
        $this->command?->info($preparados->count().' projetos reais importados; notas e códigos existentes preservados.');
    }
}
