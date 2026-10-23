<?php

namespace App\Exceptions;

use App\Models\Lote;
use RuntimeException;

/** HU13 / K5: se intentó retirar de un lote más unidades que su saldo. */
class SaldoLoteInsuficienteException extends RuntimeException
{
    public function __construct(
        public readonly Lote $lote,
        public readonly int $solicitado,
    ) {
        parent::__construct(sprintf(
            'El lote "%s" tiene %d unidades disponibles; no se puede registrar una merma de %d.',
            $lote->codigo_lote,
            $lote->cantidad_disponible,
            $solicitado,
        ));
    }
}
