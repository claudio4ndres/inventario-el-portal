@extends('layouts.app')
@section('titulo', 'Vista previa de la importación')
@section('contenido')
<h1 class="h3 mb-1">Vista previa de la importación</h1>
<p class="text-muted">Archivo «{{ $archivo }}»: {{ count($filas) }} {{ count($filas) === 1 ? 'fila' : 'filas' }}.</p>

<div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge bg-success fs-6">{{ $totalCrear }} por crear</span>
    <span class="badge bg-primary fs-6">{{ $totalActualizar }} por actualizar</span>
    <span class="badge bg-{{ $totalErrores > 0 ? 'danger' : 'secondary' }} fs-6">{{ $totalErrores }} con errores</span>
</div>

@if($totalErrores > 0)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i>
        Las filas con errores <strong>no se importarán</strong>. Corríjalas en el archivo y vuelva a subirlo, o importe solo las válidas.</div>
@endif

<div class="card mb-3">
<div class="table-responsive">
<table class="table table-sm mb-0 align-middle">
    <thead class="table-light"><tr>
        <th>Línea</th><th>Código</th><th>Nombre</th><th class="text-end">Precio</th><th class="text-end">P. reorden</th><th>ABC</th><th>Resultado</th>
    </tr></thead>
    <tbody>
    @foreach($filas as $fila)
        @php($d = $fila['datos'])
        <tr class="{{ $fila['errores'] ? 'table-danger' : '' }}">
            <td>{{ $fila['linea'] }}</td>
            <td><code>{{ $d['codigo_barras'] }}</code></td>
            <td>{{ $d['nombre'] }}</td>
            <td class="text-end">{{ $d['precio_venta'] }}</td>
            <td class="text-end">{{ $d['punto_reorden'] }}</td>
            <td>{{ $d['clase_abc'] }}</td>
            <td>
                @if($fila['errores'])
                    <ul class="mb-0 small text-danger ps-3">@foreach($fila['errores'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                @elseif($fila['accion'] === 'actualizar')
                    <span class="badge bg-primary">Se actualiza</span>
                @else
                    <span class="badge bg-success">Se crea</span>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
</div>

<div class="d-flex gap-2">
    @if($totalValidas > 0)
        <form method="POST" action="{{ route('productos.importar.store') }}">@csrf
            <button class="btn btn-success"><i class="bi bi-check-lg"></i> Importar {{ $totalValidas }} {{ $totalValidas === 1 ? 'fila válida' : 'filas válidas' }}</button>
        </form>
    @endif
    <a class="btn btn-outline-secondary" href="{{ route('productos.importar') }}">Subir otro archivo</a>
</div>
@endsection
