@extends('layouts.app')
@section('titulo', 'Recepción N.º '.$recepcion->id)
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0">Recepción N.º {{ $recepcion->id }}</h1>
    <a class="btn btn-outline-secondary" href="{{ route('recepciones.index') }}"><i class="bi bi-arrow-left"></i> Recepciones</a>
</div>
<p class="text-muted">
    {{ $recepcion->proveedor->razon_social }} · documento {{ $recepcion->numero_documento }} ·
    {{ $recepcion->fecha_recepcion->format('d-m-Y H:i') }} · {{ $recepcion->usuario->name }}
    @if($recepcion->ordenCompra) · contra la orden de compra N.º {{ $recepcion->ordenCompra->id }} @endif
</p>

<div class="card mb-3"><div class="table-responsive">
<table class="table mb-0">
    <thead class="table-light"><tr><th>Producto</th><th>Lote</th><th>Vencimiento</th><th class="text-end">Cantidad</th><th class="text-end">Costo unit.</th></tr></thead>
    <tbody>
    @foreach($recepcion->lotes as $lote)
        <tr>
            <td>{{ $lote->producto->nombre }}</td>
            <td><code>{{ $lote->codigo_lote }}</code></td>
            <td>{{ $lote->fecha_vencimiento?->format('d-m-Y') ?? 'No perecible' }}</td>
            <td class="text-end">{{ $lote->cantidad_inicial }}</td>
            <td class="text-end">${{ number_format($lote->costo_unitario, 0, ',', '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>

@if($diferencias->isNotEmpty())
    <h2 class="h5">Diferencias con la orden de compra N.º {{ $recepcion->ordenCompra->id }}</h2>
    @include('ordenes-compra._diferencias', ['diferencias' => $diferencias])
@endif
@endsection
