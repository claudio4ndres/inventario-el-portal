@extends('layouts.app')
@section('titulo', 'Nueva recepción')
@section('contenido')
<h1 class="h3 mb-3">Registrar recepción de mercadería</h1>
<form method="POST" action="{{ route('recepciones.store') }}">
@csrf
<div class="card p-4 mb-3">
    <div class="row g-3">
        @if($ordenes->isNotEmpty())
        <div class="col-12">
            {{-- HU11: al elegir una OC enviada se recarga el formulario con su proveedor y sus productos --}}
            <label class="form-label" for="orden_compra_id">Orden de compra (opcional)</label>
            <select class="form-select" id="orden_compra_id" name="orden_compra_id"
                    onchange="window.location = '{{ route('recepciones.create') }}' + (this.value ? '?orden_compra_id=' + this.value : '')">
                <option value="">Sin orden de compra</option>
                @foreach($ordenes as $oc)
                    <option value="{{ $oc->id }}" @selected(old('orden_compra_id', $orden?->id) == $oc->id)>OC N.º {{ $oc->id }} · {{ $oc->proveedor->razon_social }}</option>
                @endforeach
            </select>
            <div class="form-text">Al registrar, la orden queda recibida y se muestran las diferencias entre lo pedido y lo recibido.</div>
        </div>
        @endif
        <div class="col-md-6">
            <label class="form-label" for="proveedor_id">Proveedor</label>
            <select class="form-select" id="proveedor_id" name="proveedor_id" required>
                <option value="">Seleccione…</option>
                @foreach($proveedores as $prov)
                    <option value="{{ $prov->id }}" @selected(old('proveedor_id', $orden?->proveedor_id) == $prov->id)>{{ $prov->razon_social }} ({{ $prov->rut }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="numero_documento">N.º factura o guía</label>
            <input class="form-control" id="numero_documento" name="numero_documento" value="{{ old('numero_documento') }}" required maxlength="30">
        </div>
    </div>
</div>

<div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Productos recibidos</h2>
        <button type="button" class="btn btn-sm btn-outline-success" id="agregar"><i class="bi bi-plus"></i> Agregar línea</button>
    </div>
    <div class="table-responsive">
    <table class="table" id="tabla-lineas">
        <thead><tr><th style="min-width:220px">Producto</th><th>Cantidad</th><th>Lote</th><th>Vencimiento</th><th>Costo unit.</th><th></th></tr></thead>
        <tbody>
        @foreach(old('lineas', $lineasIniciales) as $i => $linea)
            <tr>
                <td><select class="form-select" name="lineas[{{ $i }}][producto_id]" required>
                    <option value="">Seleccione…</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" @selected(($linea['producto_id'] ?? null) == $p->id)>{{ $p->nombre }} · {{ $p->codigo_barras }}</option>
                    @endforeach
                </select></td>
                <td><input class="form-control" type="number" min="1" name="lineas[{{ $i }}][cantidad]" value="{{ $linea['cantidad'] ?? '' }}" required style="width:90px"></td>
                <td><input class="form-control" name="lineas[{{ $i }}][codigo_lote]" value="{{ $linea['codigo_lote'] ?? '' }}" required maxlength="40"></td>
                <td><input class="form-control" type="date" name="lineas[{{ $i }}][fecha_vencimiento]" value="{{ $linea['fecha_vencimiento'] ?? '' }}"></td>
                <td><input class="form-control" type="number" min="0" name="lineas[{{ $i }}][costo_unitario]" value="{{ $linea['costo_unitario'] ?? '' }}" style="width:110px"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger quitar" title="Quitar"><i class="bi bi-x"></i></button></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <p class="form-text">Deje el vencimiento vacío solo para productos no perecibles.</p>
    <div class="d-flex gap-2">
        <button class="btn btn-success"><i class="bi bi-save"></i> Registrar recepción</button>
        <a class="btn btn-outline-secondary" href="{{ route('recepciones.index') }}">Cancelar</a>
    </div>
</div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const tbody = document.querySelector('#tabla-lineas tbody');
    let indice = tbody.rows.length;

    document.getElementById('agregar').addEventListener('click', () => {
        const fila = tbody.rows[0].cloneNode(true);
        fila.querySelectorAll('input, select').forEach(el => {
            el.name = el.name.replace(/lineas\[\d+\]/, `lineas[${indice}]`);
            el.value = '';
        });
        tbody.appendChild(fila);
        indice++;
    });

    tbody.addEventListener('click', e => {
        const boton = e.target.closest('.quitar');
        if (boton && tbody.rows.length > 1) boton.closest('tr').remove();
    });
})();
</script>
@endpush
