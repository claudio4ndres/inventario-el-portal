<?php

namespace Tests\Unit;

use App\Services\ClasificacionAbcService;
use PHPUnit\Framework\TestCase;

/** HU17: pruebas unitarias (sin base de datos) de la regla del porcentaje acumulado. */
class ClasificacionAbcTest extends TestCase
{
    public function test_a_hasta_el_80_por_ciento_b_hasta_el_95_y_c_el_resto(): void
    {
        // Total 100.000: acumulados previos 0 %, 50 %, 80 %, 90 % y 96 %.
        $clases = ClasificacionAbcService::clasificar([3 => 30_000, 1 => 50_000, 5 => 4_000, 2 => 10_000, 4 => 6_000]);

        $this->assertSame(['A', 'A', 'B', 'B', 'C'], [$clases[1], $clases[3], $clases[2], $clases[4], $clases[5]]);
    }

    public function test_el_producto_que_cruza_el_umbral_queda_en_la_clase_superior(): void
    {
        // Un solo producto con el 90 % de las ventas es A, aunque supere el 80 %.
        $this->assertSame(['A', 'B'], array_values(ClasificacionAbcService::clasificar([7 => 90_000, 8 => 10_000])));

        // 70 % + 20 %: el segundo empieza en el 70 % y también es A.
        $clases = ClasificacionAbcService::clasificar([1 => 70, 2 => 20, 3 => 6, 4 => 4]);
        $this->assertSame(['A', 'A', 'B', 'C'], [$clases[1], $clases[2], $clases[3], $clases[4]]);
    }

    public function test_los_empates_se_ordenan_por_id_para_un_resultado_estable(): void
    {
        $clases = ClasificacionAbcService::clasificar([9 => 40, 2 => 40, 5 => 20]);

        $this->assertSame([2, 9, 5], array_keys($clases));
        $this->assertSame(['A', 'A', 'B'], array_values($clases));
    }
}
