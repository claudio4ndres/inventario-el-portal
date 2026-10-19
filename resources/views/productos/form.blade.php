@extends('layouts.app')
@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')
@section('contenido')
<h1 class="h3 mb-3">{{ $producto->exists ? 'Editar producto' : 'Nuevo producto' }}</h1>
<div class="card p-4">
<form method="POST" action="{{ $producto->exists ? route('productos.update', $producto) : route('productos.store') }}">
    @csrf
    @if($producto->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="codigo_barras">Código de barras</label>
            <input class="form-control @error('codigo_barras') is-invalid @enderror" id="codigo_barras" name="codigo_barras"
                   value="{{ old('codigo_barras', $producto->codigo_barras) }}" required maxlength="32" autofocus>
            @error('codigo_barras')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="form-label" for="nombre">Nombre</label>
            <input class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre"
                   value="{{ old('nombre', $producto->nombre) }}" required maxlength="120">
            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="precio_venta">Precio de venta (IVA incluido)</label>
            <div class="input-group"><span class="input-group-text">$</span>
                <input class="form-control @error('precio_venta') is-invalid @enderror" type="number" min="1" id="precio_venta" name="precio_venta"
                       value="{{ old('precio_venta', $producto->precio_venta) }}" required>
                @error('precio_venta')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="punto_reorden">Punto de reorden (unidades)</label>
            <input class="form-control @error('punto_reorden') is-invalid @enderror" type="number" min="0" id="punto_reorden" name="punto_reorden"
                   value="{{ old('punto_reorden', $producto->punto_reorden ?? 0) }}" required>
            <div class="form-text">Al llegar a este stock se genera una alerta de reposición.</div>
            @error('punto_reorden')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="clase_abc">Clase ABC</label>
            <select class="form-select" id="clase_abc" name="clase_abc">
                @foreach(['A' => 'A · alta importancia', 'B' => 'B · media', 'C' => 'C · baja'] as $valor => $texto)
                    <option value="{{ $valor }}" @selected(old('clase_abc', $producto->clase_abc) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <input type="hidden" name="activo" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" @checked(old('activo', $producto->activo ?? true))>
                <label class="form-check-label" for="activo">Producto activo (disponible para venta)</label>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-success"><i class="bi bi-save"></i> Guardar</button>
        <a class="btn btn-outline-secondary" href="{{ route('productos.index') }}">Cancelar</a>
    </div>
</form>
</div>
@endsection
