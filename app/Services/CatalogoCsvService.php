<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * HU16 / OE5 (M5.1): importación del catálogo inicial desde un archivo CSV.
 *
 * El proceso tiene dos pasos:
 *  1. analizar(): lee el CSV y valida cada fila con las mismas reglas del mantenedor (HU02),
 *     sin escribir nada. El resultado alimenta la vista previa con los errores fila por fila.
 *  2. importar(): guarda solo las filas válidas en una única transacción. Si el código de
 *     barras ya existe se actualiza el producto; si no, se crea.
 *
 * No mueve stock (el stock se calcula desde los lotes), por eso vive fuera de InventarioService.
 */
class CatalogoCsvService
{
    /** Columnas obligatorias, en el orden de la plantilla. */
    public const COLUMNAS = ['codigo_barras', 'nombre', 'precio_venta', 'punto_reorden', 'clase_abc'];

    /** Límite de filas por archivo, para que la vista previa siga siendo legible. */
    public const MAX_FILAS = 2000;

    /**
     * Lee y valida el contenido de un CSV.
     *
     * @return array<int, array{linea:int, datos:array<string,mixed>, errores:array<int,string>, accion:?string}>
     *
     * @throws InvalidArgumentException si el archivo no tiene el formato esperado (encabezado, filas)
     */
    public function analizar(string $contenido): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);   // BOM que agrega Excel
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $encabezado = $lineas[0] ?? '';
        // Excel en configuración regional chilena guarda con punto y coma
        $separador = substr_count($encabezado, ';') > substr_count($encabezado, ',') ? ';' : ',';

        $columnas = array_map(fn ($c) => mb_strtolower(trim($c)), str_getcsv($encabezado, $separador, '"', ''));
        $faltantes = array_diff(self::COLUMNAS, $columnas);
        if ($faltantes !== []) {
            throw new InvalidArgumentException('Faltan columnas en el encabezado del archivo: '.implode(', ', $faltantes).
                '. Descargue la plantilla para ver el formato.');
        }

        $filas = [];
        $codigosVistos = [];
        $existentes = null;

        foreach (array_slice($lineas, 1, null, true) as $indice => $linea) {
            if (trim($linea) === '' || trim(str_replace($separador, '', $linea)) === '') {
                continue;   // filas vacías
            }
            if (count($filas) >= self::MAX_FILAS) {
                throw new InvalidArgumentException('El archivo supera el máximo de '.self::MAX_FILAS.' filas.');
            }

            $valores = str_getcsv($linea, $separador, '"', '');
            $datos = [];
            foreach (self::COLUMNAS as $columna) {
                $posicion = array_search($columna, $columnas, true);
                $datos[$columna] = trim((string) ($valores[$posicion] ?? ''));
            }
            $datos = $this->normalizar($datos);

            $errores = Validator::make($datos, $this->reglas())->errors()->all();

            $codigo = $datos['codigo_barras'];
            if ($codigo !== '' && isset($codigosVistos[$codigo])) {
                $errores[] = "El código de barras {$codigo} está repetido en el archivo (línea {$codigosVistos[$codigo]}).";
            }
            $codigosVistos[$codigo] ??= $indice + 1;

            $accion = null;
            if ($errores === []) {
                $existentes ??= Producto::query()->pluck('id', 'codigo_barras');
                $accion = $existentes->has($codigo) ? 'actualizar' : 'crear';
            }

            $filas[] = ['linea' => $indice + 1, 'datos' => $datos, 'errores' => $errores, 'accion' => $accion];
        }

        if ($filas === []) {
            throw new InvalidArgumentException('El archivo no tiene filas de productos bajo el encabezado.');
        }

        return $filas;
    }

    /**
     * Guarda las filas válidas en una transacción: o se importan todas, o ninguna.
     *
     * @param  array<int, array<string,mixed>>  $filasValidas  datos ya validados por analizar()
     * @return array{creados:int, actualizados:int}
     */
    public function importar(array $filasValidas): array
    {
        return DB::transaction(function () use ($filasValidas) {
            $creados = 0;
            $actualizados = 0;

            foreach ($filasValidas as $datos) {
                // Se revalida por si el contenido no proviene de analizar()
                Validator::make($datos, $this->reglas())->validate();

                $producto = Producto::query()->lockForUpdate()->firstOrNew(['codigo_barras' => $datos['codigo_barras']]);
                $producto->exists ? $actualizados++ : $creados++;
                $producto->fill([
                    'nombre' => $datos['nombre'],
                    'precio_venta' => (int) $datos['precio_venta'],
                    'punto_reorden' => (int) $datos['punto_reorden'],
                    'clase_abc' => $datos['clase_abc'],
                ])->save();
            }

            return ['creados' => $creados, 'actualizados' => $actualizados];
        });
    }

    /** Contenido de la plantilla descargable, con dos filas de ejemplo. */
    public function plantilla(): string
    {
        return "\xEF\xBB\xBF".implode(';', self::COLUMNAS)."\r\n"
            ."7802900001002;Leche entera 1 L;1190;12;A\r\n"
            ."7801234000017;Arroz grado 1 1 kg;1490;8;B\r\n";
    }

    /** Mismas reglas del mantenedor (HU02), sin la unicidad: un código existente se actualiza. */
    private function reglas(): array
    {
        return [
            'codigo_barras' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z\-]+$/'],
            'nombre' => ['required', 'string', 'max:120'],
            'precio_venta' => ['required', 'integer', 'min:1', 'max:10000000'],
            'punto_reorden' => ['required', 'integer', 'min:0', 'max:100000'],
            'clase_abc' => ['required', Rule::in(['A', 'B', 'C'])],
        ];
    }

    /** Acepta montos escritos como "$1.190" y la clase en minúscula. */
    private function normalizar(array $datos): array
    {
        foreach (['precio_venta', 'punto_reorden'] as $campo) {
            $valor = str_replace(['$', ' '], '', $datos[$campo]);
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
                $valor = str_replace('.', '', $valor);   // separador de miles chileno
            }
            $datos[$campo] = $valor;
        }
        $datos['clase_abc'] = mb_strtoupper($datos['clase_abc']);

        return $datos;
    }
}
