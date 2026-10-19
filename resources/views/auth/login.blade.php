@extends('layouts.app')
@section('titulo', 'Ingresar')
@section('contenido')
<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">
        <div class="card p-4">
            <div class="text-center mb-3">
                <i class="bi bi-shop text-success" style="font-size:3rem"></i>
                <h1 class="h4 mt-2">Almacén El Portal</h1>
                <p class="text-muted small mb-0">Gestión de inventario y ventas</p>
            </div>
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Correo</label>
                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control" type="password" id="password" name="password" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="recordar" name="recordar" value="1">
                    <label class="form-check-label" for="recordar">Recordarme</label>
                </div>
                <button class="btn btn-success w-100">Ingresar</button>
            </form>
        </div>
    </div>
</div>
@endsection
