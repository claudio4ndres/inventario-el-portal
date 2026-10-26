@extends('layouts.app')
@section('titulo', 'Conteo cíclico')
@section('contenido')
<div class="mb-3">
    <h1 class="h3 mb-1">Conteo cíclico</h1>
    <p class="text-muted mb-0">Se cuenta una clase ABC a la vez: A cada semana, B cada 15 días y C cada mes (control K6). Cada diferencia genera un ajuste en la bitácora.</p>
</div>

<div class="row g-3 mb-4">
    @foreach($programacion as $p)
        <div class="col-md-6 col-lg-3">
            <div class="card p-3 h-100 {{ $p->atrasada ? 'border border-danger' : ($p->pendiente ? 'border border-warning' : '') }}" id="clase-{{ $p->clase }}">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-5">Clase {{ $p->clase }}</span>
                    <span class="badge bg-light text-dark">{{ $p->frecuencia }} · cada {{ $p->dias }} días</span>
                </div>
                <div class="small text-muted mt-2">{{ $p->productos }} producto(s) activos</div>
                <div class="small">Último conteo: {{ $p->ultimo ? $p->ultimo->fecha_conteo->format('d-m-Y') : 'nunca' }}</div>
                <div class="small mb-2">Próximo: <strong>{{ $p->proxima->format('d-m-Y') }}</strong>
                    @if($p->atrasada)
                        <span class="badge bg-danger">Atrasado</span>
                    @elseif($p->pendiente)
                        <span class="badge bg-warning text-dark">Corresponde hoy</span>
                    @else
                        <span class="badge bg-success">Al día</span>
                    @endif
                </div>
                @if($p->productos > 0)
                    <a class="btn btn-sm {{ $p->pendiente ? 'btn-success' : 'btn-outline-success' }} mt-auto" href="{{ route('conteos.create', ['clase' => $p->clase]) }}"><i class="bi bi-clipboard-plus"></i> Contar clase {{ $p->clase }}</a>
                @endif
            </div>
        </div>
    @endforeach
    <div class="col-md-6 col-lg-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Exactitud del inventario (30 días)</div>
            <div class="kpi" id="kpi-exactitud">{{ $exactitud === null ? '—' : number_format($exactitud, 1, ',', '.').' %' }}</div>
            <div class="small text-muted">1 − productos con diferencia ÷ productos contados</div>
        </div>
    </div>
</div>

<h2 class="h5">Historial de conteos</h2>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead class="table-light"><tr><th>N.º</th><th>Fecha</th><th>Clase</th><th class="text-end">Contados</th><th class="text-end">Con diferencia</th><th class="text-end">Exactitud</th><th>Usuario</th><th></th></tr></thead>
    <tbody>
    @forelse($conteos as $c)
        <tr>
            <td>{{ $c->id }}</td>
            <td class="text-nowrap">{{ $c->fecha_conteo->format('d-m-Y H:i') }}</td>
            <td>{{ $c->clase_abc }}</td>
            <td class="text-end">{{ $c->productos_contados }}</td>
            <td class="text-end">{{ $c->productos_con_diferencia }}</td>
            <td class="text-end">{{ number_format($c->exactitud(), 1, ',', '.') }} %</td>
            <td>{{ $c->usuario->name }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('conteos.show', $c) }}">Ver</a></td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-muted py-4">Aún no se registran conteos.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-3">{{ $conteos->links('pagination::bootstrap-5') }}</div>
@endsection
