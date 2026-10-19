<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        return [
            'rut' => fake()->unique()->numerify('7#.###.###-').fake()->randomDigit(),
            'razon_social' => fake()->company(),
            'telefono' => fake()->numerify('+569########'),
            'email' => fake()->companyEmail(),
            'activo' => true,
        ];
    }
}
