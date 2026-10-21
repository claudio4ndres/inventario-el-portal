@extends('layouts.app')
@section('titulo', $proveedor->exists ? 'Editar proveedor' : 'Nuevo proveedor')
@section('contenido')
<h1 class="h3 mb-3">{{ $proveedor->exists ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
<div class="card p-4">
<form method="POST" action="{{ $proveedor->exists ? route('proveedores.update', $proveedor) : route('proveedores.store') }}">
    @csrf
    @if($proveedor->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="rut">RUT</label>
            <input class="form-control @error('rut') is-invalid @enderror" id="rut" name="rut"
                   value="{{ old('rut', $proveedor->rut) }}" required maxlength="12" placeholder="76.111.111-6" autofocus>
            <div class="form-text">Con o sin puntos; se valida el dígito verificador.</div>
            @error('rut')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="form-label" for="razon_social">Razón social</label>
            <input class="form-control @error('razon_social') is-invalid @enderror" id="razon_social" name="razon_social"
                   value="{{ old('razon_social', $proveedor->razon_social) }}" required maxlength="120">
            @error('razon_social')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="telefono">Teléfono</label>
            <input class="form-control @error('telefono') is-invalid @enderror" id="telefono" name="telefono"
                   value="{{ old('telefono', $proveedor->telefono) }}" maxlength="20" placeholder="+56912345678">
            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="form-label" for="email">Correo</label>
            <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email"
                   value="{{ old('email', $proveedor->email) }}" maxlength="120">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <input type="hidden" name="activo" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                       @checked(old('activo', $proveedor->activo ?? true))>
                <label class="form-check-label" for="activo">Proveedor activo (aparece en la recepción de mercadería)</label>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
        <a class="btn btn-outline-secondary" href="{{ route('proveedores.index') }}">Cancelar</a>
    </div>
</form>
</div>
@endsection
