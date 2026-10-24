<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\PuntoReordenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU10: punto de reorden calculado a partir de la demanda (RF4, K3, OE2). */
class PuntoReordenTest extends TestCase
{
    use RefreshDatabase;

    private function recibir(Producto $producto, int $cantidad): void
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-'.$producto->id],
            [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'codigo_lote' => 'L-'.$producto->id]],
        );
    }

    /** Registra una venta real (HU04), opcionalmente hace N días. */
    private function vender(Producto $producto, int $cantidad, int $haceDias = 0): void
    {
        $this->travelTo(now()->subDays($haceDias));
        app(InventarioService::class)->registrarVenta(User::factory()->create(), [
            ['producto_id' => $producto->id, 'cantidad' => $cantidad],
        ]);
        $this->travelBack();
    }

    public function test_aplica_la_formula_demanda_diaria_por_dias_de_reposicion_mas_stock_de_seguridad(): void
    {
        $producto = Producto::factory()->create(['punto_reorden' => 0, 'dias_reposicion' => 4, 'stock_seguridad' => 3]);
        $this->recibir($producto, 100);
        $this->vender($producto, 30, 2);
        $this->vender($producto, 26, 20);   // 56 unidades en 28 días -> 2 por día

        $this->assertSame(1, app(PuntoReordenService::class)->recalcular());

        $this->assertSame(11, $producto->fresh()->punto_reorden);   // 2 × 4 + 3
    }

    public function test_solo_cuentan_las_ventas_de_los_ultimos_28_dias(): void
    {
        $producto = Producto::factory()->create(['dias_reposicion' => 7, 'stock_seguridad' => 0]);
        $this->recibir($producto, 200);
        $this->vender($producto, 28, 1);     // dentro del período: 1 por día
        $this->vender($producto, 100, 30);   // fuera del período: no cuenta

        app(PuntoReordenService::class)->recalcular();

        $this->assertSame(7, $producto->fresh()->punto_reorden);   // 1 × 7 + 0
    }

    public function test_la_demanda_fraccionaria_se_redondea_hacia_arriba(): void
    {
        $this->assertSame(2, PuntoReordenService::calcular(10, 3, 0));    // 10 ÷ 28 × 3 = 1,07 -> 2
        $this->assertSame(5, PuntoReordenService::calcular(28, 3, 2));    // exacto: 3 + 2
        $this->assertSame(4, PuntoReordenService::calcular(0, 7, 4));     // sin demanda: solo el stock de seguridad
    }

    public function test_productos_sin_ventas_en_el_periodo_no_se_recalculan(): void
    {
        $vendido = Producto::factory()->create(['punto_reorden' => 0, 'dias_reposicion' => 3, 'stock_seguridad' => 1]);
        $sinVentas = Producto::factory()->create(['punto_reorden' => 9]);
        $soloVentasAntiguas = Producto::factory()->create(['punto_reorden' => 6]);
        $this->recibir($vendido, 50);
        $this->recibir($soloVentasAntiguas, 50);
        $this->vender($vendido, 14);
        $this->vender($soloVentasAntiguas, 40, 45);

        $this->assertSame(1, app(PuntoReordenService::class)->recalcular());

        $this->assertSame(3, $vendido->fresh()->punto_reorden);              // ceil(14 × 3 ÷ 28) + 1 = 2 + 1
        $this->assertSame(9, $sinVentas->fresh()->punto_reorden);            // se conserva
        $this->assertSame(6, $soloVentasAntiguas->fresh()->punto_reorden);   // se conserva
    }

    public function test_el_comando_de_artisan_recalcula_el_punto_de_reorden(): void
    {
        $producto = Producto::factory()->create(['punto_reorden' => 0, 'dias_reposicion' => 2, 'stock_seguridad' => 5]);
        $this->recibir($producto, 60);
        $this->vender($producto, 42, 3);   // 1,5 por día

        $this->artisan('inventario:recalcular-reorden')
            ->expectsOutputToContain('recalculado en 1 producto')
            ->assertExitCode(0);

        $this->assertSame(8, $producto->fresh()->punto_reorden);   // 1,5 × 2 + 5
    }

    public function test_el_comando_queda_programado_en_el_planificador(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('inventario:recalcular-reorden')
            ->assertExitCode(0);
    }

    public function test_administrador_recalcula_con_el_boton(): void
    {
        $producto = Producto::factory()->create(['punto_reorden' => 0, 'dias_reposicion' => 7, 'stock_seguridad' => 2]);
        $this->recibir($producto, 60);
        $this->vender($producto, 56, 5);   // 2 por día
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/productos')->assertOk()->assertSee('Recalcular punto de reorden');

        $this->actingAs($admin)
            ->from('/productos')
            ->post('/productos/recalcular-reorden')
            ->assertRedirect('/productos')
            ->assertSessionHas('ok', fn (string $mensaje) => str_contains($mensaje, '1 producto'));

        $this->assertSame(16, $producto->fresh()->punto_reorden);   // 2 × 7 + 2
    }

    public function test_sin_ventas_el_boton_informa_que_no_hubo_cambios(): void
    {
        Producto::factory()->create(['punto_reorden' => 4]);

        $this->actingAs(User::factory()->administrador()->create())
            ->from('/productos')
            ->post('/productos/recalcular-reorden')
            ->assertRedirect('/productos')
            ->assertSessionHas('ok', fn (string $mensaje) => str_contains($mensaje, 'no se modificó'));

        $this->assertSame(4, Producto::first()->punto_reorden);
    }

    public function test_solo_el_administrador_puede_recalcular(): void
    {
        $this->actingAs(User::factory()->bodeguero()->create())->post('/productos/recalcular-reorden')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/productos/recalcular-reorden')->assertForbidden();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/productos')->assertDontSee('Recalcular punto de reorden');
    }

    public function test_dias_de_reposicion_y_stock_de_seguridad_son_campos_del_producto(): void
    {
        $admin = User::factory()->administrador()->create();
        $datos = [
            'codigo_barras' => '7802900001002', 'nombre' => 'Leche entera 1 L', 'precio_venta' => 1190,
            'punto_reorden' => 12, 'clase_abc' => 'A', 'activo' => '1',
        ];

        $this->actingAs($admin)->get('/productos/crear')
            ->assertSee('name="dias_reposicion"', false)
            ->assertSee('name="stock_seguridad"', false);

        $this->actingAs($admin)
            ->post('/productos', $datos + ['dias_reposicion' => 2, 'stock_seguridad' => 10])
            ->assertRedirect('/productos');
        $this->assertDatabaseHas('productos', ['codigo_barras' => '7802900001002', 'dias_reposicion' => 2, 'stock_seguridad' => 10]);

        $producto = Producto::where('codigo_barras', '7802900001002')->firstOrFail();
        $this->actingAs($admin)
            ->put("/productos/{$producto->id}", $datos + ['dias_reposicion' => 0, 'stock_seguridad' => -1])
            ->assertSessionHasErrors(['dias_reposicion', 'stock_seguridad']);
        $this->assertSame(2, $producto->fresh()->dias_reposicion);
    }
}
