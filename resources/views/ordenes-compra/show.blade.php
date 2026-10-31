@extends('layouts.app')
@section('titulo', 'Orden de compra N.º '.$orden->id)
@section('contenido')
@php($badge = ['borrador' => 'bg-secondary', 'enviada' => 'bg-primary', 'recibida' => 'bg-success'])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0">Orden de compra N.º {{ $orden->id }}
        <span class="badge {{ $badge[$orden->estado] ?? 'bg-secondary' }} fs-6 align-middle" id="estado-oc">{{ $orden->etiquetaEstado() }}</span></h1>
    <a class="btn btn-outline-secondary" href="{{ route('ordenes-compra.index') }}"><i class="bi bi-arrow-left"></i> Órdenes de compra</a>
</div>
<p class="text-muted">
    {{ $orden->proveedor->razon_social }} ({{ $orden->proveedor->rut }}) · creada el {{ $orden->created_at->format('d-m-Y H:i') }} por {{ $orden->usuario->name }}
    @if($orden->fecha_envio) · enviada el {{ $orden->fecha_envio->format('d-m-Y H:i') }} @endif
    @if($orden->fecha_recepcion) · recibida el {{ $orden->fecha_recepcion->format('d-m-Y H:i') }} @endif
</p>

@if($orden->esBorrador())
    <form method="POST" action="{{ route('ordenes-compra.update', $orden) }}">
    @csrf @method('PUT')
@endif
<div class="card mb-3"><div class="table-responsive">
<table class="table mb-0">
    <thead class="table-light"><tr><th>Producto</th><th>Código</th><th class="text-end">Sugerido</th><th class="text-end" style="width:160px">Cantidad pedida</th></tr></thead>
    <tbody>
    @foreach($orden->detalles as $d)
        <tr>
            <td>{{ $d->producto->nombre }}</td>
            <td><code class="small">{{ $d->producto->codigo_barras }}</code></td>
            <td class="text-end">{{ $d->cantidad_sugerida }}</td>
            <td class="text-end">
                @if($orden->esBorrador())
                    <input class="form-control text-end" type="number" min="1" max="100000" required
                           name="detalles[{{ $d->id }}][cantidad]" value="{{ old('detalles.'.$d->id.'.cantidad', $d->cantidad) }}">
                @else
                    {{ $d->cantidad }}
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>
@if($orden->esBorrador())
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-outline-primary"><i class="bi bi-save"></i> Guardar cantidades</button>
    </div>
    </form>
    <form method="POST" action="{{ route('ordenes-compra.enviar', $orden) }}" onsubmit="return confirm('¿Marcar la orden como enviada al proveedor? Ya no podrá modificar las cantidades.')">
        @csrf @method('PATCH')
        <button class="btn btn-success"><i class="bi bi-send"></i> Marcar como enviada</button>
    </form>
    <p class="form-text mt-2">Guarde las cantidades antes de enviarla. Una vez enviada, la orden se puede asociar a la recepción de la mercadería.</p>
@elseif($orden->estado === 'enviada')
    <p class="form-text">Pendiente de recepción. El bodeguero la selecciona al registrar la recepción de mercadería.</p>
@endif

@if($diferencias->isNotEmpty())
    <h2 class="h5 mt-4">Diferencias entre lo pedido y lo recibido</h2>
    @include('ordenes-compra._diferencias', ['diferencias' => $diferencias])
    @foreach($orden->recepciones as $r)
        <p class="form-text">Recepción N.º {{ $r->id }} · documento {{ $r->numero_documento }} · {{ $r->fecha_recepcion->format('d-m-Y H:i') }}</p>
    @endforeach
@endif
@endsection
