<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function __invoke(Request $request): View
    {
        $hoy = now()->startOfDay();

        return view('panel', [
            'alertasReorden' => Producto::bajoPuntoReorden()->count(),
            // HU12: lotes con saldo que vencen dentro de la ventana configurada (incluye vencidos)
            'lotesPorVencer' => Lote::porVencer(config('inventario.dias_por_vencer'))->count(),
            'diasPorVencer' => (int) config('inventario.dias_por_vencer'),
            'productosActivos' => Producto::where('activo', true)->count(),
            'ventasHoy' => Venta::where('fecha_venta', '>=', $hoy)->count(),
            'montoHoy' => (int) Venta::where('fecha_venta', '>=', $hoy)->sum('total'),
        ]);
    }
}
