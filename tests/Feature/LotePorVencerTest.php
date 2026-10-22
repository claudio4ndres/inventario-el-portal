<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU12: alerta de lotes por vencer (RF5, K4, OE3). */
class LotePorVencerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Fecha fija para que los días restantes no dependan del día en que corre la CI
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
    }

    /** Crea un lote con la recepción real (HU03) y devuelve el lote creado. */
    private function lote(Producto $producto, string $codigo, int $cantidad, ?int $venceEnDias, int $costo = 1000): Lote
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-'.$codigo],
            [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'codigo_lote' => $codigo,
                'fecha_vencimiento' => $venceEnDias === null ? null : today()->addDays($venceEnDias)->toDateString(),
                'costo_unitario' => $costo,
            ]],
        );

        return Lote::where('codigo_lote', $codigo)->firstOrFail();
    }

    public function test_lista_solo_lotes_con_saldo_que_vencen_dentro_de_7_dias_por_defecto(): void
    {
        $producto = Producto::factory()->create();
        $dentro = $this->lote($producto, 'DENTRO-7', 5, 7);
        $this->lote($producto, 'FUERA-8', 5, 8);
        $this->lote($producto, 'NO-PERECIBLE', 5, null);
        $agotado = $this->lote(Producto::factory()->create(), 'AGOTADO', 2, 3);
        $agotado->update(['cantidad_disponible' => 0]);

        $this->assertSame([$dentro->id], Lote::porVencer(7)->pluck('id')->all());

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/lotes/por-vencer')
            ->assertOk()
            ->assertViewHas('dias', 7)
            ->assertSee('DENTRO-7')
            ->assertDontSee('FUERA-8')
            ->assertDontSee('NO-PERECIBLE')
            ->assertDontSee('AGOTADO');
    }

    public function test_la_ventana_de_dias_es_configurable_por_parametro_y_por_configuracion(): void
    {
        $producto = Producto::factory()->create();
        $this->lote($producto, 'EN-10', 4, 10);
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/lotes/por-vencer')->assertDontSee('EN-10');
        $this->actingAs($admin)->get('/lotes/por-vencer?dias=15')->assertOk()->assertViewHas('dias', 15)->assertSee('EN-10');

        config(['inventario.dias_por_vencer' => 12]);
        $this->actingAs($admin)->get('/lotes/por-vencer')->assertViewHas('dias', 12)->assertSee('EN-10');
    }

    public function test_el_parametro_dias_se_valida(): void
    {
        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->get('/lotes/por-vencer?dias=0')->assertSessionHasErrors('dias');
        $this->actingAs($admin)->get('/lotes/por-vencer?dias=abc')->assertSessionHasErrors('dias');
        $this->actingAs($admin)->get('/lotes/por-vencer?dias=366')->assertSessionHasErrors('dias');
    }

    public function test_los_lotes_se_ordenan_por_fecha_de_vencimiento(): void
    {
        $producto = Producto::factory()->create();
        $this->lote($producto, 'LOTE-C', 1, 6);
        $this->lote($producto, 'LOTE-A', 1, -2);
        $this->lote($producto, 'LOTE-B', 1, 1);

        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/lotes/por-vencer')
            ->assertOk()
            ->assertSeeInOrder(['LOTE-A', 'LOTE-B', 'LOTE-C']);
    }

    public function test_muestra_dias_restantes_saldo_y_valor_en_riesgo(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Yogur frutilla 125 g']);
        $lote = $this->lote($producto, 'YO-0912', 12, 3, 280);

        $this->assertSame(3, $lote->diasRestantes());
        $this->assertSame(3360, $lote->valorEnRiesgo());   // 12 × $280

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/lotes/por-vencer')
            ->assertOk()
            ->assertSeeInOrder(['Yogur frutilla 125 g', 'YO-0912', '12', '$280', '$3.360'])
            ->assertSee('3 días')
            ->assertViewHas('valorTotal', 3360);
    }

    public function test_el_valor_en_riesgo_usa_el_saldo_tras_las_ventas(): void
    {
        $producto = Producto::factory()->create();
        $lote = $this->lote($producto, 'LE-2611A', 10, 4, 850);
        app(InventarioService::class)->registrarVenta(User::factory()->create(), [['producto_id' => $producto->id, 'cantidad' => 6]]);

        $this->assertSame(3400, $lote->fresh()->valorEnRiesgo());   // 4 × $850
    }

    public function test_los_lotes_vencidos_aparecen_destacados(): void
    {
        $producto = Producto::factory()->create();
        $vencido = $this->lote($producto, 'VENCIDO-1', 3, -1);
        $hoy = $this->lote($producto, 'VENCE-HOY', 3, 0);

        $this->assertTrue($vencido->estaVencido());
        $this->assertSame(-1, $vencido->diasRestantes());
        $this->assertFalse($hoy->estaVencido());

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/lotes/por-vencer')
            ->assertOk()
            ->assertSee('table-danger', false)
            ->assertSee('Vencido hace 1 día')
            ->assertSee('Vence hoy')
            ->assertViewHas('vencidos', 1);
    }

    public function test_el_panel_muestra_cuantos_lotes_estan_por_vencer(): void
    {
        $producto = Producto::factory()->create();
        $this->lote($producto, 'P1', 2, 2);
        $this->lote($producto, 'P2', 2, -3);
        $this->lote($producto, 'P3', 2, 30);

        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/')
            ->assertOk()
            ->assertViewHas('lotesPorVencer', 2)
            ->assertSee('Lotes por vencer (7 días)')
            ->assertSee('id="kpi-lotes-por-vencer">2<', false);
    }

    public function test_solo_administrador_y_bodeguero_acceden(): void
    {
        $this->get('/lotes/por-vencer')->assertRedirect('/login');
        $this->actingAs(User::factory()->administrador()->create())->get('/lotes/por-vencer')->assertOk();
        $this->actingAs(User::factory()->bodeguero()->create())->get('/lotes/por-vencer')->assertOk();
        $this->actingAs(User::factory()->create(['rol' => User::VENDEDOR]))->get('/lotes/por-vencer')->assertForbidden();
    }
}
