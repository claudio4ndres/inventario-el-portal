@extends('layouts.app')
@section('titulo', 'Bitácora de movimientos')
@section('contenido')
<h1 class="h3 mb-3">Bitácora de movimientos</h1>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-6">
        <select class="form-select" name="producto_id">
            <option value="">Todos los productos</option>
            @foreach($productos as $p)
                <option value="{{ $p->id }}" @selected(($filtros['producto_id'] ?? null) == $p->id)>{{ $p->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="tipo">
            <option value="">Todos los tipos</option>
            @foreach(['ENTRADA', 'SALIDA', 'AJUSTE', 'MERMA'] as $t)
                <option value="{{ $t }}" @selected(($filtros['tipo'] ?? null) === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3"><button class="btn btn-outline-secondary w-100">Filtrar</button></div>
</form>
<div class="card"><div class="table-responsive">
<table class="table table-sm table-hover mb-0">
    <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Lote</th><th class="text-end">Cantidad</th><th class="text-end">Stock resultante</th><th>Usuario</th><th>Detalle</th></tr></thead>
    <tbody>
    @forelse($movimientos as $m)
        <tr>
            <td class="text-nowrap">{{ $m->created_at->format('d-m-Y H:i') }}</td>
            <td><span class="badge bg-{{ $m->tipo === 'ENTRADA' ? 'success' : ($m->tipo === 'SALIDA' ? 'primary' : 'warning text-dark') }}">{{ $m->tipo }}</span></td>
            <td>{{ $m->producto->nombre }}</td>
            <td><code>{{ $m->lote->codigo_lote }}</code></td>
            <td class="text-end">{{ $m->tipo === 'ENTRADA' ? '+' : '−' }}{{ $m->cantidad }}</td>
            <td class="text-end fw-bold">{{ $m->stock_resultante }}</td>
            <td>{{ $m->usuario->name }}</td>
            <td class="small text-muted">{{ $m->motivo }}@if($m->costo_total !== null) · costo ${{ number_format($m->costo_total, 0, ',', '.') }}@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Sin movimientos.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $movimientos->links('pagination::bootstrap-5') }}</div>
@endsection
