@extends('layouts.app')
@section('titulo', 'Conteo N.º '.$conteo->id)
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0">Conteo N.º {{ $conteo->id }} · clase {{ $conteo->clase_abc }}</h1>
    <a class="btn btn-outline-secondary" href="{{ route('conteos.index') }}"><i class="bi bi-arrow-left"></i> Conteo cíclico</a>
</div>
<p class="text-muted">{{ $conteo->fecha_conteo->format('d-m-Y H:i') }} · registrado por {{ $conteo->usuario->name }}</p>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Productos contados</div><div class="kpi">{{ $conteo->productos_contados }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Con diferencia</div><div class="kpi {{ $conteo->productos_con_diferencia > 0 ? 'text-danger' : '' }}">{{ $conteo->productos_con_diferencia }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Exactitud</div><div class="kpi" id="kpi-exactitud-conteo">{{ number_format($conteo->exactitud(), 1, ',', '.') }} %</div></div></div>
</div>

<div class="card"><div class="table-responsive">
<table class="table mb-0">
    <thead class="table-light"><tr><th>Producto</th><th class="text-end">Sistema</th><th class="text-end">Contado</th><th class="text-end">Diferencia</th><th>Motivo</th></tr></thead>
    <tbody>
    @foreach($conteo->detalles as $d)
        <tr class="{{ $d->diferencia !== 0 ? 'table-warning' : '' }}">
            <td>{{ $d->producto->nombre }}</td>
            <td class="text-end">{{ $d->stock_sistema }}</td>
            <td class="text-end">{{ $d->cantidad_contada }}</td>
            <td class="text-end fw-bold {{ $d->diferencia === 0 ? 'text-success' : 'text-danger' }}">{{ $d->diferencia > 0 ? '+'.$d->diferencia : ($d->diferencia < 0 ? '−'.abs($d->diferencia) : '0') }}</td>
            <td class="small">{{ $d->motivo }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>
@if($conteo->productos_con_diferencia > 0 && auth()->user()->tieneRol('administrador'))
    <p class="form-text mt-2">Los ajustes quedan en la <a href="{{ route('movimientos.index', ['tipo' => 'AJUSTE']) }}">bitácora de movimientos</a>.</p>
@endif
@endsection
