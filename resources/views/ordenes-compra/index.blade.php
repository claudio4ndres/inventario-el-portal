@extends('layouts.app')
@section('titulo', 'Órdenes de compra')
@section('contenido')
@php($badge = ['borrador' => 'bg-secondary', 'enviada' => 'bg-primary', 'recibida' => 'bg-success'])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Órdenes de compra</h1>
        <p class="text-muted mb-0">Sugeridas a partir de las alertas de reposición (HU11 · K3). Estados: borrador, enviada y recibida.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('alertas.index') }}"><i class="bi bi-bell"></i> Ver alertas</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0">Compra sugerida pendiente</h2>
            @if($sugerencias->contains(fn ($p) => $p->proveedor_sugerido !== null))
                <form method="POST" action="{{ route('ordenes-compra.sugerir') }}">@csrf
                    <button class="btn btn-success"><i class="bi bi-cart-plus"></i> Generar OC sugeridas</button>
                </form>
            @endif
        </div>
        <p class="form-text">Sugerido = punto de reorden × 2 − stock. Solo aparecen los productos en alerta que no están en una orden de compra abierta.</p>
    </div>
    <div class="table-responsive">
    <table class="table table-hover mb-0" id="tabla-sugerencias">
        <thead class="table-light"><tr><th>Producto</th><th class="text-end">Stock</th><th class="text-end">P. reorden</th><th class="text-end">Sugerido</th><th>Proveedor</th></tr></thead>
        <tbody>
        @forelse($sugerencias as $p)
            <tr>
                <td>{{ $p->nombre }}</td>
                <td class="text-end">{{ $p->stock }}</td>
                <td class="text-end">{{ $p->punto_reorden }}</td>
                <td class="text-end fw-bold">{{ $p->cantidad_sugerida }}</td>
                <td>@if($p->proveedor_sugerido){{ $p->proveedor_sugerido->razon_social }}@else<span class="text-muted">Sin proveedor activo: no se incluye</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-success py-3"><i class="bi bi-check-circle"></i> No hay compras pendientes de sugerir.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>N.º</th><th>Creada</th><th>Proveedor</th><th>Estado</th><th class="text-end">Productos</th><th class="text-end">Unidades</th><th>Creada por</th><th></th></tr></thead>
    <tbody>
    @forelse($ordenes as $o)
        <tr>
            <td>{{ $o->id }}</td>
            <td class="text-nowrap">{{ $o->created_at->format('d-m-Y H:i') }}</td>
            <td>{{ $o->proveedor->razon_social }}</td>
            <td><span class="badge {{ $badge[$o->estado] ?? 'bg-secondary' }}">{{ $o->etiquetaEstado() }}</span></td>
            <td class="text-end">{{ $o->detalles_count }}</td>
            <td class="text-end">{{ (int) $o->unidades }}</td>
            <td>{{ $o->usuario->name }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('ordenes-compra.show', $o) }}" title="Ver"><i class="bi bi-eye"></i></a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Aún no hay órdenes de compra.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $ordenes->links('pagination::bootstrap-5') }}</div>
@endsection
