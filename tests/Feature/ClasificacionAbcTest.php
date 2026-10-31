<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\ClasificacionAbcService;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU17: clasificación ABC automática por valor vendido en los últimos 90 días (RF8, K6/K7, OE4). */
class ClasificacionAbcTest extends TestCase
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

    /** Producto de $1.000 con stock y las unidades vendidas indicadas (valor = unidades × 1.000). */
    private function productoConVentas(int $unidades, string $claseInicial = 'C', int $haceDias = 1): Producto
    {
        $producto = Producto::factory()->create(['precio_venta' => 1000, 'clase_abc' => $claseInicial]);
        if ($unidades > 0) {
            $this->recibir($producto, $unidades);
            $this->vender($producto, $unidades, $haceDias);
        }

        return $producto;
    }

    /**
     * Catálogo de ejemplo: ventas por $50.000, $30.000, $10.000, $6.000 y $4.000 (total $100.000)
     * y un producto sin ventas. Acumulados previos: 0 %, 50 %, 80 %, 90 % y 96 %.
     *
     * @return array<int, Producto>
     */
    private function catalogoDeEjemplo(): array
    {
        return [
            $this->productoConVentas(50, 'C'),
            $this->productoConVentas(30, 'C'),
            $this->productoConVentas(10, 'A'),
            $this->productoConVentas(6, 'A'),
            $this->productoConVentas(4, 'A'),
            $this->productoConVentas(0, 'A'),
        ];
    }

    public function test_clasifica_por_valor_vendido_a_hasta_80_b_hasta_95_y_c_el_resto(): void
    {
        $productos = $this->catalogoDeEjemplo();

        $resumen = app(ClasificacionAbcService::class)->recalcular();

        $this->assertSame(['A' => 2, 'B' => 2, 'C' => 2], $resumen);
        $this->assertSame(
            ['A', 'A', 'B', 'B', 'C', 'C'],
            array_map(fn (Producto $p) => $p->fresh()->clase_abc, $productos),
        );
    }

    public function test_se_ordena_por_valor_vendido_y_no_por_unidades(): void
    {
        $caro = Producto::factory()->create(['precio_venta' => 5000, 'clase_abc' => 'C']);
        $barato = Producto::factory()->create(['precio_venta' => 500, 'clase_abc' => 'A']);
        $this->recibir($caro, 20);
        $this->recibir($barato, 50);
        $this->vender($caro, 20);     // $100.000
        $this->vender($barato, 50);   // $25.000: más unidades, menos valor

        app(ClasificacionAbcService::class)->recalcular();

        $this->assertSame('A', $caro->fresh()->clase_abc);
        $this->assertSame('B', $barato->fresh()->clase_abc);   // empieza en el 80 % acumulado
    }

    public function test_solo_cuentan_las_ventas_de_los_ultimos_90_dias(): void
    {
        $reciente = $this->productoConVentas(10, 'C', 89);
        $antiguo = $this->productoConVentas(100, 'A', 91);   // fuera del período: no cuenta

        app(ClasificacionAbcService::class)->recalcular();

        $this->assertSame('A', $reciente->fresh()->clase_abc);
        $this->assertSame('C', $antiguo->fresh()->clase_abc);
    }

    public function test_sin_ventas_en_el_periodo_no_se_modifica_la_clasificacion(): void
    {
        $producto = $this->productoConVentas(10, 'A', 120);

        $this->assertNull(app(ClasificacionAbcService::class)->recalcular());

        $this->assertSame('A', $producto->fresh()->clase_abc);
    }

    public function test_el_comando_de_artisan_recalcula_la_clasificacion(): void
    {
        $productos = $this->catalogoDeEjemplo();

        $this->artisan('inventario:recalcular-abc')
            ->expectsOutputToContain('2 producto(s) en A, 2 en B y 2 en C')
            ->assertExitCode(0);

        $this->assertSame('A', $productos[1]->fresh()->clase_abc);
        $this->assertSame('C', $productos[4]->fresh()->clase_abc);
    }

    public function test_el_comando_sin_ventas_avisa_que_no_hubo_cambios(): void
    {
        Producto::factory()->create(['clase_abc' => 'B']);

        $this->artisan('inventario:recalcular-abc')
            ->expectsOutputToContain('no se modificó')
            ->assertExitCode(0);

        $this->assertSame('B', Producto::first()->clase_abc);
    }

    public function test_administrador_recalcula_con_el_boton(): void
    {
        $productos = $this->catalogoDeEjemplo();
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/productos')->assertOk()->assertSee('Recalcular ABC');

        $this->actingAs($admin)
            ->from('/productos')
            ->post('/productos/recalcular-abc')
            ->assertRedirect('/productos')
            ->assertSessionHas('ok', fn (string $mensaje) => str_contains($mensaje, '2 en A, 2 en B y 2 en C'));

        $this->assertSame('A', $productos[0]->fresh()->clase_abc);
        $this->assertSame('B', $productos[2]->fresh()->clase_abc);
    }

    public function test_sin_ventas_el_boton_informa_que_no_hubo_cambios(): void
    {
        Producto::factory()->create(['clase_abc' => 'A']);

        $this->actingAs(User::factory()->administrador()->create())
            ->from('/productos')
            ->post('/productos/recalcular-abc')
            ->assertRedirect('/productos')
            ->assertSessionHas('ok', fn (string $mensaje) => str_contains($mensaje, 'no se modificó'));

        $this->assertSame('A', Producto::first()->clase_abc);
    }

    public function test_solo_el_administrador_puede_recalcular_la_clasificacion(): void
    {
        $this->actingAs(User::factory()->bodeguero()->create())->post('/productos/recalcular-abc')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/productos/recalcular-abc')->assertForbidden();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/productos')->assertDontSee('Recalcular ABC');
    }
}
