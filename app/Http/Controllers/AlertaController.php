<?php

namespace App\Http\Controllers;

use App\Models\DetalleOrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\OrdenCompraService;
use Illuminate\View\View;

/** HU06: alertas de productos en o bajo su punto de reorden (K3). HU11: cantidad sugerida de compra. */
class AlertaController extends Controller
{
    public function index(): View
    {
        $productos = Producto::bajoPuntoReorden()
            ->conStock()
            ->conUltimoProveedor()
            ->orderBy('clase_abc')      // A primero: más críticos
            ->orderBy('nombre')
            ->get();

        // HU11: proveedor sugerido (último que entregó, si sigue activo) y OC abierta en que ya está el producto
        $proveedores = Proveedor::where('activo', true)
            ->whereIn('id', $productos->pluck('ultimo_proveedor_id')->filter()->unique())
            ->pluck('razon_social', 'id');

        $ordenesAbiertas = DetalleOrdenCompra::query()
            ->whereIn('producto_id', $productos->pluck('id'))
            ->whereHas('ordenCompra', fn ($q) => $q->abiertas())
            ->pluck('orden_compra_id', 'producto_id');

        $productos->each(function (Producto $p) use ($proveedores, $ordenesAbiertas) {
            $p->cantidad_sugerida = OrdenCompraService::cantidadSugerida($p->punto_reorden, (int) $p->stock);
            $p->proveedor_sugerido = $proveedores->get((int) $p->ultimo_proveedor_id);
            $p->orden_abierta_id = $ordenesAbiertas->get($p->id);
        });

        return view('alertas.index', compact('productos'));
    }
}
