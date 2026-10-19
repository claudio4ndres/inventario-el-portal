<?php

namespace App\Http\Controllers;

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
            'productosActivos' => Producto::where('activo', true)->count(),
            'ventasHoy' => Venta::where('fecha_venta', '>=', $hoy)->count(),
            'montoHoy' => (int) Venta::where('fecha_venta', '>=', $hoy)->sum('total'),
        ]);
    }
}
