<?php

namespace App\Http\Controllers;

use App\Services\ClasificacionAbcService;
use Illuminate\Http\RedirectResponse;

/** HU17: botón "Recalcular ABC" (RF8, K6/K7). */
class ClasificacionAbcController extends Controller
{
    public function __invoke(ClasificacionAbcService $servicio): RedirectResponse
    {
        $resumen = $servicio->recalcular();

        $mensaje = $resumen === null
            ? 'No hay ventas en los últimos '.ClasificacionAbcService::DIAS_PERIODO.' días: no se modificó la clasificación ABC.'
            : 'Clasificación ABC recalculada según el valor vendido en los últimos '.ClasificacionAbcService::DIAS_PERIODO.
              " días: {$resumen['A']} en A, {$resumen['B']} en B y {$resumen['C']} en C.";

        return back()->with('ok', $mensaje);
    }
}
