<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifica que los datos de demostración se cargan sin errores. */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_datos_de_demostracion_se_cargan_correctamente(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 3);
        $this->assertSame(22, Producto::where('codigo_barras', '7802900001002')->first()->stockActual());
        $this->assertGreaterThan(0, Producto::bajoPuntoReorden()->count());

        // HU09: los RUT de demostración tienen dígito verificador válido y hay un proveedor inactivo
        Proveedor::all()->each(fn (Proveedor $p) => $this->assertTrue(Rut::esValido($p->rut), "RUT inválido: {$p->rut}"));
        $this->assertSame(1, Proveedor::where('activo', false)->count());
    }
}
