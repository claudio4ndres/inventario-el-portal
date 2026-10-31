<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU11: orden de compra sugerida y recepción contra OC (RF4/RF3, K3/K2, OE2).
 *
 * - ordenes_compra: cabecera por proveedor, con estado borrador → enviada → recibida.
 * - detalle_ordenes_compra: entidad de intersección entre ORDENES_COMPRA y PRODUCTOS (M:N);
 *   guarda la cantidad sugerida por el sistema y la cantidad pedida (editable).
 * - recepciones.orden_compra_id: la recepción puede asociarse a la OC para comparar
 *   lo pedido con lo recibido. Es opcional porque no toda compra nace de una OC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('estado', 10)->default('borrador');       // borrador, enviada o recibida
            $table->timestamp('fecha_envio')->nullable();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->timestamps();

            $table->index('estado');
        });

        Schema::create('detalle_ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad_sugerida');            // punto de reorden × 2 − stock, al generar
            $table->unsignedInteger('cantidad');                     // cantidad pedida (editable en borrador)
            $table->timestamps();

            $table->unique(['orden_compra_id', 'producto_id']);      // un producto aparece una vez por OC
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')->nullable()->after('proveedor_id')
                ->constrained('ordenes_compra')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
        });
        Schema::dropIfExists('detalle_ordenes_compra');
        Schema::dropIfExists('ordenes_compra');
    }
};
