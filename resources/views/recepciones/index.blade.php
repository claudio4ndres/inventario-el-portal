@extends('layouts.app')
@section('titulo', 'Recepciones')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Recepciones de mercadería</h1>
    <a class="btn btn-success" href="{{ route('recepciones.create') }}"><i class="bi bi-plus-lg"></i> Nueva recepción</a>
</div>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>N.º</th><th>Fecha</th><th>Proveedor</th><th>Documento</th><th>OC</th><th class="text-end">Lotes</th><th>Registrada por</th><th></th></tr></thead>
    <tbody>
    @forelse($recepciones as $r)
        <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->fecha_recepcion->format('d-m-Y H:i') }}</td>
            <td>{{ $r->proveedor->razon_social }}</td>
            <td>{{ $r->numero_documento }}</td>
            <td>{{ $r->orden_compra_id ? 'N.º '.$r->orden_compra_id : '—' }}</td>
            <td class="text-end">{{ $r->lotes_count }}</td>
            <td>{{ $r->usuario->name }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('recepciones.show', $r) }}" title="Ver detalle"><i class="bi bi-eye"></i></a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Aún no hay recepciones.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $recepciones->links('pagination::bootstrap-5') }}</div>
@endsection
