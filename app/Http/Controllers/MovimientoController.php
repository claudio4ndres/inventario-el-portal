<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU07: bitácora de movimientos (trazabilidad, K8). */
class MovimientoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'tipo' => ['nullable', Rule::in([Movimiento::ENTRADA, Movimiento::SALIDA, Movimiento::AJUSTE, Movimiento::MERMA])],
        ]);

        $movimientos = Movimiento::with(['producto', 'lote', 'usuario'])
            ->when($filtros['producto_id'] ?? null, fn ($q, $id) => $q->where('producto_id', $id))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('movimientos.index', [
            'movimientos' => $movimientos,
            'productos' => Producto::orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $filtros,
        ]);
    }
}
