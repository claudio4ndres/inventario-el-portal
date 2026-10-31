@extends('layouts.app')
@section('titulo', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('contenido')
<h1 class="h3 mb-3">{{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
<div class="card p-4">
<form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
    @csrf
    @if($usuario->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Nombre</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                   value="{{ old('name', $usuario->name) }}" required maxlength="100" autofocus>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Correo</label>
            <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email"
                   value="{{ old('email', $usuario->email) }}" required maxlength="150">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password">Contraseña</label>
            <input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password"
                   minlength="8" autocomplete="new-password" @required(! $usuario->exists)>
            <div class="form-text">Mínimo 8 caracteres.@if($usuario->exists) Déjela vacía para conservar la actual.@endif</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation"
                   minlength="8" autocomplete="new-password" @required(! $usuario->exists)>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="rol">Rol</label>
            <select class="form-select @error('rol') is-invalid @enderror" id="rol" name="rol">
                @foreach(['administrador' => 'Administrador', 'vendedor' => 'Vendedor (caja)', 'bodeguero' => 'Bodeguero'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected(old('rol', $usuario->rol) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
            @error('rol')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <input type="hidden" name="activo" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input @error('activo') is-invalid @enderror" type="checkbox" id="activo" name="activo" value="1"
                       @checked(old('activo', $usuario->activo ?? true))>
                <label class="form-check-label" for="activo">Usuario activo (puede iniciar sesión)</label>
                @error('activo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
        <a class="btn btn-outline-secondary" href="{{ route('usuarios.index') }}">Cancelar</a>
    </div>
</form>
</div>
@endsection
