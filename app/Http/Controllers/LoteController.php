<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** HU12: alerta de lotes por vencer, ordenados FEFO (RF5, K4, OE3). */
class LoteController extends Controller
{
    public function porVencer(Request $request): View
    {
        $datos = $request->validate([
            'dias' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $dias = (int) ($datos['dias'] ?? config('inventario.dias_por_vencer'));

        $lotes = Lote::porVencer($dias)
            ->with('producto')
            ->orderBy('fecha_vencimiento')   // FEFO: el que vence antes, primero
            ->orderBy('id')
            ->get();

        return view('lotes.por-vencer', [
            'lotes' => $lotes,
            'dias' => $dias,
            'valorTotal' => $lotes->sum(fn (Lote $l) => $l->valorEnRiesgo()),
            'vencidos' => $lotes->filter(fn (Lote $l) => $l->estaVencido())->count(),
        ]);
    }
}
