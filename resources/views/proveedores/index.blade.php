@extends('layouts.app')
@section('titulo', 'Proveedores')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Proveedores</h1>
    <a class="btn btn-success" href="{{ route('proveedores.create') }}"><i class="bi bi-plus-lg"></i> Nuevo proveedor</a>
</div>
<form class="mb-3" method="GET">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input class="form-control" name="q" value="{{ $busqueda }}" placeholder="Razón social o RUT">
        <button class="btn btn-outline-secondary">Buscar</button>
    </div>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr>
        <th>RUT</th><th>Razón social</th><th>Teléfono</th><th>Correo</th><th class="text-end">Recepciones</th><th>Estado</th><th></th>
    </tr></thead>
    <tbody>
    @forelse($proveedores as $proveedor)
        <tr class="{{ $proveedor->activo ? '' : 'text-muted' }}">
            <td class="text-nowrap">{{ $proveedor->rut }}</td>
            <td>{{ $proveedor->razon_social }}</td>
            <td>{{ $proveedor->telefono ?? '—' }}</td>
            <td>{{ $proveedor->email ?? '—' }}</td>
            <td class="text-end">{{ $proveedor->recepciones_count }}</td>
            <td>
                @if($proveedor->activo)<span class="badge bg-success">Activo</span>
                @else<span class="badge bg-dark">Inactivo</span>@endif
            </td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('proveedores.edit', $proveedor) }}" title="Editar"><i class="bi bi-pencil"></i></a>
                <form class="d-inline" method="POST" action="{{ route('proveedores.toggle', $proveedor) }}">@csrf @method('PATCH')
                    <button class="btn btn-sm btn-outline-{{ $proveedor->activo ? 'danger' : 'success' }}" title="{{ $proveedor->activo ? 'Desactivar' : 'Activar' }}">
                        <i class="bi bi-{{ $proveedor->activo ? 'toggle-on' : 'toggle-off' }}"></i></button>
                </form>
                @if($proveedor->recepciones_count === 0)
                    <form class="d-inline" method="POST" action="{{ route('proveedores.destroy', $proveedor) }}"
                          onsubmit="return confirm('¿Eliminar al proveedor {{ $proveedor->razon_social }}? Esta acción no se puede deshacer.')">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No hay proveedores.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
</div>
<p class="small text-muted mt-2">Los proveedores inactivos no aparecen en la recepción. Los que tienen recepciones registradas no se eliminan: se desactivan.</p>
<div class="mt-3">{{ $proveedores->links('pagination::bootstrap-5') }}</div>
@endsection
