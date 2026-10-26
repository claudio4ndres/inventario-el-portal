<?php

namespace Database\Seeders;

use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\OrdenCompraService;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios de demostración del almacén El Portal (no son datos reales del local).
 * Usuarios de prueba: contraseña "ElPortal2026" para los tres.
 */
class DatabaseSeeder extends Seeder
{
    public function run(InventarioService $inventario, OrdenCompraService $compras): void
    {
        $admin = User::create(['name' => 'Administrador', 'email' => 'admin@elportal.test', 'password' => 'ElPortal2026', 'rol' => User::ADMINISTRADOR]);
        $vendedor = User::create(['name' => 'Caja', 'email' => 'caja@elportal.test', 'password' => 'ElPortal2026', 'rol' => User::VENDEDOR]);
        $bodeguero = User::create(['name' => 'Bodega', 'email' => 'bodega@elportal.test', 'password' => 'ElPortal2026', 'rol' => User::BODEGUERO]);

        $lacteos = Proveedor::create(['rut' => '76.111.111-6', 'razon_social' => 'Distribuidora Lácteos del Sur', 'telefono' => '+56222222222']);
        $abarrotes = Proveedor::create(['rut' => '76.222.222-1', 'razon_social' => 'Mayorista Abarrotes Central', 'telefono' => '+56233333333']);
        $panaderia = Proveedor::create(['rut' => '76.333.333-7', 'razon_social' => 'Panificadora Valle Grande', 'telefono' => '+56244444444']);
        // Proveedor inactivo: no aparece en la recepción (HU09)
        Proveedor::create(['rut' => '76.444.444-2', 'razon_social' => 'Embutidos La Montaña (sin contrato)', 'telefono' => '+56255555555', 'activo' => false]);

        $p = fn (string $codigo, string $nombre, int $precio, int $reorden, string $abc) => Producto::create([
            'codigo_barras' => $codigo, 'nombre' => $nombre, 'precio_venta' => $precio,
            'punto_reorden' => $reorden, 'clase_abc' => $abc,
        ]);

        $leche = $p('7802900001002', 'Leche entera 1 L', 1190, 12, 'A');
        $huevos = $p('7801234000123', 'Huevos blancos 12 un.', 3290, 6, 'A');
        $bebida = $p('7801610001196', 'Bebida cola 1,5 L', 1890, 10, 'A');
        $panMolde = $p('7802408000017', 'Pan de molde blanco', 2190, 5, 'B');
        $yogur = $p('7802920000154', 'Yogur frutilla 125 g', 450, 15, 'B');
        $arroz = $p('7801320000018', 'Arroz grado 2, 1 kg', 1490, 8, 'B');
        $fideos = $p('7802575000155', 'Fideos spaghetti 400 g', 990, 8, 'C');
        $azucar = $p('7801555000202', 'Azúcar 1 kg', 1290, 6, 'C');
        $p('7801234567892', 'Cecina jamón 250 g', 2490, 4, 'C');   // sin stock -> aparece en alertas

        // HU10: parámetros de reposición de ejemplo (el lácteo llega cada 2 días; el abarrote, semanal)
        $leche->update(['dias_reposicion' => 2, 'stock_seguridad' => 10]);
        $huevos->update(['dias_reposicion' => 3, 'stock_seguridad' => 5]);
        $arroz->update(['dias_reposicion' => 7, 'stock_seguridad' => 4]);

        $inventario->registrarRecepcion($bodeguero, ['proveedor_id' => $lacteos->id, 'numero_documento' => 'F-10234'], [
            ['producto_id' => $leche->id, 'cantidad' => 10, 'codigo_lote' => 'LE-2611A', 'fecha_vencimiento' => now()->addDays(4)->toDateString(), 'costo_unitario' => 850],
            ['producto_id' => $leche->id, 'cantidad' => 24, 'codigo_lote' => 'LE-2611B', 'fecha_vencimiento' => now()->addDays(20)->toDateString(), 'costo_unitario' => 850],
            ['producto_id' => $yogur->id, 'cantidad' => 12, 'codigo_lote' => 'YO-0912', 'fecha_vencimiento' => now()->addDays(6)->toDateString(), 'costo_unitario' => 280],
            // Lote ya vencido: aparece destacado en "Lotes por vencer" (HU12)
            ['producto_id' => $yogur->id, 'cantidad' => 3, 'codigo_lote' => 'YO-0901', 'fecha_vencimiento' => now()->subDay()->toDateString(), 'costo_unitario' => 280],
        ]);
        $inventario->registrarRecepcion($bodeguero, ['proveedor_id' => $abarrotes->id, 'numero_documento' => 'F-55871'], [
            ['producto_id' => $huevos->id, 'cantidad' => 8, 'codigo_lote' => 'HU-1020', 'fecha_vencimiento' => now()->addDays(18)->toDateString(), 'costo_unitario' => 2400],
            ['producto_id' => $bebida->id, 'cantidad' => 30, 'codigo_lote' => 'BE-3301', 'fecha_vencimiento' => now()->addMonths(6)->toDateString(), 'costo_unitario' => 1200],
            ['producto_id' => $arroz->id, 'cantidad' => 20, 'codigo_lote' => 'AR-0001', 'fecha_vencimiento' => null, 'costo_unitario' => 1000],
            ['producto_id' => $fideos->id, 'cantidad' => 6, 'codigo_lote' => 'FI-0420', 'fecha_vencimiento' => null, 'costo_unitario' => 600],
            ['producto_id' => $azucar->id, 'cantidad' => 15, 'codigo_lote' => 'AZ-0815', 'fecha_vencimiento' => null, 'costo_unitario' => 900],
        ]);
        $inventario->registrarRecepcion($bodeguero, ['proveedor_id' => $panaderia->id, 'numero_documento' => 'G-0091'], [
            ['producto_id' => $panMolde->id, 'cantidad' => 7, 'codigo_lote' => 'PM-1019', 'fecha_vencimiento' => now()->addDays(5)->toDateString(), 'costo_unitario' => 1500],
        ]);

        // Algunas ventas de ejemplo (descuentan por lote: la leche sale primero del lote LE-2611A)
        $inventario->registrarVenta($vendedor, [
            ['producto_id' => $leche->id, 'cantidad' => 12],
            ['producto_id' => $bebida->id, 'cantidad' => 2],
        ]);
        $inventario->registrarVenta($vendedor, [
            ['producto_id' => $huevos->id, 'cantidad' => 3],
            ['producto_id' => $panMolde->id, 'cantidad' => 2],
        ], 'debito');

        // HU13: una merma de ejemplo (un pan de molde con el envase roto)
        $loteDanado = $panMolde->lotes()->where('codigo_lote', 'PM-1019')->firstOrFail();
        $inventario->registrarMerma($bodeguero, $loteDanado->id, 1, 'dano', 'Envase roto al reponer la góndola');

        // HU11: órdenes de compra sugeridas (una por proveedor); la del mayorista queda enviada
        // para probar la recepción contra OC. La cecina no tiene proveedor y no se incluye.
        $compras->generarSugeridas($admin)
            ->firstWhere('proveedor_id', $abarrotes->id)
            ?->update(['estado' => OrdenCompra::ENVIADA, 'fecha_envio' => now()]);

        // HU14: conteo cíclico de la clase A con una diferencia (falta una bebida); B y C quedan pendientes
        $inventario->registrarConteo($bodeguero, 'A', [
            $leche->id => ['cantidad' => 22],
            $huevos->id => ['cantidad' => 5],
            $bebida->id => ['cantidad' => 27, 'motivo' => 'Venta no registrada en caja'],
        ]);
    }
}
