<?php

namespace App\Http\Controllers;

use App\Exceptions\StockInsuficienteException;
use App\Models\Producto;
use App\Models\Venta;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU04: registro de venta con descuento automático de stock por lote (K1). */
class VentaController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function index(): View
    {
        $ventas = Venta::with('usuario')->withCount('detalles')->latest('fecha_venta')->paginate(15);

        return view('ventas.index', compact('ventas'));
    }

    public function create(Request $request): View
    {
        // Si la venta anterior fue rechazada, se reconstruye el carrito desde el input previo
        $previos = collect($request->old('items', []));
        $productos = Producto::conStock()->whereIn('id', $previos->pluck('producto_id'))->get()->keyBy('id');
        $carrito = $previos
            ->filter(fn ($i) => isset($productos[$i['producto_id'] ?? null]))
            ->map(fn ($i) => [
                'id' => (int) $i['producto_id'],
                'nombre' => $productos[$i['producto_id']]->nombre,
                'codigo_barras' => $productos[$i['producto_id']]->codigo_barras,
                'precio_venta' => $productos[$i['producto_id']]->precio_venta,
                'stock' => (int) $productos[$i['producto_id']]->stock,
                'cantidad' => (int) ($i['cantidad'] ?? 1),
            ])->values();

        return view('ventas.create', compact('carrito'));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'medio_pago' => ['required', Rule::in(['efectivo', 'debito', 'credito', 'transferencia'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('activo', true)],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
        ], [
            'items.required' => 'Agregue al menos un producto a la venta.',
        ]);

        try {
            $venta = $this->inventario->registrarVenta($request->user(), $datos['items'], $datos['medio_pago']);
        } catch (StockInsuficienteException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('ventas.show', $venta)
            ->with('ok', 'Venta registrada. Total: $'.number_format($venta->total, 0, ',', '.'));
    }

    public function show(Venta $venta): View
    {
        $venta->load(['usuario', 'detalles.producto', 'detalles.movimientos.lote']);

        return view('ventas.show', compact('venta'));
    }
}
