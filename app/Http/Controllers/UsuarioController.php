<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** HU08: gestión de usuarios y roles (RF9 · K8). Solo el administrador accede. */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q', ''));

        $usuarios = User::query()
            ->withCount('movimientos')
            ->when($busqueda !== '', fn ($q) => $q->where(function ($q) use ($busqueda) {
                $q->where('name', 'like', "%{$busqueda}%")
                    ->orWhere('email', 'like', "%{$busqueda}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('usuarios.index', compact('usuarios', 'busqueda'));
    }

    public function create(): View
    {
        return view('usuarios.form', ['usuario' => new User(['rol' => User::VENDEDOR, 'activo' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = User::create($this->validar($request));

        return redirect()->route('usuarios.index')
            ->with('ok', "Usuario «{$usuario->name}» creado.");
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.form', compact('usuario'));
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validar($request, $usuario);

        if (! $datos['activo'] && $this->esElMismo($request, $usuario)) {
            throw ValidationException::withMessages(['activo' => 'No puede desactivar su propia cuenta.']);
        }

        $usuario->update($datos);

        return redirect()->route('usuarios.index')
            ->with('ok', "Usuario «{$usuario->name}» actualizado.");
    }

    public function toggle(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->activo && $this->esElMismo($request, $usuario)) {
            return back()->withErrors(['usuario' => 'No puede desactivar su propia cuenta.']);
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        return back()->with('ok', "Usuario «{$usuario->name}» ".($usuario->activo ? 'activado.' : 'desactivado.'));
    }

    /**
     * Solo se elimina un usuario sin historia. Si tiene movimientos, ventas o recepciones
     * se debe desactivar, para que la bitácora (K8) no quede con referencias huérfanas.
     */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($this->esElMismo($request, $usuario)) {
            return back()->withErrors(['usuario' => 'No puede eliminar su propia cuenta.']);
        }

        if ($usuario->tieneHistoria()) {
            return back()->withErrors([
                'usuario' => "El usuario «{$usuario->name}» tiene movimientos registrados: desactívelo en vez de eliminarlo.",
            ]);
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('ok', "Usuario «{$usuario->name}» eliminado.");
    }

    private function esElMismo(Request $request, User $usuario): bool
    {
        return $request->user()->is($usuario);
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        // El correo se normaliza a minúsculas antes de validar: así la unicidad no depende
        // de la intercalación del motor (SQLite distingue mayúsculas; MySQL no).
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            // Al editar, la contraseña es opcional: si se deja vacía se conserva la actual
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8', 'max:72', 'confirmed'],
            'rol' => ['required', Rule::in(User::ROLES)],
        ]);

        $datos['activo'] = $request->boolean('activo', true);

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        return $datos;
    }
}
