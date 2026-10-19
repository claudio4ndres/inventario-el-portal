@extends('layouts.app')
@section('titulo', 'Nueva venta')
@section('contenido')
<h1 class="h3 mb-3">Registrar venta</h1>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card p-3 mb-3">
            <label class="form-label" for="buscador"><i class="bi bi-upc-scan"></i> Escanee el código de barras o escriba el nombre</label>
            <input class="form-control form-control-lg" id="buscador" autocomplete="off" autofocus placeholder="Ej.: 7802900001002 o leche">
            <div class="list-group mt-2" id="resultados"></div>
        </div>
        <div class="card">
            <div class="table-responsive">
            <table class="table mb-0" id="carrito">
                <thead class="table-light"><tr><th>Producto</th><th class="text-end">Precio</th><th class="text-end">Stock</th><th style="width:120px">Cantidad</th><th class="text-end">Subtotal</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
            </div>
            <p class="text-muted text-center py-3 mb-0" id="vacio">Aún no hay productos en la venta.</p>
        </div>
    </div>
    <div class="col-lg-4">
        <form method="POST" action="{{ route('ventas.store') }}" id="form-venta" class="card p-3">
            @csrf
            <div id="campos"></div>
            <div class="text-muted">Total</div>
            <div class="kpi mb-3" id="total">$0</div>
            <label class="form-label" for="medio_pago">Medio de pago</label>
            <select class="form-select mb-3" id="medio_pago" name="medio_pago">
                @foreach(['efectivo' => 'Efectivo', 'debito' => 'Débito', 'credito' => 'Crédito', 'transferencia' => 'Transferencia'] as $v => $t)
                    <option value="{{ $v }}" @selected(old('medio_pago', 'efectivo') === $v)>{{ $t }}</option>
                @endforeach
            </select>
            <button class="btn btn-success btn-lg w-100" id="confirmar" disabled><i class="bi bi-check2-circle"></i> Confirmar venta</button>
            <p class="form-text mb-0 mt-2">Al confirmar, el stock se descuenta automáticamente del lote que vence primero.</p>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const urlBuscar = @json(route('productos.buscar'));
    const carrito = new Map(); // id -> {producto, cantidad}
    @json($carrito).forEach(p => carrito.set(p.id, { producto: p, cantidad: p.cantidad }));

    const $ = s => document.querySelector(s);
    const pesos = n => '$' + Number(n).toLocaleString('es-CL');
    const escapar = t => String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function agregar(p) {
        const linea = carrito.get(p.id);
        if (linea) linea.cantidad++; else carrito.set(p.id, { producto: p, cantidad: 1 });
        $('#resultados').innerHTML = '';
        $('#buscador').value = '';
        $('#buscador').focus();
        dibujar();
    }

    function dibujar() {
        const tbody = $('#carrito tbody');
        tbody.innerHTML = '';
        $('#campos').innerHTML = '';
        let total = 0, i = 0;
        carrito.forEach(({ producto: p, cantidad }, id) => {
            total += p.precio_venta * cantidad;
            const sinStock = cantidad > p.stock;
            tbody.insertAdjacentHTML('beforeend', `
                <tr class="${sinStock ? 'table-danger' : ''}">
                    <td>${escapar(p.nombre)}<br><code class="small">${escapar(p.codigo_barras)}</code></td>
                    <td class="text-end">${pesos(p.precio_venta)}</td>
                    <td class="text-end">${p.stock}</td>
                    <td><input type="number" min="1" class="form-control form-control-sm cant" data-id="${id}" value="${cantidad}"></td>
                    <td class="text-end">${pesos(p.precio_venta * cantidad)}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger quitar" data-id="${id}"><i class="bi bi-x"></i></button></td>
                </tr>`);
            $('#campos').insertAdjacentHTML('beforeend', `
                <input type="hidden" name="items[${i}][producto_id]" value="${id}">
                <input type="hidden" name="items[${i}][cantidad]" value="${cantidad}">`);
            i++;
        });
        $('#total').textContent = pesos(total);
        $('#vacio').hidden = carrito.size > 0;
        $('#confirmar').disabled = carrito.size === 0;
    }

    async function buscar(termino) {
        const r = await fetch(`${urlBuscar}?q=${encodeURIComponent(termino)}`, { headers: { Accept: 'application/json' } });
        const productos = await r.json();
        // Un escáner envía el código + Enter: si coincide exacto, se agrega directo
        const exacto = productos.find(p => p.codigo_barras === termino);
        if (exacto) return agregar(exacto);
        $('#resultados').innerHTML = productos.length
            ? productos.map((p, k) => `<button type="button" class="list-group-item list-group-item-action" data-k="${k}">
                ${escapar(p.nombre)} · ${pesos(p.precio_venta)} <span class="badge bg-secondary float-end">stock ${p.stock}</span></button>`).join('')
            : '<div class="list-group-item text-muted">Sin resultados</div>';
        $('#resultados').querySelectorAll('button').forEach(b => b.addEventListener('click', () => agregar(productos[b.dataset.k])));
    }

    $('#buscador').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); if (e.target.value.trim()) buscar(e.target.value.trim()); }
    });
    $('#carrito').addEventListener('change', e => {
        if (!e.target.classList.contains('cant')) return;
        const linea = carrito.get(Number(e.target.dataset.id));
        linea.cantidad = Math.max(1, parseInt(e.target.value, 10) || 1);
        dibujar();
    });
    $('#carrito').addEventListener('click', e => {
        const b = e.target.closest('.quitar');
        if (b) { carrito.delete(Number(b.dataset.id)); dibujar(); }
    });

    dibujar();
})();
</script>
@endpush
