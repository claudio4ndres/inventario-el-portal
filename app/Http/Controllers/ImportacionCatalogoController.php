<?php

namespace App\Http\Controllers;

use App\Services\CatalogoCsvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * HU16 / OE5 (M5.1): importar el catálogo inicial desde un CSV.
 *
 * Flujo: subir archivo → vista previa con errores fila por fila → confirmar (importa las válidas).
 * Entre la vista previa y la confirmación, las filas válidas quedan en la sesión del administrador:
 * así se importa exactamente lo que se mostró, sin volver a subir el archivo.
 */
class ImportacionCatalogoController extends Controller
{
    private const CLAVE_SESION = 'importacion_catalogo';

    public function create(): View
    {
        return view('productos.importar.create');
    }

    public function plantilla(CatalogoCsvService $servicio): Response
    {
        return response($servicio->plantilla(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-catalogo.csv"',
        ]);
    }

    public function vistaPrevia(Request $request, CatalogoCsvService $servicio): View|RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $archivo = $request->file('archivo');

        try {
            $filas = $servicio->analizar((string) file_get_contents($archivo->getRealPath()));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['archivo' => $e->getMessage()]);
        }

        $validas = array_values(array_filter($filas, fn ($f) => $f['errores'] === []));
        $request->session()->put(self::CLAVE_SESION, [
            'archivo' => $archivo->getClientOriginalName(),
            'filas' => array_column($validas, 'datos'),
        ]);

        return view('productos.importar.vista-previa', [
            'archivo' => $archivo->getClientOriginalName(),
            'filas' => $filas,
            'totalValidas' => count($validas),
            'totalErrores' => count($filas) - count($validas),
            'totalCrear' => count(array_filter($validas, fn ($f) => $f['accion'] === 'crear')),
            'totalActualizar' => count(array_filter($validas, fn ($f) => $f['accion'] === 'actualizar')),
        ]);
    }

    public function store(Request $request, CatalogoCsvService $servicio): RedirectResponse
    {
        $pendiente = $request->session()->pull(self::CLAVE_SESION);

        if (empty($pendiente['filas'])) {
            return redirect()->route('productos.importar')
                ->withErrors(['archivo' => 'No hay filas válidas para importar. Suba el archivo nuevamente.']);
        }

        $resultado = $servicio->importar($pendiente['filas']);

        return redirect()->route('productos.index')->with('ok',
            "Catálogo importado desde «{$pendiente['archivo']}»: {$resultado['creados']} ".
            ($resultado['creados'] === 1 ? 'producto creado' : 'productos creados')." y {$resultado['actualizados']} ".
            ($resultado['actualizados'] === 1 ? 'actualizado.' : 'actualizados.'));
    }
}
