@extends('layouts.app')
@section('titulo', 'Productos')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Productos</h1>
    @if(auth()->user()->tieneRol('administrador'))
        <a class="btn btn-success" href="{{ route('productos.create') }}"><i class="bi bi-plus-lg"></i> Nuevo producto</a>
    @endif
</div>
<form class="mb-3" method="GET">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
        <input class="form-control" name="q" value="{{ $busqueda }}" placeholder="Escanee el código o escriba el nombre">
        <button class="btn btn-outline-secondary">Buscar</button>
    </div>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr>
        <th>Código</th><th>Nombre</th><th class="text-end">Precio</th><th class="text-end">Stock</th>
        <th class="text-end">P. reorden</th><th>ABC</th><th>Estado</th>@if(auth()->user()->tieneRol('administrador'))<th></th>@endif
    </tr></thead>
    <tbody>
    @forelse($productos as $p)
        @php($stock = (int) $p->stock)
        <tr class="{{ $p->activo ? '' : 'text-muted' }}">
            <td><code>{{ $p->codigo_barras }}</code></td>
            <td>{{ $p->nombre }}</td>
            <td class="text-end">${{ number_format($p->precio_venta, 0, ',', '.') }}</td>
            <td class="text-end fw-bold">{{ $stock }}</td>
            <td class="text-end">{{ $p->punto_reorden }}</td>
            <td><span class="badge bg-secondary">{{ $p->clase_abc }}</span></td>
            <td>
                @if(! $p->activo)<span class="badge bg-dark">Inactivo</span>
                @elseif($p->punto_reorden > 0 && $stock <= $p->punto_reorden)<span class="badge bg-danger">Bajo mínimo</span>
                @else<span class="badge bg-success">OK</span>@endif
            </td>
            @if(auth()->user()->tieneRol('administrador'))
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('productos.edit', $p) }}" title="Editar"><i class="bi bi-pencil"></i></a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('movimientos.index', ['producto_id' => $p->id]) }}" title="Bitácora"><i class="bi bi-clock-history"></i></a>
                <form class="d-inline" method="POST" action="{{ route('productos.toggle', $p) }}">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-{{ $p->activo ? 'danger' : 'success' }}" title="{{ $p->activo ? 'Desactivar' : 'Activar' }}">
                        <i class="bi bi-{{ $p->activo ? 'toggle-on' : 'toggle-off' }}"></i></button>
                </form>
            </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">No hay productos.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
</div>
<div class="mt-3">{{ $productos->links('pagination::bootstrap-5') }}</div>
@endsection
