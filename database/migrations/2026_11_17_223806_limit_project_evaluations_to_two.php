<?php

use App\Models\Projeto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Projeto::with('subcategoria.categoria')->orderBy('id')->get() as $projeto) {
            $projeto->notas = array_slice($projeto->notas ?? [], 0, 2);
            $projeto->avaliador_ids = array_slice($projeto->avaliador_ids ?? [], 0, 2);
            $projeto->save();
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projetos DROP CONSTRAINT IF EXISTS projetos_notas_max_tres');
            DB::statement("ALTER TABLE projetos ADD CONSTRAINT projetos_notas_max_duas CHECK (CASE WHEN jsonb_typeof(notas) = 'array' THEN jsonb_array_length(notas) <= 2 ELSE false END)");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['insert', 'update'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS projetos_notas_max_tres_'.$evento);
                DB::unprepared("CREATE TRIGGER projetos_notas_max_duas_{$evento} BEFORE {$evento} ON projetos WHEN json_type(NEW.notas) != 'array' OR json_array_length(NEW.notas) > 2 BEGIN SELECT RAISE(ABORT, 'projetos.notas deve conter no máximo duas avaliações'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE projetos DROP CONSTRAINT IF EXISTS projetos_notas_max_duas');
            DB::statement("ALTER TABLE projetos ADD CONSTRAINT projetos_notas_max_tres CHECK (CASE WHEN jsonb_typeof(notas) = 'array' THEN jsonb_array_length(notas) <= 3 ELSE false END)");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['insert', 'update'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS projetos_notas_max_duas_'.$evento);
                DB::unprepared("CREATE TRIGGER projetos_notas_max_tres_{$evento} BEFORE {$evento} ON projetos WHEN json_type(NEW.notas) != 'array' OR json_array_length(NEW.notas) > 3 BEGIN SELECT RAISE(ABORT, 'projetos.notas deve conter no máximo três avaliações'); END");
            }
        }
    }
};
