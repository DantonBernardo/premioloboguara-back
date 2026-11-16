<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projetos', function (Blueprint $table) {
            // ID simples e sequencial (1, 2, 3...): é o número que o avaliador digita para abrir o projeto.
            $table->increments('id');
            $table->string('nome');
            $table->string('categoria', 100);
            $table->string('subcategoria', 100)->nullable();
            // Notas atribuídas, ex.: [{"avaliador_id": 2, "c1": 2.5, "c2": 3.0, "c3": 1.5, "c4": 2.0, "bonus": 0}]
            $table->jsonb('notas')->default('[]');
            // IDs dos avaliadores designados, ex.: [2, 5]
            $table->jsonb('avaliador_ids')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projetos');
    }
};
