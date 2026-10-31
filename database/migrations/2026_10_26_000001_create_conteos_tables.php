<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU14: conteo cíclico por clase ABC y ajustes (RF7, K6, OE1 M1.2).
 *
 * - conteos: cabecera de un conteo físico de una clase (A semanal, B quincenal, C mensual).
 *   Guarda cuántos productos se contaron y cuántos tuvieron diferencia para calcular la exactitud.
 * - detalle_conteos: entidad de intersección entre CONTEOS y PRODUCTOS (M:N). Copia el stock del
 *   sistema al momento del conteo (igual que el precio en detalle_ventas) para que el historial no cambie.
 * - movimientos.detalle_conteo_id: cada diferencia genera uno o más movimientos AJUSTE ligados a su línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteos', function (Blueprint $table) {
            $table->id();
            $table->char('clase_abc', 1);                                 // A, B o C
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_conteo');
            $table->unsignedInteger('productos_contados')->default(0);
            $table->unsignedInteger('productos_con_diferencia')->default(0);
            $table->timestamps();

            $table->index(['clase_abc', 'fecha_conteo']);                 // último conteo por clase (programación)
        });

        Schema::create('detalle_conteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('conteos')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('stock_sistema');                     // stock calculado desde los lotes al contar
            $table->unsignedInteger('cantidad_contada');                  // conteo físico
            $table->integer('diferencia');                                // contada − sistema: negativa = faltante
            $table->string('motivo', 80)->nullable();                     // obligatorio si hay diferencia
            $table->timestamps();

            $table->unique(['conteo_id', 'producto_id']);                 // un producto se cuenta una vez por conteo
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('detalle_conteo_id')->nullable()->after('recepcion_id')
                ->constrained('detalle_conteos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detalle_conteo_id');
        });
        Schema::dropIfExists('detalle_conteos');
        Schema::dropIfExists('conteos');
    }
};
