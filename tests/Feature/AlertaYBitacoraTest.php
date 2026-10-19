<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU06: alertas de punto de reorden (K3) y HU07: bitácora de movimientos. */
class AlertaYBitacoraTest extends TestCase
{
    use RefreshDatabase;

    private function recibir(Producto $producto, int $cantidad): void
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-'.$cantidad],
            [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'codigo_lote' => 'L'.$cantidad]],
        );
    }

    public function test_producto_en_o_bajo_el_punto_de_reorden_aparece_en_alertas(): void
    {
        $bajo = Producto::factory()->create(['nombre' => 'Huevos 12 un.', 'punto_reorden' => 6]);
        $ok = Producto::factory()->create(['nombre' => 'Arroz 1 kg', 'punto_reorden' => 6]);
        $this->recibir($bajo, 6);   // stock = punto de reorden -> alerta
        $this->recibir($ok, 20);

        $this->assertEqualsCanonicalizing([$bajo->id], Producto::bajoPuntoReorden()->pluck('id')->all());

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/alertas')->assertOk()->assertSee('Huevos 12 un.')->assertDontSee('Arroz 1 kg');
    }

    public function test_la_alerta_aparece_tras_una_venta_y_desaparece_al_reponer(): void
    {
        $producto = Producto::factory()->create(['punto_reorden' => 5]);
        $this->recibir($producto, 8);
        $this->assertSame(0, Producto::bajoPuntoReorden()->count());

        app(InventarioService::class)->registrarVenta(User::factory()->create(), [['producto_id' => $producto->id, 'cantidad' => 4]]);
        $this->assertSame(1, Producto::bajoPuntoReorden()->count());   // stock 4 <= 5

        $this->recibir($producto, 10);
        $this->assertSame(0, Producto::bajoPuntoReorden()->count());   // stock 14
    }

    public function test_productos_inactivos_o_sin_punto_de_reorden_no_generan_alerta(): void
    {
        Producto::factory()->create(['punto_reorden' => 5, 'activo' => false]);
        Producto::factory()->create(['punto_reorden' => 0]);

        $this->assertSame(0, Producto::bajoPuntoReorden()->count());
    }

    public function test_bitacora_registra_entradas_y_salidas_con_usuario(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Bebida cola 1,5 L']);
        $this->recibir($producto, 10);
        $vendedor = User::factory()->create(['name' => 'Persona de caja']);
        app(InventarioService::class)->registrarVenta($vendedor, [['producto_id' => $producto->id, 'cantidad' => 3]]);

        $this->actingAs(User::factory()->administrador()->create())
            ->get("/movimientos?producto_id={$producto->id}")
            ->assertOk()
            ->assertSee('ENTRADA')
            ->assertSee('SALIDA')
            ->assertSee('Persona de caja');
    }

    public function test_bodeguero_ve_alertas_pero_no_la_bitacora(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();

        $this->actingAs($bodeguero)->get('/alertas')->assertOk();
        $this->actingAs($bodeguero)->get('/movimientos')->assertForbidden();
    }
}
