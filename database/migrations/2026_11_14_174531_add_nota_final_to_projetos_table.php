<?php

use App\Services\Avaliacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

return new class extends Migration
{
    public function up(): void
    {
        $avaliacao = app(Avaliacao::class);
        foreach (DB::table('projetos')->orderBy('id')->cursor() as $projeto) {
            try {
                $avaliacao->normalizar($projeto->categoria, json_decode($projeto->notas, true, 512, JSON_THROW_ON_ERROR));
            } catch (ValidationException $exception) {
                throw new RuntimeException('Corrija as avaliações existentes do projeto '.$projeto->id.' antes de migrar: '.json_encode($exception->errors(), JSON_UNESCAPED_UNICODE), previous: $exception);
            }
        }

        Schema::table('projetos', function (Blueprint $table): void {
            $table->decimal('nota_final', 5, 2)->nullable();
        });

        foreach (DB::table('projetos')->orderBy('id')->cursor() as $projeto) {
            $notas = $avaliacao->normalizar($projeto->categoria, json_decode($projeto->notas, true, 512, JSON_THROW_ON_ERROR));
            DB::table('projetos')->where('id', $projeto->id)->update([
                'notas' => json_encode($notas, JSON_THROW_ON_ERROR),
                'nota_final' => $avaliacao->media($notas),
            ]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE projetos ADD CONSTRAINT projetos_notas_max_tres CHECK (CASE WHEN jsonb_typeof(notas) = 'array' THEN jsonb_array_length(notas) <= 3 ELSE false END)");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $evento) {
                $nome = 'projetos_notas_max_tres_'.strtolower($evento);
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$evento} ON projetos WHEN json_type(NEW.notas) != 'array' OR json_array_length(NEW.notas) > 3 BEGIN SELECT RAISE(ABORT, 'projetos.notas deve ser um array de no máximo três avaliações'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projetos DROP CONSTRAINT projetos_notas_max_tres');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS projetos_notas_max_tres_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS projetos_notas_max_tres_update');
        }
        Schema::table('projetos', function (Blueprint $table): void {
            $table->dropColumn('nota_final');
        });
    }
};
