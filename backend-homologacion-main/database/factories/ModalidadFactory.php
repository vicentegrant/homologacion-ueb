<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ModalidadFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => fake()->unique()->words(3, true), 'activa' => true];
    }
}
