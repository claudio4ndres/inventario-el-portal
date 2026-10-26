<?php

namespace Tests\Feature;

use App\Models\Conteo;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU14: conteo cíclico por clase ABC y ajustes (RF7, K6, OE1 M1.2). */
class ConteoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(10, 0));
    }

    /** Crea un lote con la recepción real (HU03) y devuelve el lote creado. */
    private function lote(Producto $producto, string $codigo, int $cantidad, ?string $vence = null): Lote
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-'.$codigo],
            [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'codigo_lote' => $codigo,
                'fecha_vencimiento' => $vence,
                'costo_unitario' => 500,
            ]],
        );

        return Lote::where('codigo_lote', $codigo)->firstOrFail();
    }

    public function test_la_programacion_es_semanal_para_a_quincenal_para_b_y_mensual_para_c(): void
    {
        Producto::factory()->create(['clase_abc' => 'A']);
        Producto::factory()->create(['clase_abc' => 'B']);
        $bodeguero = User::factory()->bodeguero()->create();

        // Sin conteos: las tres clases corresponden hoy
        $programacion = Conteo::programacion();
        $this->assertSame([7, 14, 30], $programacion->pluck('dias')->values()->all());
        $this->assertTrue($programacion->every(fn ($p) => $p->pendiente));

        $servicio = app(InventarioService::class);
        $servicio->registrarConteo($bodeguero, 'A', [Producto::where('clase_abc', 'A')->value('id') => ['cantidad' => 0]]);
        $servicio->registrarConteo($bodeguero, 'B', [Producto::where('clase_abc', 'B')->value('id') => ['cantidad' => 0]]);

        $programacion = Conteo::programacion();
        $this->assertFalse($programacion['A']->pendiente);
        $this->assertSame('2026-11-02', $programacion['A']->proxima->toDateString());
        $this->assertSame('2026-11-09', $programacion['B']->proxima->toDateString());
        $this->assertTrue($programacion['C']->pendiente);

        // A los 7 días vuelve a corresponder A; B sigue al día hasta el día 14
        $this->travel(7)->days();
        $programacion = Conteo::programacion();
        $this->assertTrue($programacion['A']->pendiente);
        $this->assertFalse($programacion['A']->atrasada);
        $this->assertFalse($programacion['B']->pendiente);

        $this->travel(8)->days();
        $this->assertTrue(Conteo::programacion()['B']->atrasada);

        $this->actingAs($bodeguero)->get('/conteos')
            ->assertOk()
            ->assertSeeInOrder(['Clase A', 'Semanal', 'Clase B', 'Quincenal', 'Clase C', 'Mensual'])
            ->assertSee('Atrasado');
    }

    public function test_la_planilla_muestra_los_productos_activos_de_la_clase_con_su_stock(): void
    {
        $leche = Producto::factory()->create(['nombre' => 'Leche entera 1 L', 'clase_abc' => 'A']);
        $this->lote($leche, 'LE-01', 22);
        Producto::factory()->create(['nombre' => 'Huevos 12 un.', 'clase_abc' => 'A']);
        Producto::factory()->create(['nombre' => 'Fideos 400 g', 'clase_abc' => 'C']);
        Producto::factory()->create(['nombre' => 'Cecina descontinuada', 'clase_abc' => 'A', 'activo' => false]);

        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/conteos/crear?clase=A')
            ->assertOk()
            ->assertSee('Huevos 12 un.')
            ->assertSee('Leche entera 1 L')
            ->assertSee('data-sistema="22"', false)
            ->assertSee('name="conteos['.$leche->id.'][cantidad]"', false)
            ->assertDontSee('Fideos 400 g')
            ->assertDontSee('Cecina descontinuada');

        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/conteos/crear?clase=Z')
            ->assertSessionHasErrors('clase');
    }

    public function test_faltante_descuenta_en_orden_fefo_y_genera_ajustes_con_motivo(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $leche = Producto::factory()->create(['clase_abc' => 'A']);
        $loteNuevo = $this->lote($leche, 'LE-NUEVO', 10, '2026-11-20');
        $loteViejo = $this->lote($leche, 'LE-VIEJO', 3, '2026-10-30');

        $this->actingAs($bodeguero)->post('/conteos', [
            'clase_abc' => 'A',
            'conteos' => [$leche->id => ['cantidad' => 8, 'motivo' => 'Venta no registrada']],
        ])->assertRedirect();

        // Faltan 5: salen primero las 3 del lote que vence antes y luego 2 del siguiente
        $this->assertSame(0, $loteViejo->fresh()->cantidad_disponible);
        $this->assertSame(8, $loteNuevo->fresh()->cantidad_disponible);
        $this->assertSame(8, $leche->stockActual());

        $ajustes = Movimiento::where('tipo', Movimiento::AJUSTE)->orderBy('id')->get();
        $this->assertCount(2, $ajustes);
        $this->assertSame([$loteViejo->id, $loteNuevo->id], $ajustes->pluck('lote_id')->all());
        $this->assertSame([3, 2], $ajustes->pluck('cantidad')->all());
        $this->assertSame([10, 8], $ajustes->pluck('stock_resultante')->all());
        $this->assertStringContainsString('(−5): Venta no registrada', $ajustes[0]->motivo);
        $this->assertSame('−', $ajustes[0]->signo());

        $conteo = Conteo::sole();
        $this->assertDatabaseHas('detalle_conteos', [
            'conteo_id' => $conteo->id, 'producto_id' => $leche->id,
            'stock_sistema' => 13, 'cantidad_contada' => 8, 'diferencia' => -5, 'motivo' => 'Venta no registrada',
        ]);
        $this->assertSame($conteo->detalles()->value('id'), $ajustes[0]->detalle_conteo_id);
    }

    public function test_sobrante_suma_al_lote_mas_reciente_y_la_bitacora_lo_muestra_positivo(): void
    {
        $arroz = Producto::factory()->create(['nombre' => 'Arroz grado 2', 'clase_abc' => 'B']);
        $this->lote($arroz, 'AR-01', 4);
        $reciente = $this->lote($arroz, 'AR-02', 6);
        $reciente->update(['cantidad_disponible' => 0]);   // el lote más reciente puede estar en 0

        app(InventarioService::class)->registrarConteo(User::factory()->bodeguero()->create(), 'B', [
            $arroz->id => ['cantidad' => 7, 'motivo' => 'Recepción contada de menos'],
        ]);

        $this->assertSame(3, $reciente->fresh()->cantidad_disponible);
        $this->assertSame(7, $arroz->stockActual());
        $ajuste = Movimiento::where('tipo', Movimiento::AJUSTE)->sole();
        $this->assertSame([3, 7], [$ajuste->cantidad, $ajuste->stock_resultante]);
        $this->assertSame('+', $ajuste->signo());

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/movimientos?tipo=AJUSTE')
            ->assertOk()
            ->assertSee('+3')
            ->assertSee('Ajuste conteo N.º '.Conteo::sole()->id.' (+3): Recepción contada de menos');
    }

    public function test_sin_diferencias_no_genera_ajustes_ni_pide_motivo(): void
    {
        $producto = Producto::factory()->create(['clase_abc' => 'C']);
        $this->lote($producto, 'AZ-01', 15);

        $this->actingAs(User::factory()->bodeguero()->create())->post('/conteos', [
            'clase_abc' => 'C',
            'conteos' => [$producto->id => ['cantidad' => 15, 'motivo' => 'ignorado']],
        ])->assertRedirect(route('conteos.show', Conteo::sole()));

        $this->assertSame(0, Movimiento::where('tipo', Movimiento::AJUSTE)->count());
        $this->assertDatabaseHas('detalle_conteos', ['producto_id' => $producto->id, 'diferencia' => 0, 'motivo' => null]);
        $this->assertSame(100.0, Conteo::sole()->exactitud());
    }

    public function test_una_diferencia_sin_motivo_no_registra_nada(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $leche = Producto::factory()->create(['nombre' => 'Leche entera 1 L', 'clase_abc' => 'A']);
        $huevos = Producto::factory()->create(['nombre' => 'Huevos 12 un.', 'clase_abc' => 'A']);
        $this->lote($leche, 'LE-01', 10);
        $this->lote($huevos, 'HU-01', 8);

        $this->actingAs($bodeguero)->from('/conteos/crear?clase=A')->post('/conteos', [
            'clase_abc' => 'A',
            'conteos' => [
                $leche->id => ['cantidad' => 9, 'motivo' => 'Envase roto'],
                $huevos->id => ['cantidad' => 7, 'motivo' => '   '],
            ],
        ])->assertRedirect('/conteos/crear?clase=A')
            ->assertSessionHasErrors("conteos.{$huevos->id}.motivo");

        // La transacción se revierte completa: tampoco se ajusta la leche
        $this->assertSame(10, $leche->stockActual());
        $this->assertSame(0, Conteo::count());
        $this->assertSame(0, Movimiento::where('tipo', Movimiento::AJUSTE)->count());
    }

    public function test_calcula_la_exactitud_por_conteo_y_del_periodo(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $productos = Producto::factory()->count(4)->create(['clase_abc' => 'A']);
        $productos->each(fn (Producto $p, int $i) => $this->lote($p, 'L-'.$i, 10));

        $lineas = $productos->mapWithKeys(fn (Producto $p) => [$p->id => ['cantidad' => 10]])->all();
        $lineas[$productos[0]->id] = ['cantidad' => 9, 'motivo' => 'Robo'];

        $this->actingAs($bodeguero)->post('/conteos', ['clase_abc' => 'A', 'conteos' => $lineas])
            ->assertSessionHas('ok', fn (string $m) => str_contains($m, 'Exactitud 75,0 %'));

        $conteo = Conteo::sole();
        $this->assertSame([4, 1], [$conteo->productos_contados, $conteo->productos_con_diferencia]);
        $this->assertSame(0.25, $conteo->tasaDiferencias());   // productos con diferencia ÷ productos contados
        $this->assertSame(75.0, $conteo->exactitud());

        // Segundo conteo sin diferencias: el período suma 1 diferencia en 8 productos contados
        $this->travel(7)->days();
        app(InventarioService::class)->registrarConteo($bodeguero, 'A', $productos->mapWithKeys(fn (Producto $p) => [$p->id => ['cantidad' => $p->stockActual()]])->all());
        $this->assertSame(87.5, Conteo::exactitudPeriodo(30));

        $this->actingAs($bodeguero)->get('/conteos')
            ->assertOk()
            ->assertSee('id="kpi-exactitud">87,5 %<', false);
        $this->actingAs($bodeguero)->get('/conteos/'.$conteo->id)
            ->assertOk()
            ->assertSee('id="kpi-exactitud-conteo">75,0 %<', false)
            ->assertSee('Robo');
    }

    public function test_rechaza_sobrante_sin_lotes_productos_de_otra_clase_y_datos_invalidos(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $sinLotes = Producto::factory()->create(['clase_abc' => 'A']);
        $deOtraClase = Producto::factory()->create(['clase_abc' => 'C']);

        $this->actingAs($bodeguero)->post('/conteos', [
            'clase_abc' => 'A', 'conteos' => [$sinLotes->id => ['cantidad' => 2, 'motivo' => 'Apareció en bodega']],
        ])->assertSessionHasErrors("conteos.{$sinLotes->id}.cantidad");

        $this->actingAs($bodeguero)->post('/conteos', [
            'clase_abc' => 'A', 'conteos' => [$deOtraClase->id => ['cantidad' => 0]],
        ])->assertSessionHasErrors("conteos.{$deOtraClase->id}.cantidad");

        $this->actingAs($bodeguero)->post('/conteos', [
            'clase_abc' => 'D', 'conteos' => [$sinLotes->id => ['cantidad' => -1, 'motivo' => str_repeat('x', 81)]],
        ])->assertSessionHasErrors(['clase_abc', "conteos.{$sinLotes->id}.cantidad", "conteos.{$sinLotes->id}.motivo"]);

        $this->actingAs($bodeguero)->post('/conteos', ['clase_abc' => 'A'])
            ->assertSessionHasErrors('conteos');

        $this->assertSame(0, Conteo::count());
    }

    public function test_solo_administrador_y_bodeguero_acceden_y_el_usuario_con_conteos_no_se_elimina(): void
    {
        $producto = Producto::factory()->create(['clase_abc' => 'A']);
        $this->lote($producto, 'PR-01', 5);

        $this->get('/conteos')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrador()->create())->get('/conteos')->assertOk();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/conteos/crear?clase=A')->assertOk();

        $vendedor = User::factory()->vendedor()->create();
        $this->actingAs($vendedor)->get('/conteos')->assertForbidden();
        $this->actingAs($vendedor)->get('/conteos/crear?clase=A')->assertForbidden();
        $this->actingAs($vendedor)->post('/conteos', [
            'clase_abc' => 'A', 'conteos' => [$producto->id => ['cantidad' => 0, 'motivo' => 'x']],
        ])->assertForbidden();
        $this->assertSame(5, $producto->stockActual());

        // Un conteo sin diferencias no deja movimientos, pero igual es historia del usuario (FK RESTRICT)
        $contador = User::factory()->bodeguero()->create();
        app(InventarioService::class)->registrarConteo($contador, 'A', [$producto->id => ['cantidad' => 5]]);
        $this->assertTrue($contador->tieneHistoria());
    }
}
