<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\IndicadorService;
use Database\Seeders\EscenarioPruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * El escenario simulado de 28 días produce los casos que se quieren probar en el tablero.
 * Son datos de prueba del sistema, no resultados del almacén.
 */
class EscenarioPruebaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_escenario_genera_quiebres_mermas_y_conteos_en_las_cuatro_semanas(): void
    {
        $this->seed(EscenarioPruebaSeeder::class);

        // Hubo operación todos los días del período y el stock nunca quedó negativo (K5)
        $this->assertGreaterThan(300, Venta::count());
        $this->assertSame(EscenarioPruebaSeeder::DIAS, Venta::query()->get('fecha_venta')
            ->map(fn (Venta $v) => $v->fecha_venta->toDateString())->unique()->count());
        $this->assertFalse(Lote::where('cantidad_disponible', '<', 0)->exists());
        Producto::all()->each(function (Producto $p) {
            $ultimo = Movimiento::where('producto_id', $p->id)->latest('id')->first();
            $this->assertSame($p->stockActual(), (int) $ultimo->stock_resultante, "Stock inconsistente: {$p->nombre}");
        });

        $indicadores = app(IndicadorService::class)->calcular();

        // M1.1: todas las ventas descontaron stock por lote
        $this->assertSame(100.0, $indicadores['m11']['porcentaje']);

        // M2: hay quiebres en el período y la leche quebró por las entregas que no llegaron (semana 2)
        $m2 = $indicadores['m2'];
        $this->assertGreaterThan(0, $m2['total']);
        $this->assertCount(4, $m2['semanas']);
        $this->assertGreaterThan(0, $m2['semanas'][1]->quiebres);
        $this->assertTrue($m2['productos']->contains('nombre', 'Leche entera 1 L'));

        // M3: hubo merma por vencimiento (perecibles retirados al cierre)
        $this->assertGreaterThan(0, $indicadores['m3']['merma']);
        $this->assertGreaterThan(0, $indicadores['m3']['porcentaje']);

        // Casos fijos de merma por daño y por robo
        $this->assertDatabaseHas('movimientos', ['tipo' => Movimiento::MERMA, 'motivo_merma' => 'dano', 'cantidad' => 1]);
        $this->assertDatabaseHas('movimientos', ['tipo' => Movimiento::MERMA, 'motivo_merma' => 'robo', 'cantidad' => 1]);

        // M1.2: cuatro conteos semanales de la clase A, uno con diferencia (bebida −1)
        $this->assertNotNull($indicadores['m12']['exactitud']);
        $this->assertLessThan(100.0, $indicadores['m12']['exactitud']);
    }

    public function test_no_se_carga_sobre_una_base_con_datos(): void
    {
        User::factory()->administrador()->create(['email' => 'dueno@elportal.cl']);

        $this->expectException(RuntimeException::class);
        app(EscenarioPruebaSeeder::class)->setContainer(app())->__invoke();
    }
}
