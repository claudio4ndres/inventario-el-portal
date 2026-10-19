<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\View\View;

/** HU06: alertas de productos en o bajo su punto de reorden (K3). */
class AlertaController extends Controller
{
    public function index(): View
    {
        $productos = Producto::bajoPuntoReorden()
            ->conStock()
            ->orderBy('clase_abc')      // A primero: más críticos
            ->orderBy('nombre')
            ->get();

        return view('alertas.index', compact('productos'));
    }
}
