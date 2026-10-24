<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU10: punto de reorden calculado (RF4, K3, OE2).
 *
 * punto de reorden = demanda diaria promedio × días de reposición + stock de seguridad
 *
 * - dias_reposicion: días que tarda el proveedor en reponer el producto (lead time).
 * - stock_seguridad: unidades extra para cubrir variaciones de la demanda o atrasos.
 * Los productos existentes quedan con 3 días y 0 unidades, valores que el administrador ajusta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_reposicion')->default(3)->after('punto_reorden');
            $table->unsignedInteger('stock_seguridad')->default(0)->after('dias_reposicion');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['dias_reposicion', 'stock_seguridad']);
        });
    }
};
