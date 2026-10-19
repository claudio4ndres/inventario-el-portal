@extends('layouts.app')
@section('titulo', 'Alertas de reposición')
@section('contenido')
<h1 class="h3 mb-1">Alertas de reposición</h1>
<p class="text-muted">Productos activos con stock igual o inferior a su punto de reorden (control K3).</p>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>ABC</th><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Punto de reorden</th><th class="text-end">Faltan para el mínimo</th><th>Estado</th></tr></thead>
    <tbody>
    @forelse($productos as $p)
        @php($stock = (int) $p->stock)
        <tr>
            <td><span class="badge bg-secondary">{{ $p->clase_abc }}</span></td>
            <td>{{ $p->nombre }}<br><code class="small">{{ $p->codigo_barras }}</code></td>
            <td class="text-end fw-bold">{{ $stock }}</td>
            <td class="text-end">{{ $p->punto_reorden }}</td>
            <td class="text-end">{{ max($p->punto_reorden - $stock, 0) }}</td>
            <td>@if($stock === 0)<span class="badge bg-danger">Quiebre</span>@else<span class="badge bg-warning text-dark">Reponer</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> No hay productos bajo su punto de reorden.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<p class="form-text mt-2">La cantidad sugerida de compra y la orden de compra se incorporan en la HU11 (siguiente módulo).</p>
@endsection
