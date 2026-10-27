<?php

namespace Tests\Feature;

use App\Models\Conteo;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Services\IndicadorService;
use App\Services\InventarioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU15: tablero de indicadores M1.1, M1.2, M2 y M3 (RF8, K7, OE4). */
class IndicadorTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-10-26 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::HOY));
    }

    /** Crea un lote con la recepción real (HU03) y devuelve el lote creado. */
    private function lote(Producto $producto, string $codigo, int $cantidad, int $costo = 500, ?string $vence = null): Lote
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-'.$codigo],
            [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'codigo_lote' => $codigo,
                'fecha_vencimiento' => $vence,
                'costo_unitario' => $costo,
            ]],
        );

        return Lote::where('codigo_lote', $codigo)->firstOrFail();
    }

    private function vender(Producto $producto, int $cantidad): Venta
    {
        return app(InventarioService::class)->registrarVenta(
            User::factory()->vendedor()->create(),
            [['producto_id' => $producto->id, 'cantidad' => $cantidad]],
        );
    }

    /** Venta registrada sin pasar por el servicio: no descuenta lotes ni deja movimientos SALIDA. */
    private function ventaSinDescuento(Producto $producto, int $cantidad): Venta
    {
        $venta = Venta::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'total' => $producto->precio_venta * $cantidad,
            'medio_pago' => 'efectivo',
            'fecha_venta' => now(),
        ]);
        DetalleVenta::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $producto->precio_venta,
            'subtotal' => $producto->precio_venta * $cantidad,
        ]);

        return $venta;
    }

    public function test_solo_el_administrador_accede_al_tablero(): void
    {
        $this->get('/indicadores')->assertRedirect('/login');
        $this->actingAs(User::factory()->vendedor()->create())->get('/indicadores')->assertForbidden();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/indicadores')->assertForbidden();

        $this->actingAs(User::factory()->administrador()->create())->get('/')
            ->assertOk()
            ->assertSee('href="'.route('indicadores.index').'"', false);
        $this->actingAs(User::factory()->bodeguero()->create())->get('/')
            ->assertDontSee('href="'.route('indicadores.index').'"', false);
    }

    public function test_el_tablero_muestra_los_cuatro_indicadores_calculados_al_momento(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/indicadores')
            ->assertOk()
            ->assertSeeInOrder([
                'M1.1 · Ventas con descuento automático',
                'M1.2 · Exactitud del inventario',
                'M2 · Quiebres de alta rotación',
                'M3 · Merma por vencimiento sobre ventas',
                'Quiebres de stock por semana',
            ])
            ->assertSee('Actualizado el 26-10-2026 10:00')
            ->assertSee('del 29-09-2026 a hoy')
            ->assertSee('Sin quiebres en el período.');

        // Sin caché: una venta registrada recién se refleja en la siguiente consulta
        $producto = Producto::factory()->create();
        $this->lote($producto, 'PR-01', 10);
        $this->travel(5)->minutes();
        $this->vender($producto, 1);

        $this->actingAs($admin)->get('/indicadores')
            ->assertSee('Actualizado el 26-10-2026 10:05')
            ->assertSee('1 de 1 venta(s)')
            ->assertSeeInOrder(['id="m11"', '100,0 %'], false);
    }

    public function test_m11_es_el_porcentaje_de_ventas_con_descuento_automatico_por_lote(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 1000]);
        $this->lote($producto, 'PR-01', 20);

        // Venta antigua (fuera de las 4 semanas): no cuenta
        $this->travelTo(Carbon::parse(self::HOY)->subDays(40));
        $this->ventaSinDescuento($producto, 1);
        $this->travelTo(Carbon::parse(self::HOY));

        $this->vender($producto, 2);
        $this->vender($producto, 3);
        $this->ventaSinDescuento($producto, 1);

        $m11 = app(IndicadorService::class)->calcular()['m11'];
        $this->assertSame(['ventas' => 3, 'con_descuento' => 2, 'porcentaje' => 66.7], $m11);

        $this->actingAs(User::factory()->administrador()->create())->get('/indicadores')
            ->assertSeeInOrder(['id="m11"', '66,7 %', '2 de 3 venta(s)'], false);
    }

    public function test_m12_es_la_exactitud_de_los_conteos_de_los_ultimos_30_dias(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $nuevo = fn (string $clase, int $dias, int $contados, int $conDiferencia) => Conteo::create([
            'clase_abc' => $clase,
            'user_id' => $bodeguero->id,
            'fecha_conteo' => now()->subDays($dias),
            'productos_contados' => $contados,
            'productos_con_diferencia' => $conDiferencia,
        ]);

        $nuevo('A', 2, 4, 1);
        $nuevo('C', 20, 6, 1);
        $nuevo('B', 40, 10, 10);   // fuera del período: no cuenta

        // (1 − 2 ÷ 10) × 100 = 80 %
        $this->assertSame(80.0, app(IndicadorService::class)->calcular()['m12']['exactitud']);

        $this->actingAs(User::factory()->administrador()->create())->get('/indicadores')
            ->assertSeeInOrder(['id="m12"', '80,0 %', 'últimos 30 días'], false);
    }

    public function test_m2_cuenta_los_quiebres_de_clase_a_por_semana(): void
    {
        $inventario = app(InventarioService::class);
        $leche = Producto::factory()->create(['nombre' => 'Leche entera 1 L', 'clase_abc' => 'A']);
        $huevos = Producto::factory()->create(['nombre' => 'Huevos 12 un.', 'clase_abc' => 'A']);
        $pan = Producto::factory()->create(['nombre' => 'Pan de molde', 'clase_abc' => 'B']);

        // Quiebre anterior al período (27-09): no cuenta
        $this->travelTo(Carbon::parse('2026-09-25 09:00'));
        $this->lote($leche, 'LE-01', 5);
        $this->travelTo(Carbon::parse('2026-09-27 09:00'));
        $this->vender($leche, 5);

        // Semana 1 (01-10): una venta que vacía dos lotes cuenta como un solo quiebre
        $this->travelTo(Carbon::parse('2026-09-30 09:00'));
        $this->lote($leche, 'LE-02', 2, 500, '2026-10-10');
        $this->lote($leche, 'LE-03', 3, 500, '2026-10-20');
        $this->travelTo(Carbon::parse('2026-10-01 09:00'));
        $this->vender($leche, 5);

        // Semana 2: un producto de clase B que queda en 0 no es de alta rotación
        $this->travelTo(Carbon::parse('2026-10-07 09:00'));
        $this->lote($pan, 'PM-01', 2);
        $this->vender($pan, 2);

        // Semana 4 (24-10): una merma que deja la leche en 0 también es un quiebre
        $this->travelTo(Carbon::parse('2026-10-20 09:00'));
        $loteMerma = $this->lote($leche, 'LE-04', 4);
        $this->lote($huevos, 'HU-01', 6);
        $this->travelTo(Carbon::parse('2026-10-24 09:00'));
        $inventario->registrarMerma(User::factory()->bodeguero()->create(), $loteMerma->id, 4, 'vencimiento');
        $this->vender($huevos, 2);   // queda stock: no es quiebre

        $this->travelTo(Carbon::parse(self::HOY));
        $m2 = app(IndicadorService::class)->calcular()['m2'];

        $this->assertSame(2, $m2['total']);
        $this->assertSame([1, 0, 0, 1], $m2['semanas']->pluck('quiebres')->all());
        $this->assertSame(['2026-09-29', '2026-10-06', '2026-10-13', '2026-10-20'], $m2['semanas']->map(fn ($s) => $s->desde->toDateString())->all());
        $this->assertSame([['Leche entera 1 L', 2]], $m2['productos']->map(fn ($p) => [$p->nombre, $p->quiebres])->all());

        $this->actingAs(User::factory()->administrador()->create())->get('/indicadores')
            ->assertSeeInOrder(['id="m2"', '2'], false)
            ->assertSeeInOrder(['data-quiebres="1"', 'data-quiebres="0"', 'data-quiebres="0"', 'data-quiebres="1"'], false)
            ->assertSeeInOrder(['29-09 al 05-10', '06-10 al 12-10', '13-10 al 19-10', '20-10 al 26-10'])
            ->assertSee('Leche entera 1 L')
            ->assertDontSee('Pan de molde');
    }

    public function test_m3_es_la_merma_por_vencimiento_a_costo_sobre_el_monto_vendido(): void
    {
        $inventario = app(InventarioService::class);
        $bodeguero = User::factory()->bodeguero()->create();
        $producto = Producto::factory()->create(['precio_venta' => 1000]);
        $lote = $this->lote($producto, 'YO-01', 30, 500);

        // Merma por vencimiento antigua (fuera del período): no cuenta
        $this->travelTo(Carbon::parse(self::HOY)->subDays(35));
        $inventario->registrarMerma($bodeguero, $lote->id, 5, 'vencimiento');
        $this->travelTo(Carbon::parse(self::HOY));

        $this->vender($producto, 10);                                   // $10.000 vendidos
        $inventario->registrarMerma($bodeguero, $lote->id, 2, 'vencimiento');   // 2 × 500 = $1.000
        $inventario->registrarMerma($bodeguero, $lote->id, 1, 'dano');          // otro motivo: no cuenta

        $m3 = app(IndicadorService::class)->calcular()['m3'];
        $this->assertSame(['merma' => 1000, 'ventas' => 10000, 'porcentaje' => 10.0], $m3);

        $this->actingAs(User::factory()->administrador()->create())->get('/indicadores')
            ->assertSeeInOrder(['id="m3"', '10,0 %', '$1.000 de merma (a costo) sobre $10.000 vendidos'], false);
    }

    public function test_sin_datos_los_indicadores_porcentuales_no_se_muestran_como_cero(): void
    {
        $indicadores = app(IndicadorService::class)->calcular();

        $this->assertNull($indicadores['m11']['porcentaje']);
        $this->assertNull($indicadores['m12']['exactitud']);
        $this->assertNull($indicadores['m3']['porcentaje']);
        $this->assertSame(0, $indicadores['m2']['total']);

        $this->actingAs(User::factory()->administrador()->create())->get('/indicadores')
            ->assertSeeInOrder(['id="m11"', '—', 'id="m12"', '—', 'id="m2"', '0', 'id="m3"', '—'], false);
    }
}
