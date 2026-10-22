@extends('layouts.app')
@section('titulo', 'Lotes por vencer')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Lotes por vencer</h1>
        <p class="text-muted mb-0">Lotes con saldo que vencen dentro de los próximos {{ $dias }} días, ordenados por fecha de vencimiento (FEFO, control K4).</p>
    </div>
    <form method="GET" action="{{ route('lotes.por-vencer') }}" class="d-flex align-items-end gap-2">
        <div>
            <label for="dias" class="form-label small mb-0">Días de anticipación</label>
            <input type="number" min="1" max="365" name="dias" id="dias" value="{{ $dias }}" class="form-control form-control-sm" style="width: 7rem">
        </div>
        <button class="btn btn-sm btn-outline-success"><i class="bi bi-funnel"></i> Aplicar</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Lotes en la lista</div><div class="kpi">{{ $lotes->count() }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3 {{ $vencidos > 0 ? 'border border-danger' : '' }}">
        <div class="text-muted small">Ya vencidos</div><div class="kpi {{ $vencidos > 0 ? 'text-danger' : '' }}">{{ $vencidos }}</div></div></div>
    <div class="col-12 col-lg-6"><div class="card p-3">
        <div class="text-muted small">Valor en riesgo (saldo × costo unitario)</div><div class="kpi">${{ number_format($valorTotal, 0, ',', '.') }}</div></div></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>Vencimiento</th><th>Días restantes</th><th>Producto</th><th>Lote</th><th class="text-end">Saldo</th><th class="text-end">Costo unitario</th><th class="text-end">Valor en riesgo</th></tr></thead>
    <tbody>
    @forelse($lotes as $lote)
        @php($restantes = $lote->diasRestantes())
        <tr class="{{ $restantes < 0 ? 'table-danger' : ($restantes <= 2 ? 'table-warning' : '') }}">
            <td>{{ $lote->fecha_vencimiento->format('d-m-Y') }}</td>
            <td>
                @if($restantes < 0)
                    <span class="badge bg-danger"><i class="bi bi-exclamation-octagon"></i> Vencido hace {{ abs($restantes) }} {{ abs($restantes) === 1 ? 'día' : 'días' }}</span>
                @elseif($restantes === 0)
                    <span class="badge bg-danger">Vence hoy</span>
                @else
                    <span class="badge {{ $restantes <= 2 ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $restantes }} {{ $restantes === 1 ? 'día' : 'días' }}</span>
                @endif
            </td>
            <td>{{ $lote->producto->nombre }}<br><code class="small">{{ $lote->producto->codigo_barras }}</code></td>
            <td><code>{{ $lote->codigo_lote }}</code></td>
            <td class="text-end fw-bold">{{ $lote->cantidad_disponible }}</td>
            <td class="text-end">${{ number_format($lote->costo_unitario, 0, ',', '.') }}</td>
            <td class="text-end">${{ number_format($lote->valorEnRiesgo(), 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> No hay lotes que venzan en los próximos {{ $dias }} días.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<p class="form-text mt-2">Los productos no perecibles (sin fecha de vencimiento) no aparecen en esta lista. El registro de mermas se incorpora en la HU13.</p>
@endsection
