<?php

namespace Tests\Feature;

use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU03: recepción de mercadería con lote y vencimiento (K2). */
class RecepcionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bodeguero_registra_recepcion_y_aumenta_el_stock(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($bodeguero)->post('/recepciones', [
            'proveedor_id' => $proveedor->id,
            'numero_documento' => 'F-123',
            'lineas' => [[
                'producto_id' => $producto->id,
                'cantidad' => 24,
                'codigo_lote' => 'L-001',
                'fecha_vencimiento' => now()->addDays(10)->toDateString(),
                'costo_unitario' => 800,
            ]],
        ])->assertRedirect('/recepciones');

        $this->assertSame(24, $producto->stockActual());
        $this->assertDatabaseHas('lotes', ['producto_id' => $producto->id, 'codigo_lote' => 'L-001', 'cantidad_disponible' => 24]);
        $this->assertDatabaseHas('movimientos', [
            'producto_id' => $producto->id, 'tipo' => Movimiento::ENTRADA, 'cantidad' => 24,
            'stock_resultante' => 24, 'user_id' => $bodeguero->id,
        ]);
    }

    public function test_fecha_de_vencimiento_pasada_es_rechazada_y_no_se_registra_nada(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $producto = Producto::factory()->create();

        $this->actingAs($bodeguero)->post('/recepciones', [
            'proveedor_id' => Proveedor::factory()->create()->id,
            'numero_documento' => 'F-124',
            'lineas' => [[
                'producto_id' => $producto->id, 'cantidad' => 5, 'codigo_lote' => 'L-002',
                'fecha_vencimiento' => now()->subDay()->toDateString(),
            ]],
        ])->assertSessionHasErrors('lineas.0.fecha_vencimiento');

        $this->assertDatabaseCount('recepciones', 0);
        $this->assertDatabaseCount('lotes', 0);
    }

    public function test_cantidad_debe_ser_mayor_que_cero(): void
    {
        $this->actingAs(User::factory()->bodeguero()->create())->post('/recepciones', [
            'proveedor_id' => Proveedor::factory()->create()->id,
            'numero_documento' => 'F-125',
            'lineas' => [['producto_id' => Producto::factory()->create()->id, 'cantidad' => 0, 'codigo_lote' => 'L-003']],
        ])->assertSessionHasErrors('lineas.0.cantidad');
    }

    public function test_producto_no_perecible_acepta_vencimiento_vacio(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs(User::factory()->administrador()->create())->post('/recepciones', [
            'proveedor_id' => Proveedor::factory()->create()->id,
            'numero_documento' => 'F-126',
            'lineas' => [['producto_id' => $producto->id, 'cantidad' => 10, 'codigo_lote' => 'AR-1', 'fecha_vencimiento' => '']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'AR-1', 'fecha_vencimiento' => null]);
    }
}
