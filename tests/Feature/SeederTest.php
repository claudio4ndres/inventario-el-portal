<?php

namespace Tests\Feature;

use App\Models\Conteo;
use App\Models\Lote;
use App\Models\OrdenCompra;
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

        // HU12: hay lotes por vencer en 7 días y al menos uno ya vencido para la demostración
        $porVencer = Lote::porVencer(7)->get();
        $this->assertGreaterThan(1, $porVencer->count());
        $this->assertTrue($porVencer->contains(fn (Lote $l) => $l->estaVencido()));

        // HU13: hay una merma de ejemplo con su costo
        $this->assertDatabaseHas('movimientos', ['tipo' => 'MERMA', 'motivo_merma' => 'dano', 'cantidad' => 1, 'costo_total' => 1500]);

        // HU11: una OC sugerida por proveedor con productos en alerta; la del mayorista está enviada
        $this->assertSame(3, OrdenCompra::count());
        $enviada = OrdenCompra::where('estado', OrdenCompra::ENVIADA)->sole();
        $this->assertSame('Mayorista Abarrotes Central', $enviada->proveedor->razon_social);
        $this->assertEqualsCanonicalizing([7, 10], $enviada->detalles()->pluck('cantidad')->all());   // huevos 6×2−5, fideos 8×2−6
        $this->assertSame(2, OrdenCompra::where('estado', OrdenCompra::BORRADOR)->count());

        // HU14: conteo de la clase A con una diferencia ajustada (bebida 28 → 27)
        $conteo = Conteo::sole();
        $this->assertSame([3, 1], [$conteo->productos_contados, $conteo->productos_con_diferencia]);
        $this->assertSame(27, Producto::where('codigo_barras', '7801610001196')->first()->stockActual());
        $this->assertDatabaseHas('movimientos', ['tipo' => 'AJUSTE', 'cantidad' => 1, 'stock_resultante' => 27]);
    }
}
