<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** HU03: recepción de mercadería con lote y vencimiento (K2). HU11: recepción contra orden de compra. */
class RecepcionController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function index(): View
    {
        $recepciones = Recepcion::with(['proveedor', 'usuario'])
            ->withCount('lotes')
            ->latest('fecha_recepcion')
            ->paginate(15);

        return view('recepciones.index', compact('recepciones'));
    }

    public function create(Request $request): View
    {
        // HU11: si se elige una OC enviada, se precargan su proveedor y sus productos con la cantidad pedida
        $ordenes = OrdenCompra::with('proveedor')->where('estado', OrdenCompra::ENVIADA)->orderBy('id')->get();
        $orden = $ordenes->firstWhere('id', $request->integer('orden_compra_id'));
        $lineas = $orden
            ? $orden->detalles()->orderBy('id')->get()->map(fn ($d) => ['producto_id' => $d->producto_id, 'cantidad' => $d->cantidad])->all()
            : [[]];

        return view('recepciones.create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo_barras']),
            'ordenes' => $ordenes,
            'orden' => $orden,
            'lineasIniciales' => $lineas,
        ]);
    }

    /** HU11: detalle de la recepción; si se asoció a una OC, muestra las diferencias de cantidad. */
    public function show(Recepcion $recepcion): View
    {
        $recepcion->load(['proveedor', 'usuario', 'lotes.producto', 'ordenCompra']);

        return view('recepciones.show', [
            'recepcion' => $recepcion,
            'diferencias' => $recepcion->ordenCompra?->diferencias() ?? collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'proveedor_id' => ['required', Rule::exists('proveedores', 'id')->where('activo', true)],
            // HU11: OC opcional; debe estar enviada y ser del proveedor elegido
            'orden_compra_id' => ['nullable', 'integer', Rule::exists('ordenes_compra', 'id')
                ->where('estado', OrdenCompra::ENVIADA)
                ->where('proveedor_id', $request->integer('proveedor_id'))],
            'numero_documento' => ['required', 'string', 'max:30'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('activo', true)],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'lineas.*.codigo_lote' => ['required', 'string', 'max:40'],
            'lineas.*.fecha_vencimiento' => ['nullable', 'date', 'after:today'],
            'lineas.*.costo_unitario' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [
            'lineas.*.fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ]);

        try {
            $recepcion = $this->inventario->registrarRecepcion(
                $request->user(),
                [
                    'proveedor_id' => (int) $datos['proveedor_id'],
                    'numero_documento' => $datos['numero_documento'],
                    'orden_compra_id' => isset($datos['orden_compra_id']) ? (int) $datos['orden_compra_id'] : null,
                ],
                $datos['lineas'],
            );
        } catch (InvalidArgumentException $e) {
            // La OC cambió de estado entre la validación y la transacción (otra recepción la cerró)
            return back()->withInput()->withErrors(['orden_compra_id' => $e->getMessage()]);
        }

        $mensaje = "Recepción N.º {$recepcion->id} registrada con ".count($datos['lineas']).' lote(s).';

        if ($recepcion->orden_compra_id === null) {
            return redirect()->route('recepciones.index')->with('ok', $mensaje);
        }

        // HU11: se muestra el detalle con las diferencias entre lo pedido y lo recibido
        $conDiferencia = $recepcion->ordenCompra->diferencias()->where('diferencia', '!=', 0)->count();

        return redirect()->route('recepciones.show', $recepcion)->with('ok', $mensaje
            ." Orden de compra N.º {$recepcion->orden_compra_id} recibida: "
            .($conDiferencia === 0 ? 'sin diferencias de cantidad.' : "{$conDiferencia} producto(s) con diferencias de cantidad."));
    }
}
