@extends('layouts.app')
@section('titulo', 'Alertas de reposición')
@section('contenido')
<div class="d-flex justify-content-between align-items-start mb-3 gap-2 flex-wrap">
    <div>
        <h1 class="h3 mb-1">Alertas de reposición</h1>
        <p class="text-muted mb-0">Productos activos con stock igual o inferior a su punto de reorden (control K3).</p>
    </div>
    @if(auth()->user()->tieneRol('administrador') && $productos->contains(fn ($p) => $p->orden_abierta_id === null && $p->proveedor_sugerido !== null))
        <form method="POST" action="{{ route('ordenes-compra.sugerir') }}">@csrf
            <button class="btn btn-success" id="generar-oc"><i class="bi bi-cart-plus"></i> Generar OC sugeridas</button>
        </form>
    @endif
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>ABC</th><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Punto de reorden</th><th class="text-end">Faltan para el mínimo</th><th class="text-end">Sugerido</th><th>Proveedor</th><th>Estado</th></tr></thead>
    <tbody>
    @forelse($productos as $p)
        @php($stock = (int) $p->stock)
        <tr>
            <td><span class="badge bg-secondary">{{ $p->clase_abc }}</span></td>
            <td>{{ $p->nombre }}<br><code class="small">{{ $p->codigo_barras }}</code></td>
            <td class="text-end fw-bold">{{ $stock }}</td>
            <td class="text-end">{{ $p->punto_reorden }}</td>
            <td class="text-end">{{ max($p->punto_reorden - $stock, 0) }}</td>
            <td class="text-end">{{ $p->cantidad_sugerida }}</td>
            <td>{{ $p->proveedor_sugerido ?? 'Sin proveedor activo' }}</td>
            <td>
                @if($stock === 0)<span class="badge bg-danger">Quiebre</span>@else<span class="badge bg-warning text-dark">Reponer</span>@endif
                @if($p->orden_abierta_id)
                    @if(auth()->user()->tieneRol('administrador'))
                        <a class="badge bg-info text-dark text-decoration-none" href="{{ route('ordenes-compra.show', $p->orden_abierta_id) }}">En OC N.º {{ $p->orden_abierta_id }}</a>
                    @else
                        <span class="badge bg-info text-dark">En OC N.º {{ $p->orden_abierta_id }}</span>
                    @endif
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> No hay productos bajo su punto de reorden.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<p class="form-text mt-2">Sugerido = punto de reorden × 2 − stock. El proveedor sugerido es el de la última recepción del producto; los productos sin proveedor activo no se incluyen en la orden de compra.</p>
@endsection
