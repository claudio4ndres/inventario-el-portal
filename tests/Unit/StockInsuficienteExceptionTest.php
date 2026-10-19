<?php

namespace Tests\Unit;

use App\Exceptions\StockInsuficienteException;
use App\Models\Producto;
use PHPUnit\Framework\TestCase;

/** Prueba unitaria (sin base de datos) del mensaje que ve la persona de caja. */
class StockInsuficienteExceptionTest extends TestCase
{
    public function test_el_mensaje_indica_producto_cantidad_solicitada_y_disponible(): void
    {
        $producto = new Producto(['nombre' => 'Huevos blancos 12 un.']);

        $e = new StockInsuficienteException($producto, 6, 5);

        $this->assertSame('Stock insuficiente para "Huevos blancos 12 un.": se solicitaron 6 y hay 5 disponibles.', $e->getMessage());
        $this->assertSame(6, $e->solicitado);
        $this->assertSame(5, $e->disponible);
    }
}
