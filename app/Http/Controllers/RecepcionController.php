<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU03: recepción de mercadería con lote y vencimiento (K2). */
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

    public function create(): View
    {
        return view('recepciones.create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(),
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo_barras']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'proveedor_id' => ['required', Rule::exists('proveedores', 'id')->where('activo', true)],
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

        $recepcion = $this->inventario->registrarRecepcion(
            $request->user(),
            ['proveedor_id' => (int) $datos['proveedor_id'], 'numero_documento' => $datos['numero_documento']],
            $datos['lineas'],
        );

        return redirect()->route('recepciones.index')
            ->with('ok', "Recepción N.º {$recepcion->id} registrada con ".count($datos['lineas']).' lote(s).');
    }
}
