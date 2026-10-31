<?php

namespace App\Support;

/**
 * HU09 · K2: utilidades para el RUT chileno.
 *
 * El dígito verificador se calcula con el algoritmo módulo 11: se multiplican los dígitos
 * del cuerpo, de derecha a izquierda, por la serie 2, 3, 4, 5, 6, 7, 2, 3…; se suma,
 * y el dígito es 11 − (suma mód 11), donde 11 → "0" y 10 → "K".
 */
final class Rut
{
    /** Quita puntos, guiones y espacios, y deja la K en mayúscula: "76.111.111-6" → "761111116". */
    public static function limpiar(string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9kK]/', '', $rut));
    }

    public static function calcularDv(string $cuerpo): string
    {
        $suma = 0;
        $factor = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += (int) $cuerpo[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }

    /** Acepta el RUT con o sin puntos y con o sin guion. El cuerpo tiene de 7 a 8 dígitos. */
    public static function esValido(string $rut): bool
    {
        // Solo se permiten dígitos, puntos, un guion, espacios y la K final
        if (! preg_match('/^[0-9.\s]+-?\s*[0-9kK]$/', trim($rut))) {
            return false;
        }

        $limpio = self::limpiar($rut);
        if (! preg_match('/^(\d{7,8})([0-9K])$/', $limpio, $partes)) {
            return false;
        }

        return self::calcularDv($partes[1]) === $partes[2];
    }

    /**
     * Formato único con que se guarda el RUT: "76.111.111-6". Así la unicidad no depende
     * de cómo se escribió (con o sin puntos, k minúscula o mayúscula).
     */
    public static function formatear(string $rut): string
    {
        $limpio = self::limpiar($rut);
        $cuerpo = substr($limpio, 0, -1);
        $dv = substr($limpio, -1);

        return number_format((int) $cuerpo, 0, '', '.').'-'.$dv;
    }
}
