<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Categoria> */
class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo' => fake()->unique()->numberBetween(100, 65000), 'nome' => fake()->unique()->sentence()];
    }
}
