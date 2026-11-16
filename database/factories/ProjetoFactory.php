<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\Subcategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projeto>
 */
class ProjetoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->sentence(),
            'subcategoria_id' => Subcategoria::whereHas('categoria', fn ($query) => $query->where('codigo', 3))->where('codigo', 1)->sole()->id,
            'notas' => [],
            'avaliador_ids' => [],
        ];
    }
}
