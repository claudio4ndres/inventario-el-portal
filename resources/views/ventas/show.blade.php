@extends('layouts.app')
@section('titulo', 'Venta N.º '.$venta->id)
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Venta N.º {{ $venta->id }}</h1>
    <a class="btn btn-success" href="{{ route('ventas.create') }}"><i class="bi bi-cart-plus"></i> Nueva venta</a>
</div>
<p class="text-muted">{{ $venta->fecha_venta->format('d-m-Y H:i') }} · {{ $venta->usuario->name }} · {{ ucfirst($venta->medio_pago) }}</p>
<div class="card mb-3"><div class="table-responsive">
<table class="table mb-0">
    <thead class="table-light"><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th><th>Lotes descontados (K1)</th></tr></thead>
    <tbody>
    @foreach($venta->detalles as $d)
        <tr>
            <td>{{ $d->producto->nombre }}</td>
            <td class="text-end">{{ $d->cantidad }}</td>
            <td class="text-end">${{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
            <td class="text-end">${{ number_format($d->subtotal, 0, ',', '.') }}</td>
            <td class="small">
                @foreach($d->movimientos as $m)
                    <span class="badge bg-light text-dark border">{{ $m->lote->codigo_lote }}: {{ $m->cantidad }} u.
                        @if($m->lote->fecha_vencimiento) (vence {{ $m->lote->fecha_vencimiento->format('d-m-Y') }}) @endif</span>
                @endforeach
            </td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">${{ number_format($venta->total, 0, ',', '.') }}</th><th></th></tr></tfoot>
</table>
</div></div>
<p class="form-text">Comprobante interno de control de inventario. No reemplaza la boleta electrónica (Ley 20.727).</p>
@endsection
