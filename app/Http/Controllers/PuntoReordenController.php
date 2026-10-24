<?php

namespace App\Http\Controllers;

use App\Services\PuntoReordenService;
use Illuminate\Http\RedirectResponse;

/** HU10: botón "Recalcular" del punto de reorden (RF4, K3). */
class PuntoReordenController extends Controller
{
    public function __invoke(PuntoReordenService $servicio): RedirectResponse
    {
        $recalculados = $servicio->recalcular();

        $mensaje = $recalculados === 0
            ? 'No hay productos con ventas en los últimos '.PuntoReordenService::DIAS_PERIODO.' días: no se modificó ningún punto de reorden.'
            : "Punto de reorden recalculado en {$recalculados} ".($recalculados === 1 ? 'producto' : 'productos').
              ' según las ventas de los últimos '.PuntoReordenService::DIAS_PERIODO.' días.';

        return back()->with('ok', $mensaje);
    }
}
