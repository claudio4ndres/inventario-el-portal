<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 con lista fija de alta rotación (informe, sección 2.5.3).
 *
 * La lista de productos de alta rotación se acuerda con el administrador al iniciar la
 * medición de la línea base (19-10-2026) y no cambia hasta el término del piloto. Por eso
 * es una marca propia del producto y no la clase ABC, que se recalcula con las ventas (HU17):
 * así la línea base manual y el sistema miden sobre los mismos productos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('alta_rotacion')->default(false)->after('clase_abc');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('alta_rotacion');
        });
    }
};
