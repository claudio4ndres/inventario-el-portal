<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'codigo_barras' => (string) fake()->unique()->numerify('780#########'),
            'nombre' => ucfirst(fake()->words(2, true)),
            'precio_venta' => fake()->numberBetween(300, 5000),
            'punto_reorden' => 5,
            'clase_abc' => 'C',
            'activo' => true,
        ];
    }
}
