<?php

use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\MermaController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\PuntoReordenController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

// HU01: autenticación
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', PanelController::class)->name('panel');

    // Consulta de productos: todos los roles
    Route::get('/productos', [ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');

    // HU02: mantenedor de productos, HU10: punto de reorden, HU07: bitácora, HU08: usuarios y HU09: proveedores -> solo administrador
    Route::middleware('rol:administrador')->group(function () {
        Route::get('/productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::post('/productos', [ProductoController::class, 'store'])->name('productos.store');
        Route::get('/productos/{producto}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::put('/productos/{producto}', [ProductoController::class, 'update'])->name('productos.update');
        Route::patch('/productos/{producto}/estado', [ProductoController::class, 'toggle'])->name('productos.toggle');

        // HU10: botón "Recalcular" del punto de reorden
        Route::post('/productos/recalcular-reorden', PuntoReordenController::class)->name('productos.recalcular-reorden');

        Route::get('/movimientos', [MovimientoController::class, 'index'])->name('movimientos.index');

        // HU08: gestión de usuarios y roles
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'toggle'])->name('usuarios.toggle');
        Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

        // HU09: gestión de proveedores
        Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/proveedores/crear', [ProveedorController::class, 'create'])->name('proveedores.create');
        Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
        Route::get('/proveedores/{proveedor}/editar', [ProveedorController::class, 'edit'])->name('proveedores.edit');
        Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');
        Route::patch('/proveedores/{proveedor}/estado', [ProveedorController::class, 'toggle'])->name('proveedores.toggle');
        Route::delete('/proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('proveedores.destroy');
    });

    // HU03: recepción, HU06: alertas, HU12: lotes por vencer y HU13: mermas -> administrador y bodeguero
    Route::middleware('rol:administrador,bodeguero')->group(function () {
        Route::get('/recepciones', [RecepcionController::class, 'index'])->name('recepciones.index');
        Route::get('/recepciones/crear', [RecepcionController::class, 'create'])->name('recepciones.create');
        Route::post('/recepciones', [RecepcionController::class, 'store'])->name('recepciones.store');

        Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::get('/lotes/por-vencer', [LoteController::class, 'porVencer'])->name('lotes.por-vencer');

        // HU13: registro de mermas
        Route::get('/mermas', [MermaController::class, 'index'])->name('mermas.index');
        Route::get('/mermas/crear', [MermaController::class, 'create'])->name('mermas.create');
        Route::post('/mermas', [MermaController::class, 'store'])->name('mermas.store');
    });

    // HU04: ventas -> administrador y vendedor
    Route::middleware('rol:administrador,vendedor')->group(function () {
        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::get('/ventas/crear', [VentaController::class, 'create'])->name('ventas.create');
        Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::get('/ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    });
});
