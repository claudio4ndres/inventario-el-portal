<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Rules\RutChileno;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** HU09: gestión de proveedores con RUT validado (RF3 · K2). Solo el administrador accede. */
class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $proveedores = Proveedor::query()
            ->withCount('recepciones')
            ->when($busqueda !== '', fn ($q) => $q->where(function ($q) use ($busqueda) {
                $q->where('razon_social', 'like', "%{$busqueda}%")
                    ->orWhere('rut', 'like', "%{$busqueda}%");
            }))
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores', 'busqueda'));
    }

    public function create(): View
    {
        return view('proveedores.form', ['proveedor' => new Proveedor(['activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $proveedor = Proveedor::create($this->validar($request));

        return redirect()->route('proveedores.index')
            ->with('ok', "Proveedor «{$proveedor->razon_social}» creado.");
    }

    public function edit(Proveedor $proveedor): View
    {
        return view('proveedores.form', compact('proveedor'));
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($this->validar($request, $proveedor));

        return redirect()->route('proveedores.index')
            ->with('ok', "Proveedor «{$proveedor->razon_social}» actualizado.");
    }

    /** Un proveedor inactivo deja de aparecer en la recepción de mercadería. */
    public function toggle(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update(['activo' => ! $proveedor->activo]);

        return back()->with('ok', "Proveedor «{$proveedor->razon_social}» ".($proveedor->activo ? 'activado.' : 'desactivado.'));
    }

    /** Solo se elimina un proveedor sin recepciones; si tiene historia se desactiva (FK RESTRICT). */
    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        if ($proveedor->tieneHistoria()) {
            return back()->withErrors([
                'proveedor' => "El proveedor «{$proveedor->razon_social}» tiene recepciones registradas: desactívelo en vez de eliminarlo.",
            ]);
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')
            ->with('ok', "Proveedor «{$proveedor->razon_social}» eliminado.");
    }

    private function validar(Request $request, ?Proveedor $proveedor = null): array
    {
        // Si el RUT es válido se normaliza a "76.111.111-6" antes de comprobar la unicidad:
        // así "76111111-6" y "76.111.111-6" se reconocen como el mismo proveedor.
        $rut = trim((string) $request->input('rut'));
        $request->merge([
            'rut' => Rut::esValido($rut) ? Rut::formatear($rut) : $rut,
            'email' => ($email = trim((string) $request->input('email'))) === '' ? null : mb_strtolower($email),
        ]);

        $datos = $request->validate([
            'rut' => [
                'required', 'string', 'max:12', new RutChileno,
                Rule::unique('proveedores', 'rut')->ignore($proveedor?->id),
            ],
            'razon_social' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s]{8,19}$/'],
            'email' => ['nullable', 'string', 'email', 'max:120'],
        ]);

        $datos['activo'] = $request->boolean('activo', true);

        return $datos;
    }
}
