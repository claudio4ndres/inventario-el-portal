@extends('layouts.app')
@section('titulo', 'Panel')
@section('contenido')
<h1 class="h3 mb-4">Hola, {{ auth()->user()->name }}</h1>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="card p-3">
        <div class="text-muted small">Ventas de hoy</div><div class="kpi">{{ $ventasHoy }}</div></div></div>
    <div class="col-6 col-lg"><div class="card p-3">
        <div class="text-muted small">Monto vendido hoy</div><div class="kpi">${{ number_format($montoHoy, 0, ',', '.') }}</div></div></div>
    <div class="col-6 col-lg"><div class="card p-3">
        <div class="text-muted small">Productos activos</div><div class="kpi">{{ $productosActivos }}</div></div></div>
    <div class="col-6 col-lg"><div class="card p-3 {{ $alertasReorden > 0 ? 'border border-danger' : '' }}">
        <div class="text-muted small">Bajo punto de reorden</div>
        <div class="kpi {{ $alertasReorden > 0 ? 'text-danger' : '' }}">{{ $alertasReorden }}</div></div></div>
    @if(auth()->user()->tieneRol('administrador', 'bodeguero'))
    <div class="col-6 col-lg"><a href="{{ route('lotes.por-vencer') }}" class="text-decoration-none text-reset">
        <div class="card p-3 {{ $lotesPorVencer > 0 ? 'border border-warning' : '' }}">
        <div class="text-muted small">Lotes por vencer ({{ $diasPorVencer }} días)</div>
        <div class="kpi {{ $lotesPorVencer > 0 ? 'text-warning' : '' }}" id="kpi-lotes-por-vencer">{{ $lotesPorVencer }}</div></div></a></div>
    @endif
</div>
<div class="d-flex flex-wrap gap-2">
    @if(auth()->user()->tieneRol('administrador', 'vendedor'))
        <a class="btn btn-success btn-lg" href="{{ route('ventas.create') }}"><i class="bi bi-cart-plus"></i> Nueva venta</a>
    @endif
    @if(auth()->user()->tieneRol('administrador', 'bodeguero'))
        <a class="btn btn-outline-success btn-lg" href="{{ route('recepciones.create') }}"><i class="bi bi-box-seam"></i> Registrar recepción</a>
        <a class="btn btn-outline-danger btn-lg" href="{{ route('alertas.index') }}"><i class="bi bi-bell"></i> Ver alertas</a>
        <a class="btn btn-outline-warning btn-lg" href="{{ route('lotes.por-vencer') }}"><i class="bi bi-hourglass-split"></i> Lotes por vencer</a>
    @endif
</div>
@endsection
