<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** HU14: conteo cíclico de los productos de una clase ABC (RF7, K6, OE1 M1.2). */
class Conteo extends Model
{
    /** Frecuencia del conteo por clase, en días: A semanal, B quincenal, C mensual. */
    public const FRECUENCIA_DIAS = ['A' => 7, 'B' => 14, 'C' => 30];

    public const FRECUENCIA_ETIQUETA = ['A' => 'Semanal', 'B' => 'Quincenal', 'C' => 'Mensual'];

    protected $table = 'conteos';

    protected $fillable = ['clase_abc', 'user_id', 'fecha_conteo', 'productos_contados', 'productos_con_diferencia'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'fecha_conteo' => 'datetime',
            'productos_contados' => 'integer',
            'productos_con_diferencia' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleConteo::class);
    }

    /** Proporción de productos con diferencia: productos con diferencia ÷ productos contados (0 a 1). */
    public function tasaDiferencias(): float
    {
        return $this->productos_contados > 0 ? $this->productos_con_diferencia / $this->productos_contados : 0.0;
    }

    /** Exactitud del inventario en %: 100 − (productos con diferencia ÷ productos contados × 100). */
    public function exactitud(): float
    {
        return round((1 - $this->tasaDiferencias()) * 100, 1);
    }

    /**
     * Exactitud acumulada de los conteos de los últimos $dias días (indicador M1.2).
     * Suma los productos de todos los conteos del período; null si no hubo conteos.
     */
    public static function exactitudPeriodo(int $dias = 30): ?float
    {
        $totales = self::query()
            ->where('fecha_conteo', '>=', now()->subDays($dias))
            ->selectRaw('coalesce(sum(productos_contados), 0) as contados, coalesce(sum(productos_con_diferencia), 0) as con_diferencia')
            ->first();

        $contados = (int) $totales->contados;

        return $contados > 0 ? round((1 - (int) $totales->con_diferencia / $contados) * 100, 1) : null;
    }

    /**
     * Programación por clase: último conteo, fecha del próximo y si ya corresponde contar.
     * Una clase que nunca se ha contado queda pendiente desde hoy.
     *
     * @return Collection<string, object{clase: string, frecuencia: string, dias: int, productos: int, ultimo: ?Conteo, proxima: CarbonInterface, pendiente: bool, atrasada: bool}>
     */
    public static function programacion(): Collection
    {
        $hoy = today();
        $productosPorClase = Producto::where('activo', true)
            ->groupBy('clase_abc')->selectRaw('clase_abc, count(*) as total')
            ->pluck('total', 'clase_abc');

        return collect(self::FRECUENCIA_DIAS)->map(function (int $dias, string $clase) use ($hoy, $productosPorClase) {
            $ultimo = self::where('clase_abc', $clase)->latest('fecha_conteo')->latest('id')->first();
            $proxima = $ultimo ? $ultimo->fecha_conteo->copy()->startOfDay()->addDays($dias) : $hoy->copy();

            return (object) [
                'clase' => $clase,
                'frecuencia' => self::FRECUENCIA_ETIQUETA[$clase],
                'dias' => $dias,
                'productos' => (int) ($productosPorClase[$clase] ?? 0),
                'ultimo' => $ultimo,
                'proxima' => $proxima,
                'pendiente' => $proxima->lte($hoy),
                'atrasada' => $proxima->lt($hoy),
            ];
        });
    }
}
