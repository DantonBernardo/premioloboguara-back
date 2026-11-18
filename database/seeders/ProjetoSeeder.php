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
        $normalizar = fn (string $nome): string => Str::lower(Str::ascii(trim(str_replace('Angelo Lenart Homen', 'Angelo Lenart Homem', $nome))));
        $usuarios = User::where('perfil', User::PERFIL_AVALIADOR)->get()->keyBy(fn (User $usuario): string => $normalizar($usuario->name));
        $grupos = [];
        foreach ($fonte['grupos'] as $grupo => $nomes) {
            if (count($nomes) !== 2) {
                throw new RuntimeException('O grupo '.$grupo.' deve conter uma dupla de avaliadores.');
            }
            if (count($nomes) > config('avaliacao.limite_avaliacoes')) {
                throw new RuntimeException('O grupo '.$grupo.' possui mais avaliadores que o limite de notas. Defina a composição antes de importar.');
            }
            $grupos[$grupo] = [];
            foreach ($nomes as $nome) {
                $chave = $normalizar($nome);
                $usuario = $usuarios->get($chave);
                if ($usuario === null || ! $usuario->ativo) {
                    throw new RuntimeException('Avaliador não encontrado ou inativo: '.$nome);
                }
                $grupos[$grupo][] = $usuario->id;
            }
            if (count(array_unique($grupos[$grupo])) !== 2) {
                throw new RuntimeException('O grupo '.$grupo.' deve conter dois avaliadores diferentes.');
            }
        }
        $preparados = collect($fonte['projetos'])->map(function (array $linha) use ($grupos, $fonte): array {
            $subcategoria = Subcategoria::whereHas('categoria', fn ($query) => $query->where('codigo', $linha['categoria']))
                ->where('codigo', $linha['subcategoria'])->sole();

            $especialista = collect($fonte['grupos'][$linha['grupo']])->contains(
                fn (string $nome): bool => ($fonte['areas_avaliadores'][$nome] ?? null) === $linha['categoria'],
            );
            if (! $especialista) {
                throw new RuntimeException('O projeto '.$linha['nome'].' precisa de pelo menos um avaliador da área da categoria.');
            }

            $documentoUrl = $linha['documento_url'] ?? null;
            if (! is_string($documentoUrl) || ! preg_match('~^https://drive\.google\.com/file/d/[a-zA-Z0-9_-]+/view$~', $documentoUrl)) {
                throw new RuntimeException('Documento do projeto ausente ou inválido: '.$linha['nome']);
            }

            return ['nome' => $linha['nome'], 'subcategoria_id' => $subcategoria->id, 'avaliador_ids' => $grupos[$linha['grupo']], 'documento_url' => $documentoUrl];
        });
        $designados = $preparados->pluck('avaliador_ids')->flatten()->unique()->all();
        $semProjetos = $usuarios->filter(fn (User $usuario): bool => $usuario->ativo && ! in_array($usuario->id, $designados, true));
        if ($semProjetos->isNotEmpty()) {
            throw new RuntimeException('A distribuição deixou avaliadores ativos sem projetos: '.$semProjetos->pluck('name')->implode(', '));
        }
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
                $projeto->documento_url = $dados['documento_url'];
                if ($projeto->exists) {
                    Projeto::withoutTimestamps(fn (): bool => $projeto->save());
                } else {
                    $projeto->save();
                }
            }
        });
        $this->command?->info($preparados->count().' projetos reais importados; notas e códigos existentes preservados.');
    }
}
