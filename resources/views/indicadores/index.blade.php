@extends('layouts.app')
@section('titulo', 'Indicadores')
@section('contenido')
@php
    $pct = fn (?float $valor) => $valor === null ? '—' : number_format($valor, 1, ',', '.').' %';
    $clp = fn (int $monto) => '$'.number_format($monto, 0, ',', '.');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Tablero de indicadores</h1>
        <p class="text-muted mb-0">Período: últimas {{ \App\Services\IndicadorService::SEMANAS }} semanas, del {{ $desde->format('d-m-Y') }} a hoy. Los indicadores se calculan con los datos del momento.</p>
    </div>
    <span class="badge bg-light text-dark border" id="calculado"><i class="bi bi-arrow-clockwise"></i> Actualizado el {{ $calculado->format('d-m-Y H:i') }}</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 h-100" id="m11">
            <div class="text-muted small">M1.1 · Ventas con descuento automático</div>
            <div class="kpi">{{ $pct($m11['porcentaje']) }}</div>
            <div class="small text-muted">{{ $m11['con_descuento'] }} de {{ $m11['ventas'] }} venta(s) descontaron su stock por lote (K1).</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 h-100" id="m12">
            <div class="text-muted small">M1.2 · Exactitud del inventario</div>
            <div class="kpi">{{ $pct($m12['exactitud']) }}</div>
            <div class="small text-muted">Conteos de los últimos {{ $m12['dias'] }} días: 1 − productos con diferencia ÷ productos contados (K6).</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 h-100 {{ $m2['total'] > 0 ? 'border border-danger' : '' }}" id="m2">
            <div class="text-muted small">M2 · Quiebres de alta rotación</div>
            <div class="kpi {{ $m2['total'] > 0 ? 'text-danger' : '' }}">{{ $m2['total'] }}</div>
            <div class="small text-muted">Veces que un producto de clase {{ \App\Services\IndicadorService::CLASE_ALTA_ROTACION }} quedó con stock 0 en 4 semanas (K3).</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card p-3 h-100" id="m3">
            <div class="text-muted small">M3 · Merma por vencimiento sobre ventas</div>
            <div class="kpi">{{ $pct($m3['porcentaje']) }}</div>
            <div class="small text-muted">{{ $clp($m3['merma']) }} de merma (a costo) sobre {{ $clp($m3['ventas']) }} vendidos (K7).</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card p-3 h-100">
            <h2 class="h5">Quiebres de stock por semana (clase {{ \App\Services\IndicadorService::CLASE_ALTA_ROTACION }})</h2>
            <div class="d-flex align-items-end gap-3 mt-3" style="height: 220px" id="grafico-quiebres">
                @foreach($m2['semanas'] as $semana)
                    <div class="flex-fill d-flex flex-column justify-content-end align-items-center h-100">
                        <div class="fw-bold mb-1">{{ $semana->quiebres }}</div>
                        <div class="w-100 rounded-top {{ $semana->quiebres > 0 ? 'bg-danger' : 'bg-secondary-subtle' }}"
                             style="height: {{ $semana->quiebres > 0 ? round($semana->quiebres / $maximoSemanal * 100) : 2 }}%"
                             data-quiebres="{{ $semana->quiebres }}"
                             title="{{ $semana->quiebres }} quiebre(s)"></div>
                        <div class="small text-muted text-center mt-2 text-nowrap">{{ $semana->desde->format('d-m') }} al {{ $semana->hasta->format('d-m') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Productos con quiebre</div>
            <ul class="list-group list-group-flush">
                @forelse($m2['productos'] as $p)
                    <li class="list-group-item d-flex justify-content-between">{{ $p->nombre }} <span class="badge bg-danger rounded-pill">{{ $p->quiebres }}</span></li>
                @empty
                    <li class="list-group-item text-muted">Sin quiebres en el período.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
