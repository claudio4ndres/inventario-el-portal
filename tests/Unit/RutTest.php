<?php

namespace Tests\Unit;

use App\Support\Rut;
use PHPUnit\Framework\TestCase;

/** HU09: pruebas unitarias (sin base de datos) del dígito verificador módulo 11. */
class RutTest extends TestCase
{
    public function test_calcula_el_digito_verificador_incluidos_los_casos_k_y_cero(): void
    {
        $this->assertSame('6', Rut::calcularDv('76111111'));
        $this->assertSame('5', Rut::calcularDv('12345678'));
        $this->assertSame('K', Rut::calcularDv('76000006'));
        $this->assertSame('0', Rut::calcularDv('76000000'));
    }

    public function test_acepta_ruts_validos_en_distintos_formatos(): void
    {
        foreach (['76.111.111-6', '76111111-6', '761111116', '12.345.678-5', '76.000.006-k', '76000006K', '1.234.567-4'] as $rut) {
            $this->assertTrue(Rut::esValido($rut), "Debió aceptar {$rut}");
        }
    }

    public function test_rechaza_digito_verificador_incorrecto_y_formatos_invalidos(): void
    {
        foreach (['76.111.111-1', '12.345.678-K', '', 'abc', '12-3', '123.456.789-0', '76.111.111-X', '7611a1111-6'] as $rut) {
            $this->assertFalse(Rut::esValido($rut), "Debió rechazar {$rut}");
        }
    }

    public function test_formatea_con_puntos_guion_y_k_mayuscula(): void
    {
        $this->assertSame('76.111.111-6', Rut::formatear('761111116'));
        $this->assertSame('76.000.006-K', Rut::formatear('76000006-k'));
        $this->assertSame('1.234.567-4', Rut::formatear('1234567-4'));
    }
}
