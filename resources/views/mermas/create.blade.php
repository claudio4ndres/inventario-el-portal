@extends('layouts.app')
@section('titulo', 'Registrar merma')
@section('contenido')
<h1 class="h3 mb-3">Registrar merma</h1>
<form method="POST" action="{{ route('mermas.store') }}" class="card p-4" style="max-width: 720px">
@csrf
    <div class="mb-3">
        <label class="form-label" for="lote_id">Lote</label>
        <select class="form-select" id="lote_id" name="lote_id" required>
            <option value="">Seleccione…</option>
            @foreach($lotes as $lote)
                <option value="{{ $lote->id }}" data-saldo="{{ $lote->cantidad_disponible }}" data-costo="{{ $lote->costo_unitario }}" @selected($loteSeleccionado == $lote->id)>
                    {{ $lote->producto->nombre }} · lote {{ $lote->codigo_lote }} · saldo {{ $lote->cantidad_disponible }}{{ $lote->fecha_vencimiento ? ' · vence '.$lote->fecha_vencimiento->format('d-m-Y') : '' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label" for="cantidad">Cantidad</label>
            <input class="form-control" type="number" min="1" id="cantidad" name="cantidad" value="{{ old('cantidad') }}" required>
            <div class="form-text" id="ayuda-saldo">Entre 1 y el saldo del lote.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="motivo_merma">Motivo</label>
            <select class="form-select" id="motivo_merma" name="motivo_merma" required>
                <option value="">Seleccione…</option>
                @foreach($motivos as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected(old('motivo_merma') === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Costo estimado</label>
            <div class="form-control-plaintext fw-bold" id="costo-estimado">—</div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="observacion">Observación</label>
        <input class="form-control" id="observacion" name="observacion" value="{{ old('observacion') }}" maxlength="100" placeholder="Ej.: envase roto al descargar">
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success"><i class="bi bi-save"></i> Registrar merma</button>
        <a class="btn btn-outline-secondary" href="{{ route('mermas.index') }}">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const lote = document.getElementById('lote_id');
    const cantidad = document.getElementById('cantidad');
    const ayuda = document.getElementById('ayuda-saldo');
    const costo = document.getElementById('costo-estimado');

    const actualizar = () => {
        const opcion = lote.selectedOptions[0];
        const saldo = opcion ? Number(opcion.dataset.saldo || 0) : 0;
        const unitario = opcion ? Number(opcion.dataset.costo || 0) : 0;
        if (saldo > 0) {
            cantidad.max = saldo;
            ayuda.textContent = `Entre 1 y ${saldo} (saldo del lote).`;
        }
        const n = Number(cantidad.value || 0);
        costo.textContent = saldo > 0 && n > 0 ? '$' + (n * unitario).toLocaleString('es-CL') : '—';
    };

    lote.addEventListener('change', actualizar);
    cantidad.addEventListener('input', actualizar);
    actualizar();
})();
</script>
@endpush
