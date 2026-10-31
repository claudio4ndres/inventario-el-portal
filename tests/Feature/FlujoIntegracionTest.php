<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Services\IndicadorService;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Pruebas de integración del MPV (issue #27, línea base LB-4).
 *
 * A diferencia de las pruebas por historia, cada prueba recorre un flujo completo del almacén
 * a través de las pantallas (peticiones HTTP), con los tres roles, y termina verificando la
 * cuadratura: el stock de cada producto es igual a la suma de sus movimientos en la bitácora.
 */
class FlujoIntegracionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bodeguero;

    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrador()->create();
        $this->bodeguero = User::factory()->bodeguero()->create();
        $this->vendedor = User::factory()->vendedor()->create();
    }

    /**
     * Abastecimiento: HU16 → HU03 → HU06 → HU11 → HU03 contra OC → HU07.
     */
    public function test_flujo_de_abastecimiento_desde_el_catalogo_hasta_la_recepcion_contra_la_orden_de_compra(): void
    {
        // 1. HU16: el administrador carga el catálogo inicial desde CSV
        $csv = UploadedFile::fake()->createWithContent('catalogo.csv',
            "codigo_barras;nombre;precio_venta;punto_reorden;clase_abc\n"
            ."7802900001002;Leche entera 1 L;1190;12;A\n"
            ."7801234000017;Arroz grado 1 1 kg;1490;8;B\n");
        $this->actingAs($this->admin)->post('/productos/importar/vista-previa', ['archivo' => $csv])
            ->assertOk()->assertViewHas('totalValidas', 2);
        $this->actingAs($this->admin)->post('/productos/importar')->assertRedirect('/productos');

        $leche = Producto::where('codigo_barras', '7802900001002')->firstOrFail();
        $proveedor = Proveedor::factory()->create(['razon_social' => 'Distribuidora Lácteos Sur']);

        // 2. HU03: el bodeguero recibe 5 unidades (queda bajo el punto de reorden de 12)
        $this->actingAs($this->bodeguero)->post('/recepciones', [
            'proveedor_id' => $proveedor->id,
            'numero_documento' => 'F-1001',
            'lineas' => [[
                'producto_id' => $leche->id, 'cantidad' => 5, 'codigo_lote' => 'L-INICIAL',
                'fecha_vencimiento' => today()->addDays(20)->toDateString(), 'costo_unitario' => 800,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(5, $this->stock($leche));

        // 3. HU06: la leche aparece en las alertas de reposición
        $this->actingAs($this->admin)->get('/alertas')->assertOk()->assertSee('Leche entera 1 L');

        // 4. HU11: se genera la OC sugerida al último proveedor (12 × 2 − 5 = 19) y se envía
        $this->actingAs($this->admin)->post('/ordenes-compra/sugerir')
            ->assertSessionHasNoErrors()->assertRedirect('/ordenes-compra');
        $orden = OrdenCompra::with('detalles')->sole();
        $this->assertSame(OrdenCompra::BORRADOR, $orden->estado);
        $this->assertSame($proveedor->id, $orden->proveedor_id);
        $this->assertSame(19, $orden->detalles->sole()->cantidad);

        $this->actingAs($this->admin)->patch("/ordenes-compra/{$orden->id}/enviar")->assertSessionHasNoErrors();
        $this->assertSame(OrdenCompra::ENVIADA, $orden->fresh()->estado);

        // 5. HU03 + HU11: el bodeguero recibe la mercadería contra la OC
        $this->actingAs($this->bodeguero)->post('/recepciones', [
            'proveedor_id' => $proveedor->id,
            'orden_compra_id' => $orden->id,
            'numero_documento' => 'F-1002',
            'lineas' => [[
                'producto_id' => $leche->id, 'cantidad' => 19, 'codigo_lote' => 'L-OC',
                'fecha_vencimiento' => today()->addDays(40)->toDateString(), 'costo_unitario' => 800,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(OrdenCompra::RECIBIDA, $orden->fresh()->estado);
        $this->assertSame(24, $this->stock($leche));

        // 6. La alerta desaparece y la bitácora (HU07) registra las dos entradas
        $this->actingAs($this->admin)->get('/alertas')->assertOk()->assertDontSee('Leche entera 1 L');
        $entradas = Movimiento::where('producto_id', $leche->id)->where('tipo', Movimiento::ENTRADA)->orderBy('id')->get();
        $this->assertSame([5, 19], $entradas->pluck('cantidad')->all());
        $this->assertSame(24, $entradas->last()->stock_resultante);

        $this->assertCuadratura();
    }

    /**
     * Venta FEFO: HU04 descuenta primero el lote que vence antes y rechaza sin cambios
     * una venta que no alcanza.
     */
    public function test_flujo_de_venta_descuenta_por_fefo_y_rechaza_la_venta_sin_stock_sin_dejar_rastros(): void
    {
        $yogur = Producto::factory()->create(['nombre' => 'Yogur natural', 'precio_venta' => 600, 'clase_abc' => 'A']);
        $proveedor = Proveedor::factory()->create();

        // El lote que vence después se recibe PRIMERO, para comprobar que el orden es por vencimiento y no por llegada
        $this->recibir($yogur, $proveedor, 10, 'L-TARDE', 30);
        $this->recibir($yogur, $proveedor, 3, 'L-PRONTO', 5);

        $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'efectivo',
            'items' => [['producto_id' => $yogur->id, 'cantidad' => 5]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(0, $this->lote('L-PRONTO')->cantidad_disponible);   // se agotó primero
        $this->assertSame(8, $this->lote('L-TARDE')->cantidad_disponible);
        $venta = Venta::with('detalles')->sole();
        $this->assertSame(3000, $venta->total);
        $this->assertSame(5, (int) Movimiento::where('detalle_venta_id', $venta->detalles->sole()->id)->sum('cantidad'));

        // Una venta mayor al stock (8) se rechaza completa: ni venta, ni movimientos, ni lotes tocados
        $movimientosAntes = Movimiento::count();
        $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'debito',
            'items' => [['producto_id' => $yogur->id, 'cantidad' => 9]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(1, Venta::count());
        $this->assertSame($movimientosAntes, Movimiento::count());
        $this->assertSame(8, $this->stock($yogur));

        // M1.1: la única venta registrada descontó por lote automáticamente
        $m11 = app(IndicadorService::class)->calcular()['m11'];
        $this->assertSame(['ventas' => 1, 'con_descuento' => 1, 'porcentaje' => 100.0], $m11);

        $this->assertCuadratura();
    }

    /**
     * Control de pérdidas: HU12 → HU13 → HU14 → HU15.
     */
    public function test_flujo_de_control_de_perdidas_se_refleja_en_los_indicadores(): void
    {
        $queso = Producto::factory()->create(['nombre' => 'Queso gauda 250 g', 'precio_venta' => 2500, 'clase_abc' => 'A']);
        $pan = Producto::factory()->create(['nombre' => 'Pan molde', 'precio_venta' => 1800, 'clase_abc' => 'A']);
        $proveedor = Proveedor::factory()->create();
        $this->recibir($queso, $proveedor, 6, 'Q-VENCE', 3, 1500);
        $this->recibir($queso, $proveedor, 10, 'Q-NUEVO', 60, 1500);
        $this->recibir($pan, $proveedor, 4, 'P-1', 10, 1000);

        // HU12: el bodeguero ve el lote que vence dentro de 7 días y no el de 60
        $this->actingAs($this->bodeguero)->get('/lotes/por-vencer')
            ->assertOk()->assertSee('Q-VENCE')->assertDontSee('Q-NUEVO');

        // HU13: retira el lote por vencimiento; la merma queda valorizada a costo (6 × 1.500)
        $this->actingAs($this->bodeguero)->post('/mermas', [
            'lote_id' => $this->lote('Q-VENCE')->id,
            'cantidad' => 6,
            'motivo_merma' => 'vencimiento',
        ])->assertSessionHasNoErrors()->assertRedirect('/mermas');
        $this->assertSame(9000, (int) Movimiento::where('tipo', Movimiento::MERMA)->sum('costo_total'));

        // Ventas del período: 2 quesos (5.000) y todo el pan (quiebre de un producto clase A)
        $this->vender($queso, 2);
        $this->vender($pan, 4);

        // HU14: conteo de la clase A; falta 1 queso (sistema 8, contado 7) y el pan cuadra
        $this->actingAs($this->bodeguero)->post('/conteos', [
            'clase_abc' => 'A',
            'conteos' => [
                $queso->id => ['cantidad' => 7, 'motivo' => 'Unidad rota en la vitrina'],
                $pan->id => ['cantidad' => 0],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(7, $this->stock($queso));

        // HU15: los cuatro indicadores reflejan lo ocurrido
        $indicadores = app(IndicadorService::class)->calcular();
        $this->assertSame(2, $indicadores['m11']['ventas']);
        $this->assertSame(50.0, $indicadores['m12']['exactitud']);         // 1 de 2 productos con diferencia
        $this->assertSame(1, $indicadores['m2']['total']);                  // el pan llegó a 0
        $this->assertSame(['merma' => 9000, 'ventas' => 12200, 'porcentaje' => 73.8], $indicadores['m3']);

        $this->actingAs($this->admin)->get('/indicadores')->assertOk()->assertSee('Pan molde');

        $this->assertCuadratura();
    }

    /**
     * HU10 + HU17: los comandos programados recalculan el punto de reorden y la clase ABC,
     * y las alertas y la programación de conteos usan los valores nuevos.
     */
    public function test_los_recalculos_automaticos_alimentan_las_alertas_y_los_conteos(): void
    {
        $cafe = Producto::factory()->create([
            'nombre' => 'Café molido 250 g', 'precio_venta' => 1000, 'clase_abc' => 'C',
            'punto_reorden' => 1, 'dias_reposicion' => 7, 'stock_seguridad' => 2,
        ]);
        $fosforos = Producto::factory()->create(['nombre' => 'Fósforos', 'clase_abc' => 'A']);
        $this->recibir($cafe, Proveedor::factory()->create(), 14, 'C-1', 90);
        $this->vender($cafe, 10);   // quedan 4

        // Con punto de reorden 1 todavía no hay alerta
        $this->actingAs($this->admin)->get('/alertas')->assertOk()->assertDontSee('Café molido 250 g');

        $this->artisan('inventario:recalcular-reorden')->assertSuccessful();
        $this->artisan('inventario:recalcular-abc')->assertSuccessful();

        // ceil(10 vendidas × 7 días ÷ 28) + 2 de seguridad = 5
        $this->assertSame(5, $cafe->fresh()->punto_reorden);
        $this->assertSame('A', $cafe->fresh()->clase_abc);      // concentra todo el valor vendido
        $this->assertSame('C', $fosforos->fresh()->clase_abc);  // sin ventas en 90 días

        // Stock 4 ≤ punto de reorden 5: ahora sí aparece en las alertas…
        $this->actingAs($this->admin)->get('/alertas')->assertOk()->assertSee('Café molido 250 g');
        // …y entra en el conteo semanal de la clase A
        $this->actingAs($this->bodeguero)->get('/conteos/crear?clase=A')
            ->assertOk()->assertSee('Café molido 250 g')->assertDontSee('Fósforos');

        $this->assertCuadratura();
    }

    // ---------------------------------------------------------------- utilidades

    private function recibir(Producto $producto, Proveedor $proveedor, int $cantidad, string $lote, int $venceEnDias, int $costo = 500): void
    {
        $this->actingAs($this->bodeguero)->post('/recepciones', [
            'proveedor_id' => $proveedor->id,
            'numero_documento' => 'F-'.$lote,
            'lineas' => [[
                'producto_id' => $producto->id, 'cantidad' => $cantidad, 'codigo_lote' => $lote,
                'fecha_vencimiento' => today()->addDays($venceEnDias)->toDateString(), 'costo_unitario' => $costo,
            ]],
        ])->assertSessionHasNoErrors();
    }

    private function vender(Producto $producto, int $cantidad): void
    {
        $this->actingAs($this->vendedor)->post('/ventas', [
            'medio_pago' => 'efectivo',
            'items' => [['producto_id' => $producto->id, 'cantidad' => $cantidad]],
        ])->assertSessionHasNoErrors();
    }

    private function lote(string $codigo): Lote
    {
        return Lote::where('codigo_lote', $codigo)->sole();
    }

    private function stock(Producto $producto): int
    {
        return app(InventarioService::class)->stockDe($producto->id);
    }

    /**
     * Cuadratura global: para cada producto, el stock (Σ lotes) es igual a la suma con signo
     * de sus movimientos (entradas suman; ventas y mermas restan; ajustes según el conteo).
     */
    private function assertCuadratura(): void
    {
        foreach (Producto::all() as $producto) {
            $segunBitacora = Movimiento::with('detalleConteo')->where('producto_id', $producto->id)->get()
                ->sum(fn (Movimiento $m) => $m->signo() === '+' ? $m->cantidad : -$m->cantidad);

            $this->assertSame($this->stock($producto), (int) $segunBitacora,
                "Descuadre en «{$producto->nombre}»: los lotes no coinciden con la bitácora.");
            $this->assertGreaterThanOrEqual(0, $this->stock($producto));
        }
    }
}
