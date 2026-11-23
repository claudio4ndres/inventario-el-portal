<?php

namespace Database\Seeders;

use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\OrdenCompraService;
use App\Services\PuntoReordenService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Escenario de prueba SIMULADO: 28 días de operación de un almacén ficticio.
 *
 * Sirve para probar que los indicadores del tablero (M1.1, M1.2, M2 y M3) y las alertas
 * responden a casos conocidos: quiebres de stock, mermas por vencimiento, daño y robo, y
 * diferencias de conteo. NO son datos del almacén El Portal ni resultados del piloto: los
 * proveedores y usuarios llevan la marca "(simulado)" / "(pruebas)" para que no se confundan.
 *
 * Es determinista (semilla fija): cada carga produce exactamente los mismos datos.
 * Solo corre sobre una base vacía, para que nunca se mezcle con datos reales.
 *
 * Uso: php artisan migrate:fresh --seed --seeder=EscenarioPruebaSeeder
 * (en Docker o Railway: DEMO_RESET=true y DEMO_SEEDER=EscenarioPruebaSeeder).
 */
class EscenarioPruebaSeeder extends Seeder
{
    public const DIAS = 28;

    public const SEMILLA = 2026;

    /** Días en que el proveedor de lácteos no entrega la leche (llega día por medio): provoca quiebres en la semana 2. */
    public const DIAS_SIN_ENTREGA_LECHE = [10, 12];

    /**
     * Catálogo del escenario.
     * [código, nombre, precio, costo, punto de reorden, clase ABC, demanda diaria media,
     *  cada cuántos días se repone, unidades por entrega, vida útil en días (null = no perecible),
     *  alta rotación, proveedor]
     */
    private const CATALOGO = [
        'leche' => ['7802900001002', 'Leche entera 1 L', 1190, 850, 12, 'A', 9, 2, 20, 10, true, 'lacteos'],
        'huevos' => ['7801234000123', 'Huevos blancos 12 un.', 3290, 2400, 6, 'A', 3, 7, 20, 25, true, 'abarrotes'],
        'bebida' => ['7801610001196', 'Bebida cola 1,5 L', 1890, 1200, 10, 'A', 6, 7, 40, 180, true, 'abarrotes'],
        'pan' => ['7802408000017', 'Pan de molde blanco', 2190, 1500, 5, 'B', 3, 3, 9, 6, true, 'panaderia'],
        'yogur' => ['7802920000154', 'Yogur frutilla 125 g', 450, 280, 15, 'B', 4, 3, 18, 4, false, 'lacteos'],
        'queso' => ['7802920000307', 'Queso gauda laminado 250 g', 2990, 2100, 4, 'B', 2, 3, 6, 4, false, 'lacteos'],
        'arroz' => ['7801320000018', 'Arroz grado 2, 1 kg', 1490, 1000, 8, 'B', 3, 7, 20, null, true, 'abarrotes'],
        'fideos' => ['7802575000155', 'Fideos spaghetti 400 g', 990, 600, 8, 'C', 3, 7, 18, null, true, 'abarrotes'],
        'azucar' => ['7801555000202', 'Azúcar 1 kg', 1290, 900, 6, 'C', 2, 7, 15, null, true, 'abarrotes'],
        'aceite' => ['7801555000400', 'Aceite vegetal 1 L', 2490, 1800, 4, 'C', 1, 14, 12, null, false, 'abarrotes'],
        'cafe' => ['7801555000509', 'Café instantáneo 170 g', 3990, 2800, 3, 'C', 1, 14, 10, null, false, 'abarrotes'],
        'cecina' => ['7801234567892', 'Cecina jamón 250 g', 2490, 1700, 4, 'C', 1.5, 4, 5, 5, false, 'lacteos'],
    ];

    private InventarioService $inventario;

    /** @var array<string, Producto> */
    private array $productos = [];

    /** @var array<string, Proveedor> */
    private array $proveedores = [];

    private User $admin;

    private User $caja;

    private User $bodega;

    public function run(InventarioService $inventario, OrdenCompraService $compras, PuntoReordenService $reorden): void
    {
        if (User::query()->exists()) {
            throw new RuntimeException('El escenario de prueba solo se carga en una base vacía (use migrate:fresh): nunca se mezcla con datos reales.');
        }

        $this->inventario = $inventario;
        mt_srand(self::SEMILLA);

        $inicio = today()->subDays(self::DIAS - 1);   // el mismo período de 4 semanas del tablero

        try {
            Carbon::setTestNow($inicio->copy()->setTime(8, 0));
            $this->crearMaestros();

            for ($dia = 0; $dia < self::DIAS; $dia++) {
                $fecha = $inicio->copy()->addDays($dia);
                $this->recepciones($dia, $fecha);
                $this->ventas($fecha);
                $this->eventosProgramados($dia, $fecha);
                $this->retirarVencidos($fecha);
            }
        } finally {
            Carbon::setTestNow();
        }

        // Al cierre del período: punto de reorden según la demanda real del escenario y OC sugeridas
        $reorden->recalcular();
        $compras->generarSugeridas($this->admin);
    }

    private function crearMaestros(): void
    {
        $this->admin = User::create(['name' => 'Administrador (pruebas)', 'email' => 'admin@elportal.test', 'password' => 'ElPortal2026', 'rol' => User::ADMINISTRADOR]);
        $this->caja = User::create(['name' => 'Caja (pruebas)', 'email' => 'caja@elportal.test', 'password' => 'ElPortal2026', 'rol' => User::VENDEDOR]);
        $this->bodega = User::create(['name' => 'Bodega (pruebas)', 'email' => 'bodega@elportal.test', 'password' => 'ElPortal2026', 'rol' => User::BODEGUERO]);

        $this->proveedores = [
            'lacteos' => Proveedor::create(['rut' => '76.111.111-6', 'razon_social' => 'Lácteos del Sur (simulado)', 'telefono' => '+56222222222']),
            'abarrotes' => Proveedor::create(['rut' => '76.222.222-1', 'razon_social' => 'Mayorista Abarrotes (simulado)', 'telefono' => '+56233333333']),
            'panaderia' => Proveedor::create(['rut' => '76.333.333-7', 'razon_social' => 'Panificadora (simulado)', 'telefono' => '+56244444444']),
        ];

        foreach (self::CATALOGO as $clave => [$codigo, $nombre, $precio, , $reorden, $abc, , , , , $altaRotacion]) {
            $this->productos[$clave] = Producto::create([
                'codigo_barras' => $codigo,
                'nombre' => $nombre,
                'precio_venta' => $precio,
                'punto_reorden' => $reorden,
                'clase_abc' => $abc,
                'alta_rotacion' => $altaRotacion,
            ]);
        }
    }

    /** 08:30: cada proveedor entrega los productos que le tocan ese día (una recepción por proveedor). */
    private function recepciones(int $dia, Carbon $fecha): void
    {
        Carbon::setTestNow($fecha->copy()->setTime(8, 30));

        $porProveedor = [];
        foreach (array_keys(self::CATALOGO) as $i => $clave) {
            [, , , $costo, , , , $cadaDias, $unidades, $vidaUtil, , $proveedor] = self::CATALOGO[$clave];

            // Día 0 se abastece todo; después, cada producto según su frecuencia (desfasados entre sí)
            $tocaHoy = $dia === 0 || ($dia - ($i % $cadaDias)) % $cadaDias === 0;
            if (! $tocaHoy || ($clave === 'leche' && in_array($dia, self::DIAS_SIN_ENTREGA_LECHE, true))) {
                continue;
            }

            $porProveedor[$proveedor][] = [
                'producto_id' => $this->productos[$clave]->id,
                'cantidad' => $unidades,
                'codigo_lote' => 'SIM-'.strtoupper(substr($clave, 0, 3)).'-'.$fecha->format('md'),
                'fecha_vencimiento' => $vidaUtil === null ? null : $fecha->copy()->addDays($vidaUtil)->toDateString(),
                'costo_unitario' => $costo,
            ];
        }

        foreach ($porProveedor as $proveedor => $lineas) {
            $this->inventario->registrarRecepcion($this->bodega, [
                'proveedor_id' => $this->proveedores[$proveedor]->id,
                'numero_documento' => 'SIM-'.$fecha->format('md').'-'.strtoupper(substr($proveedor, 0, 3)),
            ], $lineas);
        }
    }

    /**
     * 09:00 a 20:00: la demanda del día se reparte en boletas de 1 a 4 productos.
     * Si un producto no alcanza se vende lo que queda (y se pierde el resto de la demanda):
     * así el stock llega a 0 y el quiebre queda registrado, como en el local.
     */
    private function ventas(Carbon $fecha): void
    {
        $unidades = [];
        foreach (self::CATALOGO as $clave => $datos) {
            $media = $datos[6];
            $cantidad = (int) floor($media * (0.6 + 0.8 * $this->azar()) + $this->azar());
            $unidades = array_merge($unidades, array_fill(0, $cantidad, $clave));
        }
        $unidades = $this->barajar($unidades);

        $boletas = [];
        while ($unidades !== []) {
            $boletas[] = array_splice($unidades, 0, mt_rand(1, 4));
        }

        $minutosPorBoleta = $boletas === [] ? 0 : intdiv(11 * 60, count($boletas));
        foreach ($boletas as $n => $boleta) {
            Carbon::setTestNow($fecha->copy()->setTime(9, 0)->addMinutes($n * $minutosPorBoleta));

            $items = [];
            foreach (array_count_values($boleta) as $clave => $cantidad) {
                $id = $this->productos[$clave]->id;
                $vendible = min($cantidad, $this->inventario->stockDe($id));
                if ($vendible > 0) {
                    $items[] = ['producto_id' => $id, 'cantidad' => $vendible];
                }
            }

            if ($items !== []) {
                $this->inventario->registrarVenta($this->caja, $items, mt_rand(1, 10) <= 6 ? 'efectivo' : 'debito');
            }
        }
    }

    /** Casos de prueba fijos, para verificar cada indicador con un hecho conocido. */
    private function eventosProgramados(int $dia, Carbon $fecha): void
    {
        Carbon::setTestNow($fecha->copy()->setTime(20, 15));

        // Merma por daño: una bandeja de huevos se cae al reponer (semana 3)
        if ($dia === 15) {
            $this->mermaDelPrimerLote('huevos', 1, 'dano', 'Bandeja caída al reponer (caso de prueba)');
        }
        // Merma por robo: falta una bebida detectada en góndola (semana 3)
        if ($dia === 17) {
            $this->mermaDelPrimerLote('bebida', 1, 'robo', 'Faltante detectado en góndola (caso de prueba)');
        }

        // Conteo cíclico semanal de la clase A (días 6, 13, 20 y 27); el del día 13 tiene una diferencia
        if ($dia % 7 === 6) {
            $lineas = [];
            foreach (['leche', 'huevos', 'bebida'] as $clave) {
                $id = $this->productos[$clave]->id;
                $lineas[$id] = ['cantidad' => $this->inventario->stockDe($id)];
            }
            $bebida = $this->productos['bebida']->id;
            if ($dia === 13 && $lineas[$bebida]['cantidad'] > 0) {
                $lineas[$bebida] = ['cantidad' => $lineas[$bebida]['cantidad'] - 1, 'motivo' => 'Venta no registrada en caja (caso de prueba)'];
            }
            $this->inventario->registrarConteo($this->bodega, 'A', $lineas);
        }
    }

    /** 20:30, al cierre: se retiran los lotes que vencen ese día o antes (merma por vencimiento, M3). */
    private function retirarVencidos(Carbon $fecha): void
    {
        Carbon::setTestNow($fecha->copy()->setTime(20, 30));

        Lote::query()
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', $fecha->toDateString())
            ->where('cantidad_disponible', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(fn (Lote $lote) => $this->inventario->registrarMerma(
                $this->bodega, $lote->id, $lote->cantidad_disponible, 'vencimiento', 'Retirado al cierre'
            ));
    }

    private function mermaDelPrimerLote(string $clave, int $cantidad, string $motivo, string $observacion): void
    {
        $lote = Lote::query()
            ->where('producto_id', $this->productos[$clave]->id)
            ->where('cantidad_disponible', '>=', $cantidad)
            ->orderBy('id')
            ->first();

        if ($lote !== null) {
            $this->inventario->registrarMerma($this->bodega, $lote->id, $cantidad, $motivo, $observacion);
        }
    }

    /** Número entre 0 y 1 a partir de la semilla fija (mt_rand), para que el escenario sea reproducible. */
    private function azar(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    /**
     * Fisher-Yates con mt_rand: no se usa shuffle() para no depender de la implementación
     * de PHP y obtener siempre el mismo orden con la misma semilla.
     *
     * @param  array<int, string>  $lista
     * @return array<int, string>
     */
    private function barajar(array $lista): array
    {
        for ($i = count($lista) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$lista[$i], $lista[$j]] = [$lista[$j], $lista[$i]];
        }

        return $lista;
    }
}
