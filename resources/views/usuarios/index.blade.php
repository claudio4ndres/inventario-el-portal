@extends('layouts.app')
@section('titulo', 'Usuarios')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Usuarios</h1>
    <a class="btn btn-success" href="{{ route('usuarios.create') }}"><i class="bi bi-person-plus"></i> Nuevo usuario</a>
</div>
<form class="mb-3" method="GET">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input class="form-control" name="q" value="{{ $busqueda }}" placeholder="Nombre o correo">
        <button class="btn btn-outline-secondary">Buscar</button>
    </div>
</form>
<div class="card">
<div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr>
        <th>Nombre</th><th>Correo</th><th>Rol</th><th class="text-end">Movimientos</th><th>Estado</th><th></th>
    </tr></thead>
    <tbody>
    @forelse($usuarios as $usuario)
        @php($esYo = auth()->user()->is($usuario))
        <tr class="{{ $usuario->activo ? '' : 'text-muted' }}">
            <td>{{ $usuario->name }} @if($esYo)<span class="badge bg-info text-dark">usted</span>@endif</td>
            <td>{{ $usuario->email }}</td>
            <td><span class="badge bg-secondary">{{ $usuario->rol }}</span></td>
            <td class="text-end">{{ $usuario->movimientos_count }}</td>
            <td>
                @if($usuario->activo)<span class="badge bg-success">Activo</span>
                @else<span class="badge bg-dark">Inactivo</span>@endif
            </td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('usuarios.edit', $usuario) }}" title="Editar"><i class="bi bi-pencil"></i></a>
                @unless($esYo)
                    <form class="d-inline" method="POST" action="{{ route('usuarios.toggle', $usuario) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-{{ $usuario->activo ? 'danger' : 'success' }}" title="{{ $usuario->activo ? 'Desactivar' : 'Activar' }}">
                            <i class="bi bi-{{ $usuario->activo ? 'toggle-on' : 'toggle-off' }}"></i></button>
                    </form>
                    @if($usuario->movimientos_count === 0)
                        <form class="d-inline" method="POST" action="{{ route('usuarios.destroy', $usuario) }}"
                              onsubmit="return confirm('¿Eliminar al usuario {{ $usuario->name }}? Esta acción no se puede deshacer.')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                @endunless
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
</div>
<p class="small text-muted mt-2">Los usuarios con movimientos registrados no se eliminan: se desactivan para conservar la bitácora.</p>
<div class="mt-3">{{ $usuarios->links('pagination::bootstrap-5') }}</div>
@endsection
