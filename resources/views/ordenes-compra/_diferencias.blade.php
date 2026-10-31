{{-- HU11: comparación pedida vs. recibida, compartida por la OC y la recepción --}}
<div class="card mb-3"><div class="table-responsive">
<table class="table mb-0" id="tabla-diferencias">
    <thead class="table-light"><tr><th>Producto</th><th class="text-end">Pedida</th><th class="text-end">Recibida</th><th class="text-end">Diferencia</th><th>Resultado</th></tr></thead>
    <tbody>
    @foreach($diferencias as $fila)
        <tr class="{{ $fila->diferencia === 0 ? '' : 'table-warning' }}">
            <td>{{ $fila->producto->nombre }}</td>
            <td class="text-end">{{ $fila->pedida }}</td>
            <td class="text-end">{{ $fila->recibida }}</td>
            <td class="text-end fw-bold">{{ $fila->diferencia > 0 ? '+'.$fila->diferencia : $fila->diferencia }}</td>
            <td>
                @if($fila->diferencia === 0)<span class="badge bg-success">Completo</span>
                @elseif($fila->pedida === 0)<span class="badge bg-info text-dark">No estaba en la OC</span>
                @elseif($fila->diferencia < 0)<span class="badge bg-danger">Faltante</span>
                @else<span class="badge bg-warning text-dark">Sobrante</span>@endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>
