@extends('layouts.app')
@section('titulo', 'Conteo clase '.$clase)
@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0">Conteo de la clase {{ $clase }} <span class="badge bg-light text-dark fs-6 align-middle">{{ $frecuencia }}</span></h1>
    <a class="btn btn-outline-secondary" href="{{ route('conteos.index') }}"><i class="bi bi-arrow-left"></i> Conteo cíclico</a>
</div>
<p class="text-muted">Cuente las unidades en góndola y bodega e ingrese la cantidad física. Si difiere del sistema, indique el motivo: se registrará un ajuste.</p>

@if($productos->isEmpty())
    <div class="alert alert-info">No hay productos activos en la clase {{ $clase }}.</div>
@else
<form method="POST" action="{{ route('conteos.store') }}">
@csrf
<input type="hidden" name="clase_abc" value="{{ $clase }}">
<div class="card mb-3"><div class="table-responsive">
<table class="table mb-0">
    <thead class="table-light"><tr><th>Producto</th><th>Código</th><th class="text-end">Sistema</th><th class="text-end" style="width:140px">Contado</th><th class="text-end">Diferencia</th><th style="min-width:240px">Motivo de la diferencia</th></tr></thead>
    <tbody>
    @foreach($productos as $p)
        <tr class="fila-conteo" data-sistema="{{ (int) $p->stock }}">
            <td>{{ $p->nombre }}</td>
            <td><code class="small">{{ $p->codigo_barras }}</code></td>
            <td class="text-end">{{ (int) $p->stock }}</td>
            <td><input class="form-control text-end cantidad" type="number" min="0" max="100000" required
                       name="conteos[{{ $p->id }}][cantidad]" value="{{ old('conteos.'.$p->id.'.cantidad') }}"></td>
            <td class="text-end fw-bold diferencia">—</td>
            <td><input class="form-control" type="text" maxlength="80" placeholder="Ej.: venta no registrada"
                       name="conteos[{{ $p->id }}][motivo]" value="{{ old('conteos.'.$p->id.'.motivo') }}"></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>
<button class="btn btn-success" onclick="return confirm('¿Registrar el conteo? Las diferencias se ajustarán en el stock.')"><i class="bi bi-check2-square"></i> Registrar conteo</button>
</form>
@endif
@endsection

@push('scripts')
<script>
// Muestra la diferencia (contado − sistema) mientras se escribe; el servidor la recalcula al guardar.
document.querySelectorAll('.fila-conteo').forEach(function (fila) {
    var entrada = fila.querySelector('.cantidad');
    var celda = fila.querySelector('.diferencia');
    var actualizar = function () {
        if (entrada.value === '') { celda.textContent = '—'; celda.className = 'text-end fw-bold diferencia'; return; }
        var d = parseInt(entrada.value, 10) - parseInt(fila.dataset.sistema, 10);
        celda.textContent = d > 0 ? '+' + d : String(d);
        celda.className = 'text-end fw-bold diferencia ' + (d === 0 ? 'text-success' : 'text-danger');
    };
    entrada.addEventListener('input', actualizar);
    actualizar();
});
</script>
@endpush
