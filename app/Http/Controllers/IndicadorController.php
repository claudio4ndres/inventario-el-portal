<?php

namespace App\Http\Controllers;

use App\Services\IndicadorService;
use Illuminate\View\View;

/** HU15: tablero de indicadores M1.1, M1.2, M2 y M3 (RF8, K7, OE4). */
class IndicadorController extends Controller
{
    public function __invoke(IndicadorService $indicadores): View
    {
        $datos = $indicadores->calcular();

        return view('indicadores.index', $datos + [
            // Escala del gráfico: la barra más alta ocupa el 100 % (mínimo 1 para no dividir por 0)
            'maximoSemanal' => max(1, (int) $datos['m2']['semanas']->max('quiebres')),
        ]);
    }
}
