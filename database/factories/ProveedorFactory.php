<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        // RUT con dígito verificador válido (módulo 11), en el formato con que se guarda
        $cuerpo = (string) fake()->unique()->numberBetween(60000000, 99999999);

        return [
            'rut' => Rut::formatear($cuerpo.Rut::calcularDv($cuerpo)),
            'razon_social' => fake()->company(),
            'telefono' => fake()->numerify('+569########'),
            'email' => fake()->companyEmail(),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
