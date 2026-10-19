<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU02: mantenedor de productos (K5: validaciones). */
class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $productos = Producto::query()
            ->conStock()
            ->when($busqueda !== '', fn ($q) => $q->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('codigo_barras', $busqueda);
            }))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('productos.index', compact('productos', 'busqueda'));
    }

    public function create(): View
    {
        return view('productos.form', ['producto' => new Producto(['clase_abc' => 'C', 'activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $producto = Producto::create($this->validar($request));

        return redirect()->route('productos.index')
            ->with('ok', "Producto «{$producto->nombre}» creado.");
    }

    public function edit(Producto $producto): View
    {
        return view('productos.form', compact('producto'));
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $producto->update($this->validar($request, $producto));

        return redirect()->route('productos.index')
            ->with('ok', "Producto «{$producto->nombre}» actualizado.");
    }

    /**
     * Un producto no se elimina (tiene historia en la bitácora): se activa o desactiva.
     */
    public function toggle(Producto $producto): RedirectResponse
    {
        $producto->update(['activo' => ! $producto->activo]);

        return back()->with('ok', "Producto «{$producto->nombre}» ".($producto->activo ? 'activado.' : 'desactivado.'));
    }

    /** HU05: búsqueda por código de barras (escáner USB) o por nombre, para la pantalla de venta. */
    public function buscar(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('q', ''));
        if ($termino === '') {
            return response()->json([]);
        }

        $productos = Producto::query()
            ->conStock()
            ->where('activo', true)
            ->where(fn ($q) => $q->where('codigo_barras', $termino)->orWhere('nombre', 'like', "%{$termino}%"))
            ->orderByRaw('codigo_barras = ? desc', [$termino])
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'codigo_barras', 'nombre', 'precio_venta']);

        return response()->json($productos->map(fn (Producto $p) => [
            'id' => $p->id,
            'codigo_barras' => $p->codigo_barras,
            'nombre' => $p->nombre,
            'precio_venta' => $p->precio_venta,
            'stock' => (int) $p->stock,
        ]));
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        $datos = $request->validate([
            'codigo_barras' => [
                'required', 'string', 'max:32', 'regex:/^[0-9A-Za-z\-]+$/',
                Rule::unique('productos', 'codigo_barras')->ignore($producto?->id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'precio_venta' => ['required', 'integer', 'min:1', 'max:10000000'],
            'punto_reorden' => ['required', 'integer', 'min:0', 'max:100000'],
            'clase_abc' => ['required', Rule::in(['A', 'B', 'C'])],
        ]);
        $datos['activo'] = $request->boolean('activo', true);

        return $datos;
    }
}
