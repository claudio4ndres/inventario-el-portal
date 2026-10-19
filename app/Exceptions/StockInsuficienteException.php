<?php

namespace App\Exceptions;

use App\Models\Producto;
use RuntimeException;

class StockInsuficienteException extends RuntimeException
{
    public function __construct(
        public readonly Producto $producto,
        public readonly int $solicitado,
        public readonly int $disponible,
    ) {
        parent::__construct(sprintf(
            'Stock insuficiente para "%s": se solicitaron %d y hay %d disponibles.',
            $producto->nombre,
            $solicitado,
            $disponible,
        ));
    }
}
