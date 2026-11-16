<?php

use Database\Seeders\CategoriaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $catalogo = config('categorias');
        foreach (DB::table('projetos')->orderBy('id')->cursor() as $projeto) {
            $categoria = collect($catalogo)->firstWhere('nome', $projeto->categoria);
            if ($categoria === null || ! in_array($projeto->subcategoria, $categoria['subcategorias'], true)) {
                throw new RuntimeException('O projeto '.$projeto->id.' possui categoria/subcategoria não reconhecida. Corrija a classificação antes de migrar.');
            }
        }

        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('codigo')->unique();
            $table->string('nome')->unique();
            $table->timestamps();
        });
        Schema::create('subcategorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('categoria_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('codigo');
            $table->string('nome');
            $table->unique(['categoria_id', 'codigo']);
            $table->unique(['categoria_id', 'nome']);
            $table->timestamps();
        });
        (new CategoriaSeeder)->run();

        Schema::table('projetos', function (Blueprint $table): void {
            $table->foreignId('subcategoria_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('chave', 8)->nullable()->unique();
            $table->string('codigo', 40)->nullable()->unique();
        });
        foreach (DB::table('projetos')->orderBy('id')->cursor() as $projeto) {
            $subcategoria = DB::table('subcategorias')
                ->join('categorias', 'categorias.id', '=', 'subcategorias.categoria_id')
                ->where('categorias.nome', $projeto->categoria)
                ->where('subcategorias.nome', $projeto->subcategoria)
                ->select('subcategorias.id', 'subcategorias.codigo', 'categorias.codigo as categoria_codigo')
                ->first();
            do {
                $chave = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            } while (DB::table('projetos')->where('chave', $chave)->exists());
            DB::table('projetos')->where('id', $projeto->id)->update([
                'subcategoria_id' => $subcategoria->id,
                'chave' => $chave,
                'codigo' => $chave.'/'.$subcategoria->categoria_codigo.'/'.$subcategoria->codigo,
            ]);
        }
        Schema::table('projetos', function (Blueprint $table): void {
            $table->unsignedBigInteger('subcategoria_id')->nullable(false)->change();
            $table->string('chave', 8)->nullable(false)->change();
            $table->string('codigo', 40)->nullable(false)->change();
            $table->dropColumn(['categoria', 'subcategoria']);
        });
        $this->restaurarLimiteSqlite();
    }

    public function down(): void
    {
        Schema::table('projetos', function (Blueprint $table): void {
            $table->string('categoria', 100)->nullable();
            $table->string('subcategoria', 100)->nullable();
        });
        foreach (DB::table('projetos')->orderBy('id')->cursor() as $projeto) {
            $subcategoria = DB::table('subcategorias')
                ->join('categorias', 'categorias.id', '=', 'subcategorias.categoria_id')
                ->where('subcategorias.id', $projeto->subcategoria_id)
                ->select('subcategorias.nome', 'categorias.nome as categoria_nome')->first();
            DB::table('projetos')->where('id', $projeto->id)->update([
                'categoria' => $subcategoria->categoria_nome, 'subcategoria' => $subcategoria->nome,
            ]);
        }
        Schema::table('projetos', function (Blueprint $table): void {
            $table->string('categoria', 100)->nullable(false)->change();
            $table->dropForeign(['subcategoria_id']);
            $table->dropUnique(['chave']);
            $table->dropUnique(['codigo']);
            $table->dropColumn(['subcategoria_id', 'chave', 'codigo']);
        });
        Schema::dropIfExists('subcategorias');
        Schema::dropIfExists('categorias');
        $this->restaurarLimiteSqlite();
    }

    private function restaurarLimiteSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        foreach (['INSERT', 'UPDATE'] as $evento) {
            $nome = 'projetos_notas_max_tres_'.strtolower($evento);
            DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
            DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$evento} ON projetos WHEN json_type(NEW.notas) != 'array' OR json_array_length(NEW.notas) > 3 BEGIN SELECT RAISE(ABORT, 'projetos.notas deve ser um array de no máximo três avaliações'); END");
        }
    }
};
