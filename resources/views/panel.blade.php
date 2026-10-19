@extends('layouts.app')
@section('titulo', 'Panel')
@section('contenido')
<h1 class="h3 mb-4">Hola, {{ auth()->user()->name }}</h1>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Ventas de hoy</div><div class="kpi">{{ $ventasHoy }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Monto vendido hoy</div><div class="kpi">${{ number_format($montoHoy, 0, ',', '.') }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3">
        <div class="text-muted small">Productos activos</div><div class="kpi">{{ $productosActivos }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="card p-3 {{ $alertasReorden > 0 ? 'border border-danger' : '' }}">
        <div class="text-muted small">Bajo punto de reorden</div>
        <div class="kpi {{ $alertasReorden > 0 ? 'text-danger' : '' }}">{{ $alertasReorden }}</div></div></div>
</div>
<div class="d-flex flex-wrap gap-2">
    @if(auth()->user()->tieneRol('administrador', 'vendedor'))
        <a class="btn btn-success btn-lg" href="{{ route('ventas.create') }}"><i class="bi bi-cart-plus"></i> Nueva venta</a>
    @endif
    @if(auth()->user()->tieneRol('administrador', 'bodeguero'))
        <a class="btn btn-outline-success btn-lg" href="{{ route('recepciones.create') }}"><i class="bi bi-box-seam"></i> Registrar recepción</a>
        <a class="btn btn-outline-danger btn-lg" href="{{ route('alertas.index') }}"><i class="bi bi-bell"></i> Ver alertas</a>
    @endif
</div>
@endsection
