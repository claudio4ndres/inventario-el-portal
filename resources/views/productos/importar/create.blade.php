@extends('layouts.app')
@section('titulo', 'Importar catálogo')
@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Importar catálogo desde CSV</h1>
    <a class="btn btn-outline-secondary" href="{{ route('productos.index') }}"><i class="bi bi-arrow-left"></i> Productos</a>
</div>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4">
            <form method="POST" action="{{ route('productos.importar.vista-previa') }}" enctype="multipart/form-data">
                @csrf
                <label class="form-label" for="archivo">Archivo CSV</label>
                <input class="form-control @error('archivo') is-invalid @enderror" type="file" id="archivo" name="archivo" accept=".csv,text/csv" required>
                @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Máximo 1 MB y {{ \App\Services\CatalogoCsvService::MAX_FILAS }} filas. Antes de guardar se muestra una vista previa.</div>
                <button class="btn btn-success mt-3"><i class="bi bi-eye"></i> Ver vista previa</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-4">
            <h2 class="h5">Formato</h2>
            <p class="mb-2">La primera fila es el encabezado con estas columnas (separadas por punto y coma o coma):</p>
            <p><code>{{ implode(';', \App\Services\CatalogoCsvService::COLUMNAS) }}</code></p>
            <ul class="small">
                <li><strong>precio_venta</strong>: CLP con IVA, entero (se acepta <code>1.190</code> o <code>$1190</code>).</li>
                <li><strong>punto_reorden</strong>: entero, 0 o más.</li>
                <li><strong>clase_abc</strong>: A, B o C.</li>
                <li>Si el código de barras ya existe, el producto se <strong>actualiza</strong>; si no, se crea.</li>
                <li>Las filas con errores no se importan; las válidas se guardan todas juntas.</li>
            </ul>
            <a class="btn btn-outline-primary" href="{{ route('productos.importar.plantilla') }}"><i class="bi bi-download"></i> Descargar plantilla</a>
        </div>
    </div>
</div>
@endsection
