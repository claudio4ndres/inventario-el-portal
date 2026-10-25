<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Services\OrdenCompraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** HU11: orden de compra sugerida y recepción contra OC (RF4/RF3, K3/K2, OE2). Solo el administrador accede. */
class OrdenCompraController extends Controller
{
    public function __construct(private readonly OrdenCompraService $servicio) {}

    public function index(): View
    {
        $ordenes = OrdenCompra::with(['proveedor', 'usuario'])
            ->withCount('detalles')
            ->withSum('detalles as unidades', 'cantidad')
            ->latest('id')
            ->paginate(15);

        return view('ordenes-compra.index', [
            'ordenes' => $ordenes,
            'sugerencias' => $this->servicio->sugerencias(),
        ]);
    }

    /** Botón "Generar OC sugeridas": una OC en borrador por proveedor. */
    public function sugerir(Request $request): RedirectResponse
    {
        $ordenes = $this->servicio->generarSugeridas($request->user());

        if ($ordenes->isEmpty()) {
            return back()->withErrors([
                'orden' => 'No hay productos en alerta con proveedor asignado que no estén ya en una orden de compra abierta.',
            ]);
        }

        return redirect()->route('ordenes-compra.index')->with('ok', $ordenes->count() === 1
            ? "Se generó la orden de compra N.º {$ordenes->first()->id} en borrador. Revise las cantidades antes de enviarla."
            : "Se generaron {$ordenes->count()} órdenes de compra en borrador (una por proveedor). Revise las cantidades antes de enviarlas.");
    }

    public function show(OrdenCompra $orden): View
    {
        $orden->load(['proveedor', 'usuario', 'detalles.producto', 'recepciones']);

        return view('ordenes-compra.show', [
            'orden' => $orden,
            'diferencias' => $orden->estado === OrdenCompra::RECIBIDA ? $orden->diferencias() : collect(),
        ]);
    }

    /** Las cantidades sugeridas se pueden editar solo mientras la OC está en borrador. */
    public function update(Request $request, OrdenCompra $orden): RedirectResponse
    {
        if (! $orden->esBorrador()) {
            return back()->withErrors(['orden' => "La orden de compra N.º {$orden->id} ya fue {$orden->estado}: no se puede modificar."]);
        }

        $datos = $request->validate([
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        foreach ($orden->detalles as $detalle) {
            if (isset($datos['detalles'][$detalle->id])) {
                $detalle->update(['cantidad' => (int) $datos['detalles'][$detalle->id]['cantidad']]);
            }
        }

        return redirect()->route('ordenes-compra.show', $orden)->with('ok', "Cantidades de la orden de compra N.º {$orden->id} actualizadas.");
    }

    /** Borrador → enviada. Desde ese momento la OC puede asociarse a una recepción. */
    public function enviar(OrdenCompra $orden): RedirectResponse
    {
        if (! $orden->esBorrador()) {
            return back()->withErrors(['orden' => "La orden de compra N.º {$orden->id} ya fue {$orden->estado}."]);
        }

        $orden->update(['estado' => OrdenCompra::ENVIADA, 'fecha_envio' => now()]);

        return redirect()->route('ordenes-compra.show', $orden)
            ->with('ok', "Orden de compra N.º {$orden->id} marcada como enviada a {$orden->proveedor->razon_social}.");
    }
}
