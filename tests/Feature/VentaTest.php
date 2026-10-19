<?php

namespace Tests\Feature;

use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU04: venta con descuento automático de stock por lote (K1, K5). */
class VentaTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private Producto $leche;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendedor = User::factory()->vendedor()->create();
        $this->leche = Producto::factory()->create(['precio_venta' => 1190, 'punto_reorden' => 5]);

        // Dos lotes: el que vence ANTES ingresa DESPUÉS, para probar que se ordena por vencimiento
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-1'],
            [
                ['producto_id' => $this->leche->id, 'cantidad' => 10, 'codigo_lote' => 'VENCE-TARDE', 'fecha_vencimiento' => now()->addDays(30)->toDateString()],
                ['producto_id' => $this->leche->id, 'cantidad' => 4, 'codigo_lote' => 'VENCE-PRONTO', 'fecha_vencimiento' => now()->addDays(3)->toDateString()],
            ],
        );
    }

    private function vender(int $cantidad, ?int $productoId = null)
    {
        return $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'efectivo',
            'items' => [['producto_id' => $productoId ?? $this->leche->id, 'cantidad' => $cantidad]],
        ]);
    }

    public function test_venta_descuenta_stock_en_tiempo_real(): void
    {
        $this->vender(3)->assertRedirect();

        $this->assertSame(11, $this->leche->stockActual());
        $this->assertDatabaseHas('ventas', ['total' => 3 * 1190, 'user_id' => $this->vendedor->id]);
    }

    public function test_descuenta_primero_el_lote_que_vence_antes_y_luego_el_siguiente(): void
    {
        $this->vender(6)->assertRedirect();

        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'VENCE-PRONTO', 'cantidad_disponible' => 0]);
        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'VENCE-TARDE', 'cantidad_disponible' => 8]);

        // Un movimiento SALIDA por cada lote consumido, con el stock resultante correcto
        $salidas = Movimiento::where('tipo', Movimiento::SALIDA)->orderBy('id')->get();
        $this->assertCount(2, $salidas);
        $this->assertSame([4, 2], $salidas->pluck('cantidad')->all());
        $this->assertSame([10, 8], $salidas->pluck('stock_resultante')->all());
    }

    public function test_stock_insuficiente_rechaza_la_venta_completa_sin_cambios(): void
    {
        $pan = Producto::factory()->create(); // sin stock

        $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'efectivo',
            'items' => [
                ['producto_id' => $this->leche->id, 'cantidad' => 2],   // esta línea sí alcanza…
                ['producto_id' => $pan->id, 'cantidad' => 1],           // …pero esta no
            ],
        ])->assertSessionHasErrors('items');

        // Atomicidad: no queda la venta a medias ni se descontó la leche
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('detalle_ventas', 0);
        $this->assertSame(14, $this->leche->stockActual());
        $this->assertSame(0, Movimiento::where('tipo', Movimiento::SALIDA)->count());
    }

    public function test_no_permite_vender_mas_que_el_stock_disponible(): void
    {
        $this->vender(15)->assertSessionHasErrors('items');

        $this->assertSame(14, $this->leche->stockActual());
    }

    public function test_el_precio_se_toma_de_la_base_de_datos_y_no_del_formulario(): void
    {
        $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'efectivo',
            'items' => [['producto_id' => $this->leche->id, 'cantidad' => 1, 'precio' => 1]],
        ]);

        $this->assertDatabaseHas('ventas', ['total' => 1190]);
    }

    public function test_mismo_producto_escaneado_dos_veces_se_agrupa_en_una_linea(): void
    {
        $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'debito',
            'items' => [
                ['producto_id' => $this->leche->id, 'cantidad' => 1],
                ['producto_id' => $this->leche->id, 'cantidad' => 2],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('detalle_ventas', 1);
        $this->assertDatabaseHas('detalle_ventas', ['cantidad' => 3, 'subtotal' => 3570]);
    }

    public function test_producto_inactivo_no_se_puede_vender(): void
    {
        $this->leche->update(['activo' => false]);

        $this->vender(1)->assertSessionHasErrors('items.0.producto_id');
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_detalle_de_venta_muestra_los_lotes_descontados(): void
    {
        $this->vender(5);
        $venta = \App\Models\Venta::firstOrFail();

        $this->actingAs($this->vendedor)->get("/ventas/{$venta->id}")
            ->assertOk()
            ->assertSee('VENCE-PRONTO')
            ->assertSee('VENCE-TARDE');
    }
}
