<?php

namespace App\Http\Controllers;

use App\Models\Conteo;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU14: conteo cíclico por clase ABC y ajustes (RF7, K6, OE1 M1.2). */
class ConteoController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    /** Programación por clase (A semanal, B quincenal, C mensual), exactitud e historial. */
    public function index(): View
    {
        return view('conteos.index', [
            'programacion' => Conteo::programacion(),
            'exactitud' => Conteo::exactitudPeriodo(30),
            'conteos' => Conteo::with('usuario')->latest('fecha_conteo')->latest('id')->paginate(15),
        ]);
    }

    /** Planilla de conteo: los productos activos de la clase con su stock según el sistema. */
    public function create(Request $request): View
    {
        $clase = $request->validate([
            'clase' => ['required', Rule::in(array_keys(Conteo::FRECUENCIA_DIAS))],
        ])['clase'];

        return view('conteos.create', [
            'clase' => $clase,
            'frecuencia' => Conteo::FRECUENCIA_ETIQUETA[$clase],
            'productos' => Producto::conStock()
                ->where('activo', true)
                ->where('clase_abc', $clase)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'clase_abc' => ['required', Rule::in(array_keys(Conteo::FRECUENCIA_DIAS))],
            'conteos' => ['required', 'array', 'min:1'],
            'conteos.*.cantidad' => ['required', 'integer', 'min:0', 'max:100000'],
            'conteos.*.motivo' => ['nullable', 'string', 'max:80'],
        ]);

        // El servicio compara con el stock real dentro de la transacción y exige el motivo de cada diferencia
        $conteo = $this->inventario->registrarConteo($request->user(), $datos['clase_abc'], $datos['conteos']);

        return redirect()->route('conteos.show', $conteo)->with('ok', sprintf(
            'Conteo N.º %d de la clase %s registrado: %d producto(s) contados, %d con diferencia. Exactitud %s %%.',
            $conteo->id,
            $conteo->clase_abc,
            $conteo->productos_contados,
            $conteo->productos_con_diferencia,
            number_format($conteo->exactitud(), 1, ',', '.'),
        ));
    }

    public function show(Conteo $conteo): View
    {
        $conteo->load(['usuario', 'detalles.producto']);
        $conteo->setRelation('detalles', $conteo->detalles->sortBy(fn ($d) => $d->producto->nombre)->values());

        return view('conteos.show', ['conteo' => $conteo]);
    }
}
