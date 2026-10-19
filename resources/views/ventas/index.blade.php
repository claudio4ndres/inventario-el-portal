@extends('layouts.app')
@section('titulo', 'Ventas')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Ventas</h1>
    <a class="btn btn-success" href="{{ route('ventas.create') }}"><i class="bi bi-cart-plus"></i> Nueva venta</a>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>N.º</th><th>Fecha</th><th>Vendedor</th><th class="text-end">Productos</th><th>Medio de pago</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($ventas as $v)
        <tr>
            <td>{{ $v->id }}</td>
            <td>{{ $v->fecha_venta->format('d-m-Y H:i') }}</td>
            <td>{{ $v->usuario->name }}</td>
            <td class="text-end">{{ $v->detalles_count }}</td>
            <td>{{ ucfirst($v->medio_pago) }}</td>
            <td class="text-end fw-bold">${{ number_format($v->total, 0, ',', '.') }}</td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('ventas.show', $v) }}">Ver</a></td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay ventas.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $ventas->links('pagination::bootstrap-5') }}</div>
@endsection
