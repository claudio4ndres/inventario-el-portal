@extends('layouts.app')
@section('titulo', 'Mermas')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Mermas</h1>
        <p class="text-muted mb-0">Pérdidas registradas por vencimiento, daño, robo u otro motivo (control K7).</p>
    </div>
    <a class="btn btn-success" href="{{ route('mermas.create') }}"><i class="bi bi-plus-circle"></i> Registrar merma</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Mermas registradas</div><div class="kpi">{{ $mermas->total() }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Costo total</div><div class="kpi" id="kpi-costo-mermas">${{ number_format($costoTotal, 0, ',', '.') }}</div></div></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Fecha</th><th>Producto</th><th>Lote</th><th>Motivo</th><th class="text-end">Cantidad</th><th class="text-end">Costo</th><th>Usuario</th><th>Detalle</th></tr></thead>
    <tbody>
    @forelse($mermas as $m)
        <tr>
            <td class="text-nowrap">{{ $m->created_at->format('d-m-Y H:i') }}</td>
            <td>{{ $m->producto->nombre }}</td>
            <td><code>{{ $m->lote->codigo_lote }}</code></td>
            <td><span class="badge {{ $m->motivo_merma === 'vencimiento' ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $m->motivoMermaEtiqueta() }}</span></td>
            <td class="text-end">{{ $m->cantidad }}</td>
            <td class="text-end">${{ number_format($m->costo_total, 0, ',', '.') }}</td>
            <td>{{ $m->usuario->name }}</td>
            <td class="small text-muted">{{ $m->motivo }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Sin mermas registradas.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $mermas->links('pagination::bootstrap-5') }}</div>
@endsection
