<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** HU01: inicio de sesión por rol (K8). */
class AuthController extends Controller
{
    private const MAX_INTENTOS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Limita intentos fallidos por correo + IP (protección contra fuerza bruta, Ley 21.459)
        $clave = Str::lower($credenciales['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Intente nuevamente en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        // Solo usuarios activos pueden ingresar
        if (! Auth::attempt([...$credenciales, 'activo' => true], $request->boolean('recordar'))) {
            RateLimiter::hit($clave);
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son válidas.',
            ]);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();   // evita fijación de sesión

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
