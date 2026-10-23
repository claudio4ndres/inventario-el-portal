<?php

namespace App\Http\Controllers;

use App\Exceptions\SaldoLoteInsuficienteException;
use App\Models\Lote;
use App\Models\Movimiento;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU13: registro de mermas con cantidad, costo y motivo (RF6, K7, OE3). */
class MermaController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function index(): View
    {
        $mermas = Movimiento::with(['producto', 'lote', 'usuario'])
            ->where('tipo', Movimiento::MERMA)
            ->latest('id')
            ->paginate(25);

        return view('mermas.index', [
            'mermas' => $mermas,
            'costoTotal' => (int) Movimiento::where('tipo', Movimiento::MERMA)->sum('costo_total'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('mermas.create', [
            // Solo lotes con saldo, en orden FEFO para que los próximos a vencer queden arriba
            'lotes' => Lote::with('producto')
                ->where('cantidad_disponible', '>', 0)
                ->orderByRaw('fecha_vencimiento IS NULL')
                ->orderBy('fecha_vencimiento')
                ->orderBy('id')
                ->get(),
            'loteSeleccionado' => old('lote_id', $request->integer('lote_id') ?: null),
            'motivos' => Movimiento::MOTIVOS_MERMA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // El máximo de la cantidad es el saldo actual del lote elegido
        $saldo = (int) Lote::whereKey($request->integer('lote_id'))->value('cantidad_disponible');

        $datos = $request->validate([
            'lote_id' => ['required', 'integer', Rule::exists('lotes', 'id')->where(fn ($q) => $q->where('cantidad_disponible', '>', 0))],
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.$saldo],
            'motivo_merma' => ['required', Rule::in(array_keys(Movimiento::MOTIVOS_MERMA))],
            'observacion' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $movimiento = $this->inventario->registrarMerma(
                $request->user(),
                (int) $datos['lote_id'],
                (int) $datos['cantidad'],
                $datos['motivo_merma'],
                $datos['observacion'] ?? null,
            );
        } catch (SaldoLoteInsuficienteException $e) {
            // El saldo cambió entre la validación y la transacción (por ejemplo, una venta simultánea)
            return back()->withInput()->withErrors(['cantidad' => $e->getMessage()]);
        }

        return redirect()->route('mermas.index')->with('ok', sprintf(
            'Merma registrada: %d unidad(es) del lote %s, costo $%s.',
            $movimiento->cantidad,
            $movimiento->lote->codigo_lote,
            number_format($movimiento->costo_total, 0, ',', '.'),
        ));
    }
}
