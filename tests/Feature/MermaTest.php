<?php

namespace Tests\Feature;

use App\Exceptions\SaldoLoteInsuficienteException;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU13: registro de mermas con cantidad, costo y motivo (RF6, K7, OE3). */
class MermaTest extends TestCase
{
    use RefreshDatabase;

    /** Crea un lote con la recepción real (HU03) y devuelve el lote creado. */
    private function lote(Producto $producto, string $codigo, int $cantidad, int $costo = 1000): Lote
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-'.$codigo],
            [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'codigo_lote' => $codigo,
                'fecha_vencimiento' => now()->addDays(5)->toDateString(),
                'costo_unitario' => $costo,
            ]],
        );

        return Lote::where('codigo_lote', $codigo)->firstOrFail();
    }

    public function test_el_formulario_muestra_lotes_con_saldo_y_los_motivos(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Yogur frutilla 125 g']);
        $conSaldo = $this->lote($producto, 'YO-0912', 12, 280);
        $agotado = $this->lote($producto, 'YO-AGOTADO', 2, 280);
        $agotado->update(['cantidad_disponible' => 0]);

        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/mermas/crear?lote_id='.$conSaldo->id)
            ->assertOk()
            ->assertSee('YO-0912')
            ->assertDontSee('YO-AGOTADO')
            ->assertSee('name="cantidad"', false)
            ->assertSee('name="observacion"', false)
            ->assertSeeInOrder(['Vencimiento', 'Daño', 'Robo', 'Otro'])
            ->assertViewHas('loteSeleccionado', $conSaldo->id);
    }

    public function test_bodeguero_registra_merma_que_descuenta_el_lote_y_registra_movimiento_con_costo(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $producto = Producto::factory()->create();
        $lote = $this->lote($producto, 'LE-2611A', 10, 850);

        $this->actingAs($bodeguero)->post('/mermas', [
            'lote_id' => $lote->id,
            'cantidad' => 3,
            'motivo_merma' => 'vencimiento',
            'observacion' => 'Retirado de la vitrina refrigerada',
        ])->assertRedirect('/mermas')->assertSessionHas('ok');

        $this->assertSame(7, $lote->fresh()->cantidad_disponible);
        $this->assertSame(7, $producto->stockActual());
        $this->assertDatabaseHas('movimientos', [
            'producto_id' => $producto->id,
            'lote_id' => $lote->id,
            'user_id' => $bodeguero->id,
            'tipo' => Movimiento::MERMA,
            'cantidad' => 3,
            'stock_resultante' => 7,
            'costo_total' => 2550,   // 3 × $850
            'motivo_merma' => 'vencimiento',
            'motivo' => 'Merma por vencimiento: Retirado de la vitrina refrigerada',
        ]);
    }

    public function test_administrador_tambien_registra_mermas_y_se_puede_merma_el_saldo_completo(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'PM-1019', 4, 1500);

        $this->actingAs(User::factory()->administrador()->create())->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 4, 'motivo_merma' => 'robo',
        ])->assertRedirect('/mermas');

        $this->assertSame(0, $lote->fresh()->cantidad_disponible);
        $this->assertDatabaseHas('movimientos', [
            'tipo' => Movimiento::MERMA, 'cantidad' => 4, 'stock_resultante' => 0,
            'costo_total' => 6000, 'motivo_merma' => 'robo', 'motivo' => 'Merma por robo',
        ]);
    }

    public function test_no_se_puede_registrar_una_merma_mayor_que_el_saldo_del_lote(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'AZ-0815', 5);

        $this->actingAs(User::factory()->bodeguero()->create())->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 6, 'motivo_merma' => 'dano',
        ])->assertSessionHasErrors('cantidad');

        $this->assertSame(5, $lote->fresh()->cantidad_disponible);
        $this->assertDatabaseMissing('movimientos', ['tipo' => Movimiento::MERMA]);
    }

    public function test_el_servicio_rechaza_una_merma_mayor_que_el_saldo_y_no_registra_nada(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'FI-0420', 2);

        try {
            app(InventarioService::class)->registrarMerma(User::factory()->bodeguero()->create(), $lote->id, 3, 'dano');
            $this->fail('Se esperaba SaldoLoteInsuficienteException');
        } catch (SaldoLoteInsuficienteException $e) {
            $this->assertSame(3, $e->solicitado);
            $this->assertStringContainsString('FI-0420', $e->getMessage());
        }

        $this->assertSame(2, $lote->fresh()->cantidad_disponible);
        $this->assertSame(0, Movimiento::where('tipo', Movimiento::MERMA)->count());
    }

    public function test_la_merma_usa_el_saldo_actual_tras_las_ventas(): void
    {
        $producto = Producto::factory()->create();
        $lote = $this->lote($producto, 'HU-1020', 8, 2400);
        app(InventarioService::class)->registrarVenta(User::factory()->create(), [['producto_id' => $producto->id, 'cantidad' => 6]]);

        $bodeguero = User::factory()->bodeguero()->create();
        $this->actingAs($bodeguero)->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 3, 'motivo_merma' => 'dano',
        ])->assertSessionHasErrors('cantidad');

        $this->actingAs($bodeguero)->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 2, 'motivo_merma' => 'dano',
        ])->assertRedirect('/mermas');

        $this->assertSame(0, $producto->stockActual());
    }

    public function test_valida_lote_cantidad_motivo_y_observacion(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'BE-3301', 10);
        $agotado = $this->lote(Producto::factory()->create(), 'AGOTADO', 1);
        $agotado->update(['cantidad_disponible' => 0]);
        $bodeguero = User::factory()->bodeguero()->create();

        $this->actingAs($bodeguero)->post('/mermas', [])
            ->assertSessionHasErrors(['lote_id', 'cantidad', 'motivo_merma']);

        $this->actingAs($bodeguero)->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 0, 'motivo_merma' => 'regalo', 'observacion' => str_repeat('x', 101),
        ])->assertSessionHasErrors(['cantidad', 'motivo_merma', 'observacion']);

        $this->actingAs($bodeguero)->post('/mermas', [
            'lote_id' => $agotado->id, 'cantidad' => 1, 'motivo_merma' => 'otro',
        ])->assertSessionHasErrors('lote_id');

        $this->assertDatabaseMissing('movimientos', ['tipo' => Movimiento::MERMA]);
    }

    public function test_el_costo_se_calcula_desde_el_lote_y_no_desde_el_formulario(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'YO-0901', 3, 280);

        $this->actingAs(User::factory()->bodeguero()->create())->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 2, 'motivo_merma' => 'vencimiento', 'costo_total' => 1,
        ])->assertRedirect('/mermas');

        $this->assertDatabaseHas('movimientos', ['tipo' => Movimiento::MERMA, 'costo_total' => 560]);
    }

    public function test_el_listado_muestra_las_mermas_con_su_costo_total(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Leche entera 1 L']);
        $lote = $this->lote($producto, 'LE-2611B', 24, 850);
        $servicio = app(InventarioService::class);
        $bodeguero = User::factory()->bodeguero()->create();
        $servicio->registrarMerma($bodeguero, $lote->id, 2, 'vencimiento');
        $servicio->registrarMerma($bodeguero, $lote->id, 1, 'dano', 'Caja aplastada');

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/mermas')
            ->assertOk()
            ->assertViewHas('costoTotal', 2550)
            ->assertSee('Leche entera 1 L')
            ->assertSee('LE-2611B')
            ->assertSee('Vencimiento')
            ->assertSee('Daño')
            ->assertSee('Caja aplastada')
            ->assertSee('$1.700')
            ->assertSee('id="kpi-costo-mermas">$2.550<', false);
    }

    public function test_solo_administrador_y_bodeguero_acceden(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'AR-0001', 5);

        $this->get('/mermas')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrador()->create())->get('/mermas')->assertOk();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/mermas/crear')->assertOk();

        $vendedor = User::factory()->vendedor()->create();
        $this->actingAs($vendedor)->get('/mermas')->assertForbidden();
        $this->actingAs($vendedor)->get('/mermas/crear')->assertForbidden();
        $this->actingAs($vendedor)->post('/mermas', [
            'lote_id' => $lote->id, 'cantidad' => 1, 'motivo_merma' => 'robo',
        ])->assertForbidden();

        $this->assertSame(5, $lote->fresh()->cantidad_disponible);
    }

    public function test_la_merma_aparece_en_la_bitacora_y_en_lotes_por_vencer_hay_acceso_directo(): void
    {
        $lote = $this->lote(Producto::factory()->create(), 'PM-2020', 6, 1500);
        app(InventarioService::class)->registrarMerma(User::factory()->bodeguero()->create(), $lote->id, 1, 'dano');
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/movimientos?tipo=MERMA')
            ->assertOk()
            ->assertSee('Merma por daño')
            ->assertSee('costo $1.500');

        $this->actingAs($admin)->get('/lotes/por-vencer')
            ->assertOk()
            ->assertSee(route('mermas.create', ['lote_id' => $lote->id]), false);
    }
}
