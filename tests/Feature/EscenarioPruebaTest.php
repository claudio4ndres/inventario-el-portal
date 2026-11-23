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

    /**
     * Valores esperados del tablero con el escenario cargado (docs/escenario-pruebas.md).
     * Sirven para verificar el tablero en el ambiente de pruebas (H5, CC-05).
     */
    public function test_los_indicadores_del_escenario_tienen_los_valores_esperados(): void
    {
        $this->seed(EscenarioPruebaSeeder::class);

        $i = app(IndicadorService::class)->calcular();

        // M1.1: 434 ventas, todas con descuento automático por lote
        $this->assertSame(['ventas' => 434, 'con_descuento' => 434, 'porcentaje' => 100.0], $i['m11']);

        // M1.2: 4 conteos de la clase A; una diferencia (bebida, día 13) en 12 productos contados
        $this->assertSame(91.7, $i['m12']['exactitud']);

        // M2: 20 días-producto sin stock; la semana 2 concentra los quiebres de la leche
        $this->assertSame(20, $i['m2']['total']);
        $this->assertSame([4, 9, 4, 3], $i['m2']['semanas']->map(fn ($s) => $s->quiebres)->all());
        $this->assertSame(
            [['Fideos spaghetti 400 g', 11], ['Leche entera 1 L', 5], ['Pan de molde blanco', 4]],
            $i['m2']['productos']->map(fn ($p) => [$p->nombre, $p->quiebres])->values()->all(),
        );

        // M3: merma por vencimiento de $19.460 sobre $1.801.210 vendidos
        $this->assertSame(['merma' => 19460, 'ventas' => 1801210, 'porcentaje' => 1.1], $i['m3']);
    }

    public function test_no_se_carga_sobre_una_base_con_datos(): void
    {
        User::factory()->administrador()->create(['email' => 'dueno@elportal.cl']);

        $this->expectException(RuntimeException::class);
        app(EscenarioPruebaSeeder::class)->setContainer(app())->__invoke();
    }
}
